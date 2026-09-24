<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Tests\Unit;

use OCA\FilesZip\Exceptions\UnsupportedArchiveFormatException;
use OCA\FilesZip\Model\ArchiveFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArchiveFormatTest extends TestCase {
	public static function supportedFormats(): array {
		return [
			['archive.zip', ArchiveFormat::ZIP],
			['archive.ZIP', ArchiveFormat::ZIP],
			['archive.tar', ArchiveFormat::TAR],
			['archive.tar.gz', ArchiveFormat::TAR_GZ],
			['archive.tgz', ArchiveFormat::TAR_GZ],
			['archive.7z', ArchiveFormat::SEVEN_ZIP],
		];
	}

	#[DataProvider('supportedFormats')]
	public function testSupportedFormat(string $filename, ArchiveFormat $expected): void {
		self::assertSame($expected, ArchiveFormat::fromFilename($filename));
	}

	public function testUnsupportedFormat(): void {
		$this->expectException(UnsupportedArchiveFormatException::class);
		ArchiveFormat::fromFilename('archive.rar');
	}
}
