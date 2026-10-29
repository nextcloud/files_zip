<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Tests\Unit;

use OCA\FilesZip\Exceptions\UnsafeArchiveEntryException;
use OCA\FilesZip\Service\ArchivePathValidator;
use OCA\FilesZip\Service\TarInspector;
use PHPUnit\Framework\TestCase;

final class TarInspectorTest extends TestCase {
	private TarInspector $inspector;
	/** @var list<string> */
	private array $temporaryFiles = [];

	protected function setUp(): void {
		$this->inspector = new TarInspector(new ArchivePathValidator());
	}

	protected function tearDown(): void {
		foreach ($this->temporaryFiles as $path) {
			@unlink($path);
		}
	}

	public function testInspectsRegularFilesAndDirectories(): void {
		$tar = $this->tar([
			['folder/', '', '5', ''],
			['folder/file.txt', 'hello', '0', ''],
		]);
		$path = $this->writeTemp($tar, '.tar');

		$entries = $this->inspector->inspect($path, false);

		self::assertCount(2, $entries);
		self::assertSame('folder', $entries[0]->path);
		self::assertTrue($entries[0]->directory);
		self::assertSame('folder/file.txt', $entries[1]->path);
		self::assertSame(5, $entries[1]->size);
		self::assertFalse($entries[1]->directory);
	}

	public function testInspectsGzipCompressedTar(): void {
		if (!function_exists('gzencode')) {
			self::markTestSkipped('zlib is not available');
		}

		$tar = $this->tar([
			['file.txt', 'hello', '0', ''],
		]);
		$compressed = gzencode($tar);
		self::assertNotFalse($compressed);
		$path = $this->writeTemp($compressed, '.tar.gz');

		$entries = $this->inspector->inspect($path, true);
		self::assertCount(1, $entries);
		self::assertSame('file.txt', $entries[0]->path);
	}

	public function testRejectsParentTraversal(): void {
		$path = $this->writeTemp($this->tar([
			['../outside.txt', 'owned', '0', ''],
		]), '.tar');

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($path, false);
	}

	public function testRejectsSymlink(): void {
		$path = $this->writeTemp($this->tar([
			['link', '', '2', '../../etc/passwd'],
		]), '.tar');

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($path, false);
	}

	public function testRejectsHardlink(): void {
		$path = $this->writeTemp($this->tar([
			['hardlink', '', '1', 'file.txt'],
		]), '.tar');

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($path, false);
	}

	public function testRejectsDuplicateDestinationPath(): void {
		$path = $this->writeTemp($this->tar([
			['same.txt', 'one', '0', ''],
			['same.txt', 'two', '0', ''],
		]), '.tar');

		$this->expectException(UnsafeArchiveEntryException::class);
		$this->inspector->inspect($path, false);
	}

	/**
	 * @param list<array{0: string, 1: string, 2: string, 3: string}> $entries
	 */
	private function tar(array $entries): string {
		$archive = '';
		foreach ($entries as [$name, $data, $type, $linkName]) {
			$archive .= $this->tarHeader($name, strlen($data), $type, $linkName);
			$archive .= $data;
			$padding = (512 - (strlen($data) % 512)) % 512;
			$archive .= str_repeat("\0", $padding);
		}

		return $archive . str_repeat("\0", 1024);
	}

	private function tarHeader(string $name, int $size, string $type, string $linkName): string {
		self::assertLessThanOrEqual(100, strlen($name), 'Test TAR name must fit the classic header');
		self::assertLessThanOrEqual(100, strlen($linkName), 'Test TAR link name must fit the classic header');

		$header = str_repeat("\0", 512);
		$header = substr_replace($header, str_pad($name, 100, "\0"), 0, 100);
		$header = substr_replace($header, "0000644\0", 100, 8);
		$header = substr_replace($header, "0000000\0", 108, 8);
		$header = substr_replace($header, "0000000\0", 116, 8);
		$header = substr_replace($header, sprintf('%011o', $size) . "\0", 124, 12);
		$header = substr_replace($header, sprintf('%011o', 0) . "\0", 136, 12);
		$header = substr_replace($header, str_repeat(' ', 8), 148, 8);
		$header = substr_replace($header, $type, 156, 1);
		$header = substr_replace($header, str_pad($linkName, 100, "\0"), 157, 100);
		$header = substr_replace($header, "ustar\0", 257, 6);
		$header = substr_replace($header, '00', 263, 2);

		$checksum = array_sum(unpack('C*', $header));
		$header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);
		return $header;
	}

	private function writeTemp(string $contents, string $suffix): string {
		$base = tempnam(sys_get_temp_dir(), 'files_zip_test_');
		self::assertNotFalse($base);
		$path = $base . $suffix;
		rename($base, $path);
		file_put_contents($path, $contents);
		$this->temporaryFiles[] = $path;
		return $path;
	}
}
