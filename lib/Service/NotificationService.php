<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Service;

use DateTime;
use OCA\FilesZip\BackgroundJob\ArchiveCompressJob;
use OCA\FilesZip\BackgroundJob\ExtractJob;
use OCA\FilesZip\BackgroundJob\ZipJob;
use OCA\FilesZip\Notification\Notifier;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

final class NotificationService {
	public function __construct(
		private IManager $notificationManager,
	) {
	}

	public function sendNotificationOnPending(string $userId, string $target): void {
		$notification = $this->buildZipScheduledNotification($userId, $target)
			->setDateTime(new DateTime());
		$this->notificationManager->notify($notification);
	}

	public function sendNotificationOnSuccess(ZipJob $job, File $file): void {
		$this->notificationManager->markProcessed($this->buildZipScheduledNotification($job->getUid(), $job->getTarget()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('target', md5($job->getTarget()))
			->setSubject(Notifier::TYPE_SUCCESS, [
				'fileid' => (string)$file->getId(),
				'name' => basename($job->getTarget()),
				'path' => dirname($job->getTarget()),
			]);
		$this->notificationManager->notify($notification);
	}

	public function sendNotificationOnFailure(ZipJob $job): void {
		$this->notificationManager->markProcessed($this->buildZipScheduledNotification($job->getUid(), $job->getTarget()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('job', (string)$job->getId())
			->setSubject(Notifier::TYPE_FAILURE, ['target' => $job->getTarget()]);
		$this->notificationManager->notify($notification);
	}

	public function sendArchiveCompressionPending(string $userId, string $target, string $format): void {
		$notification = $this->buildArchiveScheduledNotification($userId, $target, $format)
			->setDateTime(new DateTime());
		$this->notificationManager->notify($notification);
	}

	public function sendArchiveCompressionSuccess(ArchiveCompressJob $job, File $file): void {
		$this->notificationManager->markProcessed($this->buildArchiveScheduledNotification($job->getUid(), $job->getTarget(), $job->getFormat()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('archive_target', md5($job->getTarget()))
			->setSubject(Notifier::TYPE_ARCHIVE_SUCCESS, [
				'fileid' => (string)$file->getId(),
				'name' => basename($job->getTarget()),
				'path' => dirname($job->getTarget()),
				'format' => $job->getFormat(),
			]);
		$this->notificationManager->notify($notification);
	}

	public function sendArchiveCompressionFailure(ArchiveCompressJob $job): void {
		$this->notificationManager->markProcessed($this->buildArchiveScheduledNotification($job->getUid(), $job->getTarget(), $job->getFormat()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('archive_job', (string)$job->getId())
			->setSubject(Notifier::TYPE_ARCHIVE_FAILURE, [
				'target' => $job->getTarget(),
				'format' => $job->getFormat(),
			]);
		$this->notificationManager->notify($notification);
	}

	public function sendExtractionPending(string $userId, string $target): void {
		$notification = $this->buildExtractScheduledNotification($userId, $target)
			->setDateTime(new DateTime());
		$this->notificationManager->notify($notification);
	}

	public function sendExtractionSuccess(ExtractJob $job, Folder $folder): void {
		$this->notificationManager->markProcessed($this->buildExtractScheduledNotification($job->getUid(), $job->getTarget()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('extract_target', md5($job->getTarget()))
			->setSubject(Notifier::TYPE_EXTRACT_SUCCESS, [
				'fileid' => (string)$folder->getId(),
				'name' => basename($job->getTarget()),
				'path' => dirname($job->getTarget()),
			]);
		$this->notificationManager->notify($notification);
	}

	public function sendExtractionFailure(ExtractJob $job): void {
		$this->notificationManager->markProcessed($this->buildExtractScheduledNotification($job->getUid(), $job->getTarget()));
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($job->getUid())
			->setApp('files_zip')
			->setDateTime(new DateTime())
			->setObject('extract_job', (string)$job->getId())
			->setSubject(Notifier::TYPE_EXTRACT_FAILURE, ['target' => $job->getTarget()]);
		$this->notificationManager->notify($notification);
	}

	private function buildZipScheduledNotification(string $uid, string $target): INotification {
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

	private function buildArchiveScheduledNotification(string $uid, string $target, string $format): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($uid)
			->setApp('files_zip')
			->setObject('archive_target', md5($target))
			->setSubject(Notifier::TYPE_ARCHIVE_SCHEDULED, [
				'directory' => dirname($target),
				'directory-name' => basename(dirname($target)),
				'target-name' => basename($target),
				'format' => $format,
			]);
		return $notification;
	}

	private function buildExtractScheduledNotification(string $uid, string $target): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($uid)
			->setApp('files_zip')
			->setObject('extract_target', md5($target))
			->setSubject(Notifier::TYPE_EXTRACT_SCHEDULED, [
				'directory' => dirname($target),
				'directory-name' => basename(dirname($target)),
				'target-name' => basename($target),
			]);
		return $notification;
	}
}
