<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Notification;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

final class Notifier implements INotifier {
	public const TYPE_SCHEDULED = 'zip_scheduled';
	public const TYPE_SUCCESS = 'zip_success';
	public const TYPE_FAILURE = 'zip_error';
	public const TYPE_ARCHIVE_SCHEDULED = 'archive_scheduled';
	public const TYPE_ARCHIVE_SUCCESS = 'archive_success';
	public const TYPE_ARCHIVE_FAILURE = 'archive_error';
	public const TYPE_EXTRACT_SCHEDULED = 'extract_scheduled';
	public const TYPE_EXTRACT_SUCCESS = 'extract_success';
	public const TYPE_EXTRACT_FAILURE = 'extract_error';

	public function __construct(
		private IFactory $factory,
		private IURLGenerator $url,
	) {
	}

	public function getID(): string {
		return 'files_zip';
	}

	public function getName(): string {
		return $this->factory->get('files_zip')->t('Zipper');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'files_zip') {
			throw new UnknownNotificationException('Application should be files_zip instead of ' . $notification->getApp());
		}

		$l = $this->factory->get('files_zip', $languageCode);
		$parameters = $notification->getSubjectParameters();

		switch ($notification->getSubject()) {
			case self::TYPE_SCHEDULED:
				$notification->setRichSubject($l->t('A Zip archive {target} will be created.'), [
					'target' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => $parameters['target-name'],
					],
				]);
				break;
			case self::TYPE_SUCCESS:
				$notification->setRichSubject($l->t('Your files have been stored as a Zip archive in {path}.'), [
					'path' => [
						'type' => 'file',
						'id' => $parameters['fileid'],
						'name' => $parameters['name'],
						'path' => $parameters['path'],
					],
				]);
				break;
			case self::TYPE_FAILURE:
				$notification->setRichSubject($l->t('Creating the Zip file {path} failed.'), [
					'path' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => basename($parameters['target']),
					],
				]);
				break;
			case self::TYPE_ARCHIVE_SCHEDULED:
				$notification->setRichSubject($l->t('A {format} archive {target} will be created.'), [
					'format' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId() . '-format',
						'name' => strtoupper($parameters['format']),
					],
					'target' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => $parameters['target-name'],
					],
				]);
				break;
			case self::TYPE_ARCHIVE_SUCCESS:
				$notification->setRichSubject($l->t('Your files have been stored as a {format} archive in {path}.'), [
					'format' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId() . '-format',
						'name' => strtoupper($parameters['format']),
					],
					'path' => [
						'type' => 'file',
						'id' => $parameters['fileid'],
						'name' => $parameters['name'],
						'path' => $parameters['path'],
					],
				]);
				break;
			case self::TYPE_ARCHIVE_FAILURE:
				$notification->setRichSubject($l->t('Creating the {format} archive {path} failed.'), [
					'format' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId() . '-format',
						'name' => strtoupper($parameters['format']),
					],
					'path' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => basename($parameters['target']),
					],
				]);
				break;
			case self::TYPE_EXTRACT_SCHEDULED:
				$notification->setRichSubject($l->t('The archive will be extracted to {target}.'), [
					'target' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => $parameters['target-name'],
					],
				]);
				break;
			case self::TYPE_EXTRACT_SUCCESS:
				$notification->setRichSubject($l->t('The archive was extracted to {path}.'), [
					'path' => [
						'type' => 'file',
						'id' => $parameters['fileid'],
						'name' => $parameters['name'],
						'path' => $parameters['path'],
					],
				]);
				break;
			case self::TYPE_EXTRACT_FAILURE:
				$notification->setRichSubject($l->t('Extracting the archive to {path} failed.'), [
					'path' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => basename($parameters['target']),
					],
				]);
				break;
			default:
				throw new UnknownNotificationException();
		}

		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files_zip', 'files_zip-dark.svg')));
		$this->setParsedSubjectFromRichSubject($notification);
		return $notification;
	}

	private function setParsedSubjectFromRichSubject(INotification $notification): void {
		$placeholders = [];
		$replacements = [];
		foreach ($notification->getRichSubjectParameters() as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['type'] === 'file' ? $parameter['path'] : $parameter['name'];
		}

		$notification->setParsedSubject(str_replace($placeholders, $replacements, $notification->getRichSubject()));
	}
}
