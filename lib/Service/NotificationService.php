<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\FilesZip\Service;

use DateTime;
use OCA\FilesZip\BackgroundJob\ZipJob;
use OCA\FilesZip\Notification\Notifier;
use OCP\Files\File;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

class NotificationService {
	/** @var IManager */
	private $notificationManager;

	public function __construct(IManager $notificationManager) {
		$this->notificationManager = $notificationManager;
	}

	public function sendNotificationOnPending($userId, $target): void {
		$notification = $this->buildScheduledNotification($userId, $target)
			->setDateTime(new DateTime());
		$this->notificationManager->notify($notification);
	}

	public function sendNotificationOnSuccess(ZipJob $job, File $file): void {
		$this->notificationManager->markProcessed($this->buildScheduledNotification($job->getUid(), $job->getTarget()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('target', md5($job->getTarget()))
			->setSubject(Notifier::TYPE_SUCCESS, ['fileid' => $file->getId(), 'name' => basename($job->getTarget()), 'path' => dirname($job->getTarget())]);
		$this->notificationManager->notify($notification);
	}

	public function sendNotificationOnFailure(ZipJob $job): void {
		$this->notificationManager->markProcessed($this->buildScheduledNotification($job->getUid(), $job->getTarget()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('job', (string)$job->getId())
			->setSubject(Notifier::TYPE_FAILURE, ['target' => $job->getTarget()]);
		$this->notificationManager->notify($notification);
	}

	private function buildScheduledNotification(string $uid, string $target): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($uid)
			->setApp('files_zip')
			->setObject('target', md5($target))
			->setSubject(Notifier::TYPE_SCHEDULED, [
				'directory' => dirname($target),
				'directory-name' => basename(dirname($target)),
				'target-name' => basename($target),
			]);
		return $notification;
	}
}
