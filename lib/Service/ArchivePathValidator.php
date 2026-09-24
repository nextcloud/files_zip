<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Service;

use OCA\FilesZip\Exceptions\UnsafeArchiveEntryException;

final class ArchivePathValidator {
	private const MAX_DEPTH = 64;

	/**
	 * Normalize an archive entry to a safe path relative to the extraction root.
	 *
	 * Absolute paths, Windows drive paths, control characters, invalid UTF-8 and
	 * parent traversal are rejected.
	 */
	public function normalize(string $path): string {
		if (
			$path === ''
			|| preg_match('//u', $path) !== 1
			|| preg_match('/[\x00-\x1F\x7F]/u', $path) === 1
		) {
			throw new UnsafeArchiveEntryException('Archive entry has an invalid path');
		}

		$path = str_replace('\\', '/', $path);

		if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
			throw new UnsafeArchiveEntryException('Absolute or drive-qualified archive paths are not allowed');
		}

		$segments = [];
		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}

			if ($segment === '..') {
				throw new UnsafeArchiveEntryException('Archive path traversal is not allowed');
			}

			$segments[] = $segment;
			if (count($segments) > self::MAX_DEPTH) {
				throw new UnsafeArchiveEntryException('Archive entry is nested too deeply');
			}
		}

		if ($segments === []) {
			throw new UnsafeArchiveEntryException('Archive entry resolves to an empty path');
		}

		return implode('/', $segments);
	}
}
