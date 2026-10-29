<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Model;

final readonly class ArchiveEntry {
	public function __construct(
		public string $sourcePath,
		public string $path,
		public int $size,
		public bool $directory,
	) {
	}
}
