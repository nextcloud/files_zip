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

final class TarInspector {
	private const BLOCK_SIZE = 512;
	private const MAX_METADATA_BYTES = 1048576;

	public function __construct(
		private ArchivePathValidator $pathValidator,
		private int $maxEntries = 10000,
		private int $maxUncompressedBytes = 10737418240,
	) {
	}

	/** @return list<ArchiveEntry> */
	public function inspect(string $archivePath, bool $gzip): array {
		$stream = $gzip ? @gzopen($archivePath, 'rb') : @fopen($archivePath, 'rb');
		if ($stream === false) {
			throw new RuntimeException('Unable to open TAR archive');
		}

		try {
			return $this->inspectStream($stream, $gzip);
		} finally {
			$gzip ? gzclose($stream) : fclose($stream);
		}
	}

	/**
	 * @param resource $stream
	 * @return list<ArchiveEntry>
	 */
	private function inspectStream($stream, bool $gzip): array {
		$entries = [];
		$seenPaths = [];
		$totalSize = 0;
		$pendingLongName = null;
		$pendingPax = [];

		while (true) {
			$header = $this->readExact($stream, self::BLOCK_SIZE, $gzip);
			if ($header === '') {
				break;
			}
			if (strlen($header) !== self::BLOCK_SIZE) {
				throw new UnsafeArchiveEntryException('Truncated TAR header');
			}
			if ($header === str_repeat("\0", self::BLOCK_SIZE)) {
				break;
			}

			$this->verifyChecksum($header);

			$name = rtrim(substr($header, 0, 100), "\0");
			$prefix = rtrim(substr($header, 345, 155), "\0");
			if ($prefix !== '') {
				$name = $prefix . '/' . $name;
			}

			$type = substr($header, 156, 1);
			$size = $this->parseNumber(substr($header, 124, 12));

			if ($type === 'x' || $type === 'g' || $type === 'L' || $type === 'K') {
				if ($size > self::MAX_METADATA_BYTES) {
					throw new ArchiveLimitExceededException('TAR metadata entry is too large');
				}
				$data = $this->readPaddedData($stream, $size, $gzip);

				if ($type === 'x') {
					if ($pendingPax !== []) {
						throw new UnsafeArchiveEntryException('Multiple pending PAX headers are not allowed');
					}
					$pendingPax = $this->parsePax($data);
					$this->assertSafePax($pendingPax);
				} elseif ($type === 'g') {
					$global = $this->parsePax($data);
					if (isset($global['path']) || isset($global['linkpath'])) {
						throw new UnsafeArchiveEntryException('Global PAX path metadata is not allowed');
					}
				} elseif ($type === 'L') {
					if ($pendingLongName !== null) {
						throw new UnsafeArchiveEntryException('Multiple pending GNU long names are not allowed');
					}
					$pendingLongName = rtrim($data, "\0\r\n");
				} else {
					throw new UnsafeArchiveEntryException('GNU long link entries are not allowed');
				}
				continue;
			}

			if (isset($pendingPax['path'])) {
				$name = $pendingPax['path'];
			} elseif ($pendingLongName !== null) {
				$name = $pendingLongName;
			}

			if (isset($pendingPax['size'])) {
				if (!ctype_digit($pendingPax['size'])) {
					throw new UnsafeArchiveEntryException('Invalid PAX size');
				}
				$size = $this->decimalToInt($pendingPax['size']);
			}

			$pendingPax = [];
			$pendingLongName = null;

			$directory = $type === '5';
			$regular = $type === "\0" || $type === '0';
			if (!$directory && !$regular) {
				throw new UnsafeArchiveEntryException('TAR links and special file types are not allowed');
			}

			$path = $this->pathValidator->normalize($name);
			if (isset($seenPaths[$path])) {
				throw new UnsafeArchiveEntryException('Duplicate archive entry path');
			}
			$seenPaths[$path] = true;

			if (count($entries) >= $this->maxEntries) {
				throw new ArchiveLimitExceededException('Archive contains too many entries');
			}

			if ($directory && $size !== 0) {
				throw new UnsafeArchiveEntryException('Directory entry contains unexpected data');
			}

			if ($size > $this->maxUncompressedBytes - $totalSize) {
				throw new ArchiveLimitExceededException('Archive expands beyond the configured limit');
			}
			$totalSize += $size;
			$entries[] = new ArchiveEntry($name, $path, $size, $directory);

			$this->skipPaddedData($stream, $size, $gzip);
		}

		if ($pendingLongName !== null || $pendingPax !== []) {
			throw new UnsafeArchiveEntryException('Dangling TAR metadata entry');
		}

		return $entries;
	}

	private function verifyChecksum(string $header): void {
		$stored = $this->parseOctal(substr($header, 148, 8));
		$checksumHeader = substr_replace($header, str_repeat(' ', 8), 148, 8);
		$calculated = array_sum(unpack('C*', $checksumHeader));
		if ($stored !== $calculated) {
			throw new UnsafeArchiveEntryException('Invalid TAR header checksum');
		}
	}

	private function parseNumber(string $field): int {
		if ((ord($field[0]) & 0x80) !== 0) {
			$bytes = array_values(unpack('C*', $field));
			$bytes[0] &= 0x7f;
			$value = 0;
			foreach ($bytes as $byte) {
				if ($value > intdiv(PHP_INT_MAX - $byte, 256)) {
					throw new ArchiveLimitExceededException('TAR numeric value is too large');
				}
				$value = ($value * 256) + $byte;
			}
			return $value;
		}

		return $this->parseOctal($field);
	}

	private function parseOctal(string $field): int {
		$value = trim($field, " \0");
		if ($value === '') {
			return 0;
		}
		if (preg_match('/^[0-7]+$/', $value) !== 1) {
			throw new UnsafeArchiveEntryException('Invalid TAR numeric field');
		}

		$result = 0;
		foreach (str_split($value) as $digit) {
			$n = ord($digit) - 48;
			if ($result > intdiv(PHP_INT_MAX - $n, 8)) {
				throw new ArchiveLimitExceededException('TAR numeric value is too large');
			}
			$result = ($result * 8) + $n;
		}
		return $result;
	}

	private function decimalToInt(string $value): int {
		$result = 0;
		foreach (str_split($value) as $digit) {
			$n = ord($digit) - 48;
			if ($result > intdiv(PHP_INT_MAX - $n, 10)) {
				throw new ArchiveLimitExceededException('PAX numeric value is too large');
			}
			$result = ($result * 10) + $n;
		}
		return $result;
	}

	/** @param array<string, string> $pax */
	private function assertSafePax(array $pax): void {
		if (isset($pax['linkpath'])) {
			throw new UnsafeArchiveEntryException('PAX link metadata is not allowed');
		}

		foreach (array_keys($pax) as $key) {
			if (str_starts_with($key, 'GNU.sparse') || $key === 'SCHILY.realsize') {
				throw new UnsafeArchiveEntryException('Sparse TAR entries are not allowed');
			}
		}
	}

	/** @return array<string, string> */
	private function parsePax(string $data): array {
		$result = [];
		$offset = 0;
		$length = strlen($data);

		while ($offset < $length) {
			$space = strpos($data, ' ', $offset);
			if ($space === false) {
				throw new UnsafeArchiveEntryException('Invalid PAX record');
			}
			$lengthText = substr($data, $offset, $space - $offset);
			if ($lengthText === '' || !ctype_digit($lengthText)) {
				throw new UnsafeArchiveEntryException('Invalid PAX record length');
			}
			$recordLength = $this->decimalToInt($lengthText);
			if ($recordLength <= ($space - $offset + 1) || $offset + $recordLength > $length) {
				throw new UnsafeArchiveEntryException('Invalid PAX record bounds');
			}

			$record = substr($data, $space + 1, $recordLength - ($space - $offset + 1));
			if (!str_ends_with($record, "\n")) {
				throw new UnsafeArchiveEntryException('Invalid PAX record terminator');
			}
			$record = substr($record, 0, -1);
			$equals = strpos($record, '=');
			if ($equals === false) {
				throw new UnsafeArchiveEntryException('Invalid PAX key/value record');
			}
			$key = substr($record, 0, $equals);
			$value = substr($record, $equals + 1);
			$result[$key] = $value;
			$offset += $recordLength;
		}

		return $result;
	}

	/** @param resource $stream */
	private function readPaddedData($stream, int $size, bool $gzip): string {
		$data = $this->readExact($stream, $size, $gzip);
		if (strlen($data) !== $size) {
			throw new UnsafeArchiveEntryException('Truncated TAR metadata entry');
		}
		$this->skipPadding($stream, $size, $gzip);
		return $data;
	}

	/** @param resource $stream */
	private function skipPaddedData($stream, int $size, bool $gzip): void {
		$this->skipExact($stream, $size, $gzip);
		$this->skipPadding($stream, $size, $gzip);
	}

	/** @param resource $stream */
	private function skipPadding($stream, int $size, bool $gzip): void {
		$padding = (self::BLOCK_SIZE - ($size % self::BLOCK_SIZE)) % self::BLOCK_SIZE;
		$this->skipExact($stream, $padding, $gzip);
	}

	/** @param resource $stream */
	private function skipExact($stream, int $bytes, bool $gzip): void {
		$remaining = $bytes;
		while ($remaining > 0) {
			$chunk = $this->readExact($stream, min($remaining, 65536), $gzip);
			if ($chunk === '') {
				throw new UnsafeArchiveEntryException('Truncated TAR entry');
			}
			$remaining -= strlen($chunk);
		}
	}

	/** @param resource $stream */
	private function readExact($stream, int $bytes, bool $gzip): string {
		if ($bytes === 0) {
			return '';
		}

		$data = '';
		while (strlen($data) < $bytes) {
			$remaining = $bytes - strlen($data);
			$chunk = $gzip ? gzread($stream, $remaining) : fread($stream, $remaining);
			if ($chunk === false || $chunk === '') {
				break;
			}
			$data .= $chunk;
		}
		return $data;
	}
}
