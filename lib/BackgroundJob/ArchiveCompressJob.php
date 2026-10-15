<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\BackgroundJob;

use OCA\FilesZip\Service\ArchiveCompressionService;
use OCA\FilesZip\Service\NotificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

final class ArchiveCompressJob extends QueuedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private ArchiveCompressionService $compressionService,
		private NotificationService $notificationService,
		private LoggerInterface $logger,
		private ITempManager $tempManager,
	) {
		parent::__construct($timeFactory);
	}

	public function getUid(): string {
		return $this->argument['uid'];
	}

	/** @return list<int> */
	public function getFileIds(): array {
		return $this->argument['fileIds'];
	}

	public function getTarget(): string {
		return $this->argument['target'];
	}

	public function getFormat(): string {
		return $this->argument['format'];
	}

	protected function run($argument): void {
		try {
			$file = $this->compressionService->compress(
				$this->getUid(),
				$this->getFileIds(),
				$this->getTarget(),
				$this->getFormat(),
			);
			$this->notificationService->sendArchiveCompressionSuccess($this, $file);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to create archive', [
				'format' => $this->getFormat(),
				'exception' => $e,
			]);
			$this->notificationService->sendArchiveCompressionFailure($this);
		} finally {
			$this->tempManager->clean();
		}
	}
}
