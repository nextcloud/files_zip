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

final class SevenZipInspector {
	public function __construct(
		private ArchivePathValidator $pathValidator,
		private int $maxEntries = 10000,
		private int $maxUncompressedBytes = 10737418240,
		private float $maxCompressionRatio = 1000.0,
	) {
	}

	/** @return list<ArchiveEntry> */
	public function inspect(string $technicalListing, int $archiveSize): array {
		$blocks = $this->parseBlocks($technicalListing);
		if (count($blocks) > $this->maxEntries) {
			throw new ArchiveLimitExceededException('Archive contains too many entries');
		}

		$entries = [];
		$seenPaths = [];
		$totalSize = 0;

		foreach ($blocks as $block) {
			$sourcePath = $block['Path'] ?? null;
			if (!is_string($sourcePath) || $sourcePath === '') {
				throw new UnsafeArchiveEntryException('7z entry has no path');
			}
			if (($block['Encrypted'] ?? '-') === '+') {
				throw new UnsafeArchiveEntryException('Encrypted archive entries are not supported');
			}
			foreach (['Symbolic Link', 'Hard Link', 'Copy Link'] as $linkField) {
				if (isset($block[$linkField]) && trim($block[$linkField]) !== '') {
					throw new UnsafeArchiveEntryException('7z links are not allowed');
				}
			}

			$mode = trim(($block['Mode'] ?? '') . ' ' . ($block['Attributes'] ?? ''));
			$unixType = $this->detectUnixType($mode);
			if ($unixType !== null && $unixType !== '-' && $unixType !== 'd') {
				throw new UnsafeArchiveEntryException('7z special file types are not allowed');
			}

			$directory = ($block['Folder'] ?? '-') === '+' || $unixType === 'd';
			$sizeValue = $block['Size'] ?? '0';
			if (!preg_match('/^[0-9]+$/', $sizeValue)) {
				throw new UnsafeArchiveEntryException('7z entry has an invalid size');
			}
			$size = filter_var($sizeValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
			if ($size === false) {
				throw new ArchiveLimitExceededException('7z entry size exceeds the supported integer range');
			}
			if ($directory && $size !== 0) {
				throw new UnsafeArchiveEntryException('7z directory entry contains unexpected data');
			}

			$path = $this->pathValidator->normalize($sourcePath);
			if (isset($seenPaths[$path])) {
				throw new UnsafeArchiveEntryException('Duplicate archive entry path');
			}
			$seenPaths[$path] = true;

			if ($size > $this->maxUncompressedBytes - $totalSize) {
				throw new ArchiveLimitExceededException('Archive expands beyond the configured limit');
			}
			$totalSize += $size;
			$entries[] = new ArchiveEntry($sourcePath, $path, $size, $directory);
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

	/** @return list<array<string, string>> */
	private function parseBlocks(string $output): array {
		$lines = preg_split('/\r\n|\n|\r/', $output);
		if ($lines === false) {
			throw new UnsafeArchiveEntryException('Unable to parse 7z listing');
		}

		$afterSeparator = false;
		$current = [];
		$blocks = [];

		foreach ($lines as $line) {
			if (!$afterSeparator) {
				if (trim($line) === '----------') {
					$afterSeparator = true;
				}
				continue;
			}

			if ($line === '') {
				if ($current !== []) {
					$blocks[] = $current;
					$current = [];
				}
				continue;
			}

			$separator = strpos($line, ' = ');
			if ($separator === false) {
				throw new UnsafeArchiveEntryException('Malformed 7z technical listing');
			}
			$key = substr($line, 0, $separator);
			$value = substr($line, $separator + 3);
			if ($key === '' || isset($current[$key])) {
				throw new UnsafeArchiveEntryException('Ambiguous 7z technical listing');
			}
			$current[$key] = $value;
		}

		if ($current !== []) {
			$blocks[] = $current;
		}
		if (!$afterSeparator) {
			throw new UnsafeArchiveEntryException('7z listing does not contain an entry section');
		}
		return $blocks;
	}

	private function detectUnixType(string $metadata): ?string {
		if (preg_match('/(?:^|\s)([bcdlps-])[rwxStTs-]{9}(?:\s|$)/', $metadata, $matches) !== 1) {
			return null;
		}
		return $matches[1];
	}
}
