<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Tests\Unit;

use OCA\FilesZip\Service\SevenZipBinary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SevenZipBinaryTest extends TestCase {
	public static function versionOutputProvider(): array {
		return [
			['7-Zip 25.01 (x64) : Copyright (c) 1999-2025 Igor Pavlov', '25.01'],
			['7-Zip 26.03 (x64) : Copyright (c) 1999-2026 Igor Pavlov', '26.03'],
			['7z 25.01', '25.01'],
			['unexpected output', null],
		];
	}

	#[DataProvider('versionOutputProvider')]
	public function testParseVersion(string $output, ?string $expected): void {
		self::assertSame($expected, SevenZipBinary::parseVersion($output));
	}

	public function testMinimumVersionIsSecurityFloor(): void {
		self::assertFalse(version_compare('24.09', SevenZipBinary::MINIMUM_VERSION, '>='));
		self::assertTrue(version_compare('25.01', SevenZipBinary::MINIMUM_VERSION, '>='));
		self::assertTrue(version_compare('26.03', SevenZipBinary::MINIMUM_VERSION, '>='));
	}
}
