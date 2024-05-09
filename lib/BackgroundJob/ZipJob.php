<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\BackgroundJob;

use OCA\FilesZip\Service\NotificationService;
use OCA\FilesZip\Service\ZipService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

class ZipJob extends QueuedJob {
	/** @var ZipService */
	private $zipService;
	/** @var NotificationService */
	private $notificationService;
	/** @var LoggerInterface */
	private $logger;
	/** @var ITempManager */
	private $tempManager;

	public function __construct(ITimeFactory $timeFactory, ZipService $zipService, NotificationService $notificationService, LoggerInterface $logger, ITempManager $tempManager) {
		parent::__construct($timeFactory);
		$this->zipService = $zipService;
		$this->notificationService = $notificationService;
		$this->logger = $logger;
		$this->tempManager = $tempManager;
	}

	public function getUid(): string {
		return $this->argument['uid'];
	}

	public function getFileIds(): array {
		return $this->argument['fileIds'];
	}

	public function getTarget(): string {
		return $this->argument['target'];
	}

	protected function run($argument) {
		try {
			$file = $this->zipService->zip($this->getUid(), $this->getFileIds(), $this->getTarget());
			$this->notificationService->sendNotificationOnSuccess($this, $file);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to create zip archive', ['exception' => $e]);
			$this->notificationService->sendNotificationOnFailure($this);
		} finally {
			$this->tempManager->clean();
		}
	}
}
