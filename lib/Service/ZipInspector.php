<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Service;

use OCA\FilesZip\Exceptions\ArchiveLimitExceededException;
use OCA\FilesZip\Exceptions\UnsafeArchiveEntryException;
use OCA\FilesZip\Model\ArchiveEntry;
use RuntimeException;
use ZipArchive;

final class ZipInspector {
	private const UNIX_OPSYS = 3;
	private const UNIX_TYPE_MASK = 0170000;
	private const UNIX_REGULAR = 0100000;
	private const UNIX_DIRECTORY = 0040000;

	public function __construct(
		private ArchivePathValidator $pathValidator,
		private int $maxEntries = 10000,
		private int $maxUncompressedBytes = 10737418240,
		private float $maxCompressionRatio = 1000.0,
	) {
	}

	/** @return list<ArchiveEntry> */
	public function inspect(ZipArchive $zip, int $archiveSize): array {
		if ($zip->numFiles > $this->maxEntries) {
			throw new ArchiveLimitExceededException('Archive contains too many entries');
		}

		$entries = [];
		$seenPaths = [];
		$totalSize = 0;

		for ($index = 0; $index < $zip->numFiles; $index++) {
			$stat = $zip->statIndex($index);
			if ($stat === false || !isset($stat['name'], $stat['size'])) {
				throw new UnsafeArchiveEntryException('Unable to inspect ZIP entry');
			}

			$name = (string)$stat['name'];
			$size = (int)$stat['size'];
			if ($size < 0) {
				throw new UnsafeArchiveEntryException('ZIP entry has an invalid size');
			}

			$directory = str_ends_with($name, '/');
			$opsys = 0;
			$attributes = 0;
			if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) && $opsys === self::UNIX_OPSYS) {
				$mode = ($attributes >> 16) & 0xffff;
				$type = $mode & self::UNIX_TYPE_MASK;
				if ($type !== 0 && $type !== self::UNIX_REGULAR && $type !== self::UNIX_DIRECTORY) {
					throw new UnsafeArchiveEntryException('ZIP links and special file types are not allowed');
				}
				$directory = $directory || $type === self::UNIX_DIRECTORY;
			}

			$path = $this->pathValidator->normalize($name);
			if (isset($seenPaths[$path])) {
				throw new UnsafeArchiveEntryException('Duplicate archive entry path');
			}
			$seenPaths[$path] = true;

			if ($directory && $size !== 0) {
				throw new UnsafeArchiveEntryException('Directory entry contains unexpected data');
			}

			if ($size > $this->maxUncompressedBytes - $totalSize) {
				throw new ArchiveLimitExceededException('Archive expands beyond the configured limit');
			}
			$totalSize += $size;
			$entries[] = new ArchiveEntry($name, $path, $size, $directory);
		}

		if ($archiveSize < 0) {
			throw new RuntimeException('Archive size is invalid');
		}
		if ($archiveSize > 0 && $totalSize > 1048576) {
			$ratio = $totalSize / $archiveSize;
			if ($ratio > $this->maxCompressionRatio) {
				throw new ArchiveLimitExceededException('Archive compression ratio is suspiciously high');
			}
		}

		return $entries;
	}
}
