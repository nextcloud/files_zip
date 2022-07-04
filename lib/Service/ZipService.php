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

use Exception;
use Icewind\Streams\CountWrapper;
use OC\User\NoUserException;
use OCA\FilesZip\AppInfo\Application;
use OCA\FilesZip\BackgroundJob\ZipJob;
use OCA\FilesZip\Exceptions\MaximumSizeReachedException;
use OCA\FilesZip\Exceptions\TargetAlreadyExists;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use ZipStreamer\ZipStreamer;

class ZipService {

	/** @var IRootFolder */
	private $rootFolder;
	/** @var NotificationService */
	private $notificationService;
	/** @var IUserSession */
	private $userSession;
	/** @var IJobList */
	private $jobList;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IConfig */
	private $config;

	public function __construct(
		IRootFolder $rootFolder,
		NotificationService $notificationService,
		IUserSession $userSession,
		IJobList $jobList,
		ITimeFactory $timeFactory,
		IConfig $config
	) {
		$this->rootFolder = $rootFolder;
		$this->notificationService = $notificationService;
		$this->userSession = $userSession;
		$this->jobList = $jobList;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
	}

	/**
	 * @throws MaximumSizeReachedException
	 * @throws TargetAlreadyExists
	 */
	public function createZipJob(array $fileIds, string $target): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new Exception('No user session available');
		}
		if (strlen($target) === 0) {
			throw new Exception('The target is invalid');
		}

		$this->verifyAndGetFiles($user->getUID(), $fileIds, $target);

		$this->jobList->add(ZipJob::class, [
			'uid' => $user->getUID(),
			'fileIds' => $fileIds,
			'target' => $target,
		]);
		$this->notificationService->sendNotificationOnPending($user->getUID(), $target);
	}

	/**
	 * @throws NotPermittedException
	 * @throws NoUserException
	 * @throws TargetAlreadyExists
	 * @throws LockedException
	 * @throws MaximumSizeReachedException
	 */
	public function zip(string $uid, array $fileIds, string $target): File {
		$userFolder = $this->rootFolder->getUserFolder($uid);

		$files = $this->verifyAndGetFiles($uid, $fileIds, $target);

		$targetNode = $userFolder->newFile($target);
		$outStream = $targetNode->fopen('w');

		$countStream = CountWrapper::wrap($outStream, function ($readSize, $writtenSize) use ($targetNode) {
			$targetNode->getStorage()->getCache()->update($targetNode->getId(), ['size' => $writtenSize]);
			$targetNode->getStorage()->getPropagator()->propagateChange($targetNode->getInternalPath(), $this->timeFactory->getTime(), $writtenSize);
		});

		$zip = new ZipStreamer([
			'outstream' => $countStream,
			'zip64' => true,
		]);

		foreach ($files as $node) {
			$this->addNode($zip, $node, '');
		}

		$zip->finalize();

		fclose($outStream);

		return $targetNode;
	}

	private function verifyAndGetFiles($uid, $fileIds, $target): array {
		$userFolder = $this->rootFolder->getUserFolder($uid);

		try {
			$userFolder->get($target);
			throw new TargetAlreadyExists();
		} catch (NotFoundException $e) {
			// Expected behavior that the file does not exist yet
		}

		$files = [];
		$size = 0;
		foreach ($fileIds as $fileId) {
			$nodes = $userFolder->getById($fileId);

			if (count($nodes) === 0) {
				continue;
			}

			$node = array_pop($nodes);
			$files[] = $node;
			$size += $node->getSize();
		}

		$maxSize = (int)$this->config->getAppValue(Application::APP_NAME, 'max_compress_size', -1);
		if ($maxSize !== -1 && $size > $maxSize) {
			throw new MaximumSizeReachedException();
		}

		return $files;
	}

	private function addNode(ZipStreamer $streamer, Node $node, string $path): void {
		if ($node instanceof Folder) {
			$this->addFolder($streamer, $node, $path);
		}

		if ($node instanceof File) {
			$this->addFile($streamer, $node, $path);
		}
	}

	private function addFolder(ZipStreamer $streamer, Folder $folder, string $path): void {
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

	private function addFile(ZipStreamer $streamer, File $file, string $path): void {
		$stream = $file->fopen('r');
		$streamer->addFileFromStream($stream, $path . $file->getName(), [
			'timestamp' => $file->getMTime(),
		]);
	}
}
