<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Model;

use OCA\FilesZip\Exceptions\UnsupportedArchiveFormatException;

enum ArchiveFormat: string {
	case ZIP = 'zip';
	case TAR = 'tar';
	case TAR_GZ = 'tar.gz';
	case SEVEN_ZIP = '7z';

	public static function fromFilename(string $filename): self {
		$name = strtolower($filename);

		return match (true) {
			str_ends_with($name, '.tar.gz'), str_ends_with($name, '.tgz') => self::TAR_GZ,
			str_ends_with($name, '.tar') => self::TAR,
			str_ends_with($name, '.zip') => self::ZIP,
			str_ends_with($name, '.7z') => self::SEVEN_ZIP,
			default => throw new UnsupportedArchiveFormatException('Unsupported archive format'),
		};
	}

	public function extension(): string {
		return match ($this) {
			self::ZIP => '.zip',
			self::TAR => '.tar',
			self::TAR_GZ => '.tar.gz',
			self::SEVEN_ZIP => '.7z',
		};
	}
}
