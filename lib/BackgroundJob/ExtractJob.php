<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\BackgroundJob;

use OCA\FilesZip\Service\ArchiveExtractionService;
use OCA\FilesZip\Service\NotificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

final class ExtractJob extends QueuedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private ArchiveExtractionService $extractionService,
		private NotificationService $notificationService,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
	}

	public function getUid(): string {
		return $this->argument['uid'];
	}

	public function getFileId(): int {
		return (int)$this->argument['fileId'];
	}

	public function getTarget(): string {
		return $this->argument['target'];
	}

	protected function run($argument): void {
		try {
			$folder = $this->extractionService->extract($this->getUid(), $this->getFileId(), $this->getTarget());
			$this->notificationService->sendExtractionSuccess($this, $folder);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to extract archive', [
				'exception' => $e,
				'fileId' => $this->getFileId(),
				'target' => $this->getTarget(),
			]);
			$this->notificationService->sendExtractionFailure($this);
		}
	}
}
