<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Tests\Unit;

use OCA\FilesZip\Exceptions\UnsafeArchiveEntryException;
use OCA\FilesZip\Service\ArchivePathValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArchivePathValidatorTest extends TestCase {
	private ArchivePathValidator $validator;

	protected function setUp(): void {
		$this->validator = new ArchivePathValidator();
	}

	public static function validPaths(): array {
		return [
			['file.txt', 'file.txt'],
			['folder/file.txt', 'folder/file.txt'],
			['folder\\file.txt', 'folder/file.txt'],
			['./folder//file.txt', 'folder/file.txt'],
			['Überblick/Foto 01.jpg', 'Überblick/Foto 01.jpg'],
		];
	}

	#[DataProvider('validPaths')]
	public function testValidPath(string $input, string $expected): void {
		self::assertSame($expected, $this->validator->normalize($input));
	}

	public static function unsafePaths(): array {
		$deepPath = implode('/', array_fill(0, 65, 'folder')) . '/file.txt';

		return [
			['../evil.txt'],
			['folder/../../evil.txt'],
			['/etc/passwd'],
			['C:/Windows/win.ini'],
			['C:\\Windows\\win.ini'],
			['C:Windows/win.ini'],
			["safe\0evil"],
			["line\nbreak.txt"],
			["tab\tname.txt"],
			["delete\x7Fname.txt"],
			["invalid\xFFutf8"],
			['.'],
			['./'],
			[$deepPath],
		];
	}

	#[DataProvider('unsafePaths')]
	public function testUnsafePath(string $input): void {
		$this->expectException(UnsafeArchiveEntryException::class);
		$this->validator->normalize($input);
	}
}
