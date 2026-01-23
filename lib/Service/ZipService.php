<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Service;

use Exception;
use Icewind\Streams\CountWrapper;
use OC\Files\Filesystem;
use OC\User\NoUserException;
use OCA\Files_Sharing\SharedStorage;
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
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use OCP\Share\IAttributes;
use OCP\Share\IShare;
use ZipStreamer\ZipStreamer;

class ZipService {


	public function __construct(
		private IRootFolder $rootFolder,
		private NotificationService $notificationService,
		private IUserSession $userSession,
		private IJobList $jobList,
		private ITimeFactory $timeFactory,
		private IConfig $config,
		private IUserManager $userManager,
	) {
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
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MaximumSizeReachedException
	 * @throws TargetAlreadyExists
	 */
	public function createZipJobForPath(string $filePath): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new Exception('No user session available');
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$nodeToZip = $userFolder->get($filePath);

		$zipFilePath = Filesystem::normalizePath($filePath . '.zip');
		$zipFilePath = $this->generateUniqueTarget($zipFilePath, $userFolder);

		$this->createZipJob([$nodeToZip->getId()], $zipFilePath);
	}

	/**
	 * Generates a unique path for the given path.
	 *
	 * If the given path exists "(2)" is added after the filename (but before
	 * the ".zip" extension). If the file with "(2)" exists then it is tried
	 * with "(3)", "(4)" and so on until a path not existing yet is found.
	 *
	 * @param string $path the path to get a unique path for, relative to the
	 *                     user folder.
	 * @param Folder $userFolder the user folder.
	 * @return string the unique path.
	 */
	private function generateUniqueTarget(string $path, Folder $userFolder): string {
		$pathinfo = pathinfo($path);

		$extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
		$filename = $pathinfo['filename'];
		$dirname = $pathinfo['dirname'];

		$i = 2;
		while ($userFolder->nodeExists($path)) {
			$path = Filesystem::normalizePath($dirname . '/' . $filename . ' (' . $i . ')' . $extension);
			$i++;
		}

		return $path;
	}

	/**
	 * @throws NotPermittedException
	 * @throws NoUserException
	 * @throws TargetAlreadyExists
	 * @throws LockedException
	 * @throws MaximumSizeReachedException
	 */
	public function zip(string $uid, array $fileIds, string $target): File {
		try {
			$this->userSession->setVolatileActiveUser($this->userManager->get($uid));
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
		} finally {
			$this->userSession->setVolatileActiveUser(null);
		}
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

			/** @var Node $node */
			$node = array_pop($nodes);

			// Skip incoming shares without download permission
			$storage = $node->getStorage();
			if ($node->isShared() && $storage->instanceOfStorage(SharedStorage::class) && method_exists(IShare::class, 'getAttributes')) {
				/** @var SharedStorage $storage */
				$share = $storage->getShare();
				$hasShareAttributes = $share && $share->getAttributes() instanceof IAttributes;
				if ($hasShareAttributes && $share->getAttributes()->getAttribute('permissions', 'download') === false) {
					continue;
				}
			}

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
