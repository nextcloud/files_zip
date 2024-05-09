<?php
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\FilesZip\Service\ZipService;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\Share\IShare;
use Test\TestCase;
use Test\Util\User\Dummy;

/**
 * @group DB
 */
class ZipFeatureTest extends TestCase {
	public const TEST_USER1 = "test-user1";

	public const TEST_FILES = [
		'unzipped1',
		'unzipped2',
		'unzipped3',
	];

	private $rootFolder;
	private $zipService;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		$backend = new Dummy();
		$backend->createUser(self::TEST_USER1, self::TEST_USER1);
		\OC::$server->get(IUserManager::class)->registerBackend($backend);
	}

	public function setUp(): void {
		parent::setUp();
		$this->rootFolder = \OC::$server->get(IRootFolder::class);
		$this->zipService = \OC::$server->get(ZipService::class);
		$folder = $this->loginAndGetUserFolder(self::TEST_USER1);
		foreach (self::TEST_FILES as $filename) {
			$folder->delete($filename);
		}
	}

	public function testZipJob() {
		$this->loginAndGetUserFolder(self::TEST_USER1)
			->delete('/pendingzipfile.zip');

		$files = [];
		foreach (self::TEST_FILES as $filename) {
			$files[] = $this->loginAndGetUserFolder(self::TEST_USER1)
				->newFile($filename, $filename);
		}


		$fileIds = array_map(function ($file) {
			return $file->getId();
		}, $files);
		$target = '/pendingzipfile.zip';
		$this->zipService->createZipJob($fileIds, $target);

		$jobList = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);
		$this->assertTrue($jobList->has(\OCA\FilesZip\BackgroundJob\ZipJob::class, [
			'uid' => self::TEST_USER1,
			'fileIds' => $fileIds,
			'target' => $target,
		]));
	}

	public function testZip() {
		$target = '/zipfile.zip';

		$userFolder = $this->loginAndGetUserFolder(self::TEST_USER1);
		$userFolder->delete($target);

		$files = [];
		foreach (self::TEST_FILES as $filename) {
			$files[] = $this->loginAndGetUserFolder(self::TEST_USER1)
				->newFile($filename, $filename);
		}


		$fileIds = array_map(function ($file) {
			return $file->getId();
		}, $files);
		try {
			$this->zipService->zip(self::TEST_USER1, $fileIds, $target);
		} catch (\PHPUnit\Framework\Error\Deprecated $e) {
			$this->markTestSkipped('Test skipped due to upstream issue https://github.com/DeepDiver1975/PHPZipStreamer/pull/11');
		}
		/** @var File $node */
		$node = $userFolder->get($target);
		$this->assertTrue($userFolder->nodeExists($target));
		$this->assertEquals('application/zip', $node->getMimetype());
		$this->assertEquals(671, $node->getSize());

		$path = $node->getStorage()->getLocalFile($node->getInternalPath());
		$zip = new \OC\Archive\ZIP($path);
		self::assertEquals(self::TEST_FILES, $zip->getFiles());

		$i = 0;
		foreach (self::TEST_FILES as $filename) {
			self::assertEquals($files[$i++]->getSize(), $zip->filesize($filename));
		}
	}

	private function loginAndGetUserFolder(string $userId) {
		$this->loginAsUser($userId);
		return $this->rootFolder->getUserFolder($userId);
	}

	private function shareFileWithUser(File $file, $owner, $user) {
		$this->shareManager = \OC::$server->getShareManager();
		$share1 = $this->shareManager->newShare();
		$share1->setNode($file)
			->setSharedBy($owner)
			->setSharedWith($user)
			->setShareType(IShare::TYPE_USER)
			->setPermissions(19);
		$share1 = $this->shareManager->createShare($share1);
		$share1->setStatus(IShare::STATUS_ACCEPTED);
		$this->shareManager->updateShare($share1);
	}

	public function tearDown(): void {
		parent::tearDown();
		$folder = $this->rootFolder->getUserFolder(self::TEST_USER1);
		foreach (self::TEST_FILES as $filename) {
			$folder->delete($filename);
		}
	}
}
