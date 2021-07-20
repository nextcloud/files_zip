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

namespace OCA\FilesZip\BackgroundJob;

use OCA\FilesZip\Service\NotificationService;
use OCA\FilesZip\Service\ZipService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class ZipJob extends QueuedJob {

	/** @var ZipService */
	private $zipService;
	/** @var NotificationService */
	private $notificationService;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(ITimeFactory $timeFactory, ZipService $zipService, NotificationService $notificationService, LoggerInterface $logger) {
		parent::__construct($timeFactory);
		$this->zipService = $zipService;
		$this->notificationService = $notificationService;
		$this->logger = $logger;
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
		}
	}
}
