<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Service;

use OCA\FilesZip\Exceptions\UnsafeArchiveEntryException;
use OCP\Files\File;
use OCP\Files\Folder;
use RuntimeException;

final class ExtractionWriter {
	public function __construct(
		private ArchivePathValidator $pathValidator,
	) {
	}

	public function createExtractionRoot(Folder $parent, string $name): Folder {
		$name = $this->pathValidator->normalize($name);
		if (str_contains($name, '/')) {
			throw new UnsafeArchiveEntryException('Extraction root must be a direct child of the destination folder');
		}
		if (!$parent->isCreatable()) {
			throw new RuntimeException('Destination folder is not writable');
		}
		$parent->verifyPath($name);
		if ($parent->nodeExists($name)) {
			throw new RuntimeException('Extraction target already exists');
		}

		return $parent->newFolder($name);
	}

	public function ensureDirectory(Folder $root, string $path): Folder {
		$path = $this->pathValidator->normalize($path);
		$current = $root;
		foreach (explode('/', $path) as $segment) {
			$current->verifyPath($segment);
			if ($current->nodeExists($segment)) {
				$node = $current->get($segment);
				if (!$node instanceof Folder) {
					throw new UnsafeArchiveEntryException('Archive path conflicts with an existing file');
				}
				$current = $node;
				continue;
			}
			$current = $current->newFolder($segment);
		}

		return $current;
	}

	/** @param resource $source */
	public function writeFile(Folder $root, string $path, $source, int $expectedSize): File {
		$path = $this->pathValidator->normalize($path);
		$segments = explode('/', $path);
		$name = array_pop($segments);
		$parent = $root;
		if ($segments !== []) {
			$parent = $this->ensureDirectory($root, implode('/', $segments));
		}

		$parent->verifyPath($name);
		if ($parent->nodeExists($name)) {
			throw new UnsafeArchiveEntryException('Archive would overwrite an existing entry');
		}

		$file = $parent->newFile($name);
		$output = $file->fopen('wb');
		if ($output === false) {
			throw new RuntimeException('Unable to open extraction target for writing');
		}

		try {
			$written = stream_copy_to_stream($source, $output, $expectedSize);
			if ($written === false || $written !== $expectedSize) {
				throw new RuntimeException('Archive entry ended before the expected size was written');
			}
		} finally {
			fclose($output);
		}

		return $file;
	}
}
