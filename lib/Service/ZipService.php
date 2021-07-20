<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesZip\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use ZipStreamer\ZipStreamer;

class ZipService {

	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(IRootFolder $rootFolder) {
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @param string $uid
	 * @param int[] $fileIds
	 * @param string $target
	 *
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 * @throws \OC\User\NoUserException
	 */
	public function zip(string $uid, array $fileIds, string $target) {
		$userFolder = $this->rootFolder->getUserFolder($uid);

		// Todo obtain proper target node
		// todo; verify node doesn't exist yet
		$taregetNode = $userFolder->newFile('target.zip');
		$outStream = $taregetNode->fopen('w');

		$zip = new ZipStreamer([
			'outstream' => $outStream,
			'zip64' => true,
		]);

		foreach ($fileIds as $fileId) {
			$nodes = $userFolder->getById($fileId);

			if (count($nodes) === 0) {
				continue;
			}

			$node = array_pop($nodes);
			$this->addNode($zip, $node, '');
		}

		$zip->finalize();

		fclose($outStream);

		// Todo send notification
	}

	private function addNode(ZipStreamer $streamer, Node $node, string $path) {
		if ($node instanceof Folder) {
			$this->addFolder($streamer, $node, $path);
		}

		if ($node instanceof File) {
			$this->addFile($streamer, $node, $path);
		}
	}

	private function addFolder(ZipStreamer $streamer, Folder $folder, string $path) {
		$nodes = $folder->getDirectoryListing();

		if (count($nodes) === 0) {
			$streamer->addEmptyDir($path . $folder->getName(), [
				'timestamp' => $folder->getMTime(),
			]);
		}

		foreach ($nodes as $node) {
			$this->addNode($streamer, $node, $path . $folder->getName() . '/');
		}
	}

	private function addFile(ZipStreamer $streamer, File $file, string $path) {
		$stream = $file->fopen('r');
		$streamer->addFileFromStream($stream, $path . $file->getName(), [
			'timestamp' => $file->getMTime(),
		]);
	}
}
