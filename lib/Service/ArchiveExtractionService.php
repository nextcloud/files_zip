<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Service;

use OCA\Files_Sharing\SharedStorage;
use OCA\FilesZip\BackgroundJob\ExtractJob;
use OCA\FilesZip\Exceptions\ArchiveLimitExceededException;
use OCA\FilesZip\Exceptions\UnsupportedArchiveFormatException;
use OCA\FilesZip\Model\ArchiveEntry;
use OCA\FilesZip\Model\ArchiveFormat;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IAttributes;
use OCP\Share\IShare;
use PharData;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ArchiveExtractionService {
	private const APP_ID = 'files_zip';
	private const DEFAULT_MAX_ARCHIVE_BYTES = 5368709120;
	private const TEMP_DISK_RESERVE_BYTES = 268435456;

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private IUserManager $userManager,
		private ITempManager $tempManager,
		private IConfig $config,
		private IJobList $jobList,
		private ArchivePathValidator $pathValidator,
		private ExtractionWriter $writer,
		private ZipInspector $zipInspector,
		private TarInspector $tarInspector,
		private SevenZipBinary $sevenZip,
		private SevenZipInspector $sevenZipInspector,
	) {
	}

	public function createExtractJob(int $fileId, string $target): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new RuntimeException('No user session available');
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$archive = $this->getReadableArchive($userFolder, $fileId);
		$format = ArchiveFormat::fromFilename($archive->getName());
		$this->assertFormatAvailable($format);
		$this->resolveTarget($userFolder, $target);

		$this->jobList->add(ExtractJob::class, [
			'uid' => $user->getUID(),
			'fileId' => $fileId,
			'target' => $target,
		]);
	}

	public function extract(string $uid, int $fileId, string $target): Folder {
		$user = $this->userManager->get($uid);
		if ($user === null) {
			throw new RuntimeException('User does not exist');
		}

		$this->userSession->setVolatileActiveUser($user);
		$tempPath = null;
		$extractionRoot = null;

		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			$archive = $this->getReadableArchive($userFolder, $fileId);
			$format = ArchiveFormat::fromFilename($archive->getName());
			$this->assertFormatAvailable($format);
			[$parent, $rootName] = $this->resolveTarget($userFolder, $target);

			$tempPath = $this->copyArchiveToTemporaryFile($archive, $format);
			$entries = $this->inspectArchive($tempPath, $format);
			$this->assertDestinationCapacity($parent, $entries);

			$extractionRoot = $this->writer->createExtractionRoot($parent, $rootName);
			$this->extractEntries($tempPath, $format, $entries, $extractionRoot);

			return $extractionRoot;
		} catch (Throwable $e) {
			if ($extractionRoot instanceof Folder) {
				try {
					$extractionRoot->delete();
				} catch (Throwable) {
					// Preserve the original extraction failure.
				}
			}
			throw $e;
		} finally {
			if (is_string($tempPath) && is_file($tempPath)) {
				@unlink($tempPath);
			}
			$this->userSession->setVolatileActiveUser(null);
		}
	}

	private function assertFormatAvailable(ArchiveFormat $format): void {
		if ($format === ArchiveFormat::ZIP && !class_exists(ZipArchive::class)) {
			throw new UnsupportedArchiveFormatException('The PHP zip extension is required for ZIP extraction');
		}
		if (($format === ArchiveFormat::TAR || $format === ArchiveFormat::TAR_GZ) && !class_exists(PharData::class)) {
			throw new UnsupportedArchiveFormatException('The PHP Phar extension is required for TAR extraction');
		}
		if ($format === ArchiveFormat::TAR_GZ && !function_exists('gzopen')) {
			throw new UnsupportedArchiveFormatException('The PHP zlib extension is required for TAR.GZ extraction');
		}
		if ($format === ArchiveFormat::SEVEN_ZIP && !$this->sevenZip->isAvailable()) {
			throw new UnsupportedArchiveFormatException('7z extraction requires ' . SevenZipBinary::PATH . ' >= ' . SevenZipBinary::MINIMUM_VERSION);
		}
	}

	private function getReadableArchive(Folder $userFolder, int $fileId): File {
		foreach ($userFolder->getById($fileId) as $node) {
			if (!$node instanceof File || !$node->isReadable()) {
				continue;
			}

			$storage = $node->getStorage();
			if ($node->isShared() && $storage->instanceOfStorage(SharedStorage::class) && method_exists(IShare::class, 'getAttributes')) {
				/** @var SharedStorage $storage */
				$share = $storage->getShare();
				$hasShareAttributes = $share && $share->getAttributes() instanceof IAttributes;
				if ($hasShareAttributes && $share->getAttributes()->getAttribute('permissions', 'download') === false) {
					continue;
				}
			}

			return $node;
		}

		throw new NotFoundException('Archive not found or not readable');
	}

	/** @return array{0: Folder, 1: string} */
	private function resolveTarget(Folder $userFolder, string $target): array {
		$target = $this->pathValidator->normalize(ltrim($target, '/'));
		$parentPath = dirname($target);
		$rootName = basename($target);
		$parent = $parentPath === '.' ? $userFolder : $userFolder->get($parentPath);
		if (!$parent instanceof Folder) {
			throw new RuntimeException('Extraction destination is not a folder');
		}
		if (!$parent->isCreatable()) {
			throw new RuntimeException('Extraction destination is not writable');
		}
		if ($parent->nodeExists($rootName)) {
			throw new RuntimeException('Extraction target already exists');
		}

		return [$parent, $rootName];
	}

	private function copyArchiveToTemporaryFile(File $archive, ArchiveFormat $format): string {
		$maxArchiveBytes = (int)$this->config->getAppValue(
			self::APP_ID,
			'max_extract_archive_size',
			(string)self::DEFAULT_MAX_ARCHIVE_BYTES,
		);
		$knownSize = (int)$archive->getSize();
		if ($maxArchiveBytes !== -1 && $knownSize >= 0 && $knownSize > $maxArchiveBytes) {
			throw new ArchiveLimitExceededException('Archive is larger than the configured extraction limit');
		}

		$tempPath = $this->tempManager->getTemporaryFile($format->extension());
		if ($tempPath === false) {
			throw new RuntimeException('Unable to allocate a temporary archive file');
		}

		try {
			$diskFree = @disk_free_space(dirname($tempPath));
			$diskLimit = false;
			if ($diskFree !== false) {
				$diskLimit = max(0, (int)$diskFree - self::TEMP_DISK_RESERVE_BYTES);
				if ($knownSize >= 0 && $knownSize > $diskLimit) {
					throw new ArchiveLimitExceededException('Not enough temporary disk space to inspect the archive');
				}
			}

			$effectiveLimit = $maxArchiveBytes;
			if ($diskLimit !== false && ($effectiveLimit === -1 || $diskLimit < $effectiveLimit)) {
				$effectiveLimit = $diskLimit;
			}

			$source = $archive->fopen('rb');
			$destination = @fopen($tempPath, 'wb');
			if ($source === false || $destination === false) {
				if (is_resource($source)) {
					fclose($source);
				}
				if (is_resource($destination)) {
					fclose($destination);
				}
				throw new RuntimeException('Unable to copy archive to temporary storage');
			}

			$total = 0;
			try {
				while (!feof($source)) {
					$chunk = fread($source, 1048576);
					if ($chunk === false) {
						throw new RuntimeException('Unable to read archive');
					}
					if ($chunk === '') {
						if (feof($source)) {
							break;
						}
						throw new RuntimeException('Archive stream stalled');
					}

					$length = strlen($chunk);
					if ($effectiveLimit !== -1 && $length > $effectiveLimit - $total) {
						throw new ArchiveLimitExceededException('Archive exceeds the safe temporary copy limit');
					}
					$this->writeAll($destination, $chunk);
					$total += $length;
				}
			} finally {
				fclose($source);
				fclose($destination);
			}

			return $tempPath;
		} catch (Throwable $e) {
			if (is_file($tempPath)) {
				@unlink($tempPath);
			}
			throw $e;
		}
	}

	/** @return list<ArchiveEntry> */
	private function inspectArchive(string $tempPath, ArchiveFormat $format): array {
		return match ($format) {
			ArchiveFormat::ZIP => $this->inspectZip($tempPath),
			ArchiveFormat::TAR => $this->tarInspector->inspect($tempPath, false),
			ArchiveFormat::TAR_GZ => $this->tarInspector->inspect($tempPath, true),
			ArchiveFormat::SEVEN_ZIP => $this->inspectSevenZip($tempPath),
		};
	}

	/** @return list<ArchiveEntry> */
	private function inspectZip(string $tempPath): array {
		$zip = new ZipArchive();
		$result = $zip->open($tempPath, ZipArchive::RDONLY);
		if ($result !== true) {
			throw new RuntimeException('Unable to open ZIP archive');
		}
		try {
			$size = filesize($tempPath);
			if ($size === false) {
				throw new RuntimeException('Unable to determine ZIP archive size');
			}
			return $this->zipInspector->inspect($zip, $size);
		} finally {
			$zip->close();
		}
	}

	/** @return list<ArchiveEntry> */
	private function inspectSevenZip(string $tempPath): array {
		$size = filesize($tempPath);
		if ($size === false) {
			throw new RuntimeException('Unable to determine 7z archive size');
		}
		$listing = $this->sevenZip->capture([
			'l', '-slt', '-bd', '-sccUTF-8', '-spd', '--', $tempPath,
		]);
		return $this->sevenZipInspector->inspect($listing, $size);
	}

	/** @param list<ArchiveEntry> $entries */
	private function assertDestinationCapacity(Folder $parent, array $entries): void {
		$total = 0;
		foreach ($entries as $entry) {
			if ($entry->size > PHP_INT_MAX - $total) {
				throw new ArchiveLimitExceededException('Archive size exceeds the supported integer range');
			}
			$total += $entry->size;
		}

		$freeSpace = $parent->getFreeSpace();
		if ($freeSpace >= 0 && $total > $freeSpace) {
			throw new ArchiveLimitExceededException('Archive does not fit in the destination quota');
		}
	}

	/** @param list<ArchiveEntry> $entries */
	private function extractEntries(string $tempPath, ArchiveFormat $format, array $entries, Folder $root): void {
		match ($format) {
			ArchiveFormat::ZIP => $this->extractZip($tempPath, $entries, $root),
			ArchiveFormat::TAR, ArchiveFormat::TAR_GZ => $this->extractTar($tempPath, $entries, $root),
			ArchiveFormat::SEVEN_ZIP => $this->extractSevenZip($tempPath, $entries, $root),
		};
	}

	/** @param list<ArchiveEntry> $entries */
	private function extractZip(string $tempPath, array $entries, Folder $root): void {
		$zip = new ZipArchive();
		$result = $zip->open($tempPath, ZipArchive::RDONLY);
		if ($result !== true) {
			throw new RuntimeException('Unable to reopen ZIP archive');
		}

		try {
			foreach ($entries as $entry) {
				if ($entry->directory) {
					$this->writer->ensureDirectory($root, $entry->path);
					continue;
				}

				$source = $zip->getStream($entry->sourcePath);
				if ($source === false) {
					throw new RuntimeException('Unable to open ZIP entry stream');
				}
				try {
					$this->writer->writeFile($root, $entry->path, $source, $entry->size);
				} finally {
					fclose($source);
				}
			}
		} finally {
			$zip->close();
		}
	}

	/** @param list<ArchiveEntry> $entries */
	private function extractTar(string $tempPath, array $entries, Folder $root): void {
		$archive = new PharData($tempPath);
		foreach ($entries as $entry) {
			if ($entry->directory) {
				$this->writer->ensureDirectory($root, $entry->path);
				continue;
			}

			if (!isset($archive[$entry->sourcePath])) {
				throw new RuntimeException('Inspected TAR entry cannot be reopened');
			}
			$fileInfo = $archive[$entry->sourcePath];
			if ($fileInfo->isDir() || $fileInfo->getSize() !== $entry->size) {
				throw new RuntimeException('TAR entry changed between inspection and extraction');
			}
			$source = @fopen($fileInfo->getPathname(), 'rb');
			if ($source === false) {
				throw new RuntimeException('Unable to open TAR entry stream');
			}
			try {
				$this->writer->writeFile($root, $entry->path, $source, $entry->size);
			} finally {
				fclose($source);
			}
		}
	}

	/** @param list<ArchiveEntry> $entries */
	private function extractSevenZip(string $tempPath, array $entries, Folder $root): void {
		foreach ($entries as $entry) {
			if ($entry->directory) {
				$this->writer->ensureDirectory($root, $entry->path);
				continue;
			}

			$entryTemp = $this->tempManager->getTemporaryFile('.7z-entry');
			if ($entryTemp === false) {
				throw new RuntimeException('Unable to allocate temporary 7z entry file');
			}
			try {
				$this->sevenZip->extractEntryToFile($tempPath, $entry->sourcePath, $entryTemp, $entry->size);
				$source = @fopen($entryTemp, 'rb');
				if ($source === false) {
					throw new RuntimeException('Unable to reopen temporary 7z entry');
				}
				try {
					$this->writer->writeFile($root, $entry->path, $source, $entry->size);
				} finally {
					fclose($source);
				}
			} finally {
				if (is_file($entryTemp)) {
					@unlink($entryTemp);
				}
			}
		}
	}

	/** @param resource $stream */
	private function writeAll($stream, string $data): void {
		$offset = 0;
		$length = strlen($data);
		while ($offset < $length) {
			$written = fwrite($stream, substr($data, $offset));
			if ($written === false || $written === 0) {
				throw new RuntimeException('Unable to write temporary archive data');
			}
			$offset += $written;
		}
	}
}
