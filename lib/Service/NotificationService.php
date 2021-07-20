<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
