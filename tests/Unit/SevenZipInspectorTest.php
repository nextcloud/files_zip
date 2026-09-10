<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Tests\Unit;

use OCA\FilesZip\Exceptions\UnsafeArchiveEntryException;
use OCA\FilesZip\Service\ArchivePathValidator;
use OCA\FilesZip\Service\SevenZipInspector;
use PHPUnit\Framework\TestCase;

final class SevenZipInspectorTest extends TestCase {
	private SevenZipInspector $inspector;

	protected function setUp(): void {
		parent::setUp();
		$this->inspector = new SevenZipInspector(new ArchivePathValidator());
	}

	public function testRegularFilesAndDirectoriesAreAccepted(): void {
		$listing = $this->listing([
			[
				'Path' => 'folder',
				'Size' => '0',
				'Folder' => '+',
				'Attributes' => 'D_ drwxr-xr-x',
				'Encrypted' => '-',
			],
			[
				'Path' => 'folder/hello.txt',
				'Size' => '13',
				'Folder' => '-',
				'Attributes' => 'A_ -rw-r--r--',
				'Encrypted' => '-',
			],
		]);

		$entries = $this->inspector->inspect($listing, 128);

		self::assertCount(2, $entries);
		self::assertTrue($entries[0]->directory);
		self::assertSame('folder/hello.txt', $entries[1]->path);
		self::assertSame(13, $entries[1]->size);
	}

	public function testSymbolicLinkIsRejected(): void {
		$listing = $this->listing([[
			'Path' => 'link',
			'Size' => '6',
			'Folder' => '-',
			'Attributes' => 'A_ lrwxrwxrwx',
			'Symbolic Link' => '../etc',
			'Encrypted' => '-',
		]]);

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($listing, 128);
	}

	public function testHardLinkIsRejected(): void {
		$listing = $this->listing([[
			'Path' => 'hardlink',
			'Size' => '4',
			'Folder' => '-',
			'Attributes' => 'A_ -rw-r--r--',
			'Hard Link' => 'target',
			'Encrypted' => '-',
		]]);

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($listing, 128);
	}

	public function testTraversalIsRejected(): void {
		$listing = $this->listing([[
			'Path' => '../outside.txt',
			'Size' => '1',
			'Folder' => '-',
			'Attributes' => 'A_ -rw-r--r--',
			'Encrypted' => '-',
		]]);

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($listing, 128);
	}

	public function testDuplicateNormalizedPathIsRejected(): void {
		$listing = $this->listing([
			[
				'Path' => 'folder//file.txt',
				'Size' => '1',
				'Folder' => '-',
				'Attributes' => 'A_ -rw-r--r--',
				'Encrypted' => '-',
			],
			[
				'Path' => 'folder/file.txt',
				'Size' => '1',
				'Folder' => '-',
				'Attributes' => 'A_ -rw-r--r--',
				'Encrypted' => '-',
			],
		]);

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($listing, 128);
	}

	public function testEncryptedEntryIsRejected(): void {
		$listing = $this->listing([[
			'Path' => 'secret.txt',
			'Size' => '4',
			'Folder' => '-',
			'Attributes' => 'A_ -rw-------',
			'Encrypted' => '+',
		]]);

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($listing, 128);
	}

	public function testSpecialUnixFileIsRejected(): void {
		$listing = $this->listing([[
			'Path' => 'pipe',
			'Size' => '0',
			'Folder' => '-',
			'Attributes' => 'A_ prw-r--r--',
			'Encrypted' => '-',
		]]);

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($listing, 128);
	}

	/**
	 * @param list<array<string, string>> $entries
	 */
	private function listing(array $entries): string {
		$output = "Path = test.7z\nType = 7z\nPhysical Size = 128\n\n----------\n";
		foreach ($entries as $entry) {
			foreach ($entry as $key => $value) {
				$output .= $key . ' = ' . $value . "\n";
			}
			$output .= "\n";
		}
		return $output;
	}
}
