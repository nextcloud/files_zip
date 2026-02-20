<?php
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\FilesZip\Notification;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public const TYPE_SCHEDULED = 'zip_scheduled';
	public const TYPE_SUCCESS = 'zip_success';
	public const TYPE_FAILURE = 'zip_error';

	/** @var IFactory */
	protected $factory;
	/** @var IURLGenerator */
	protected $url;

	public function __construct(IFactory $factory, IURLGenerator $urlGenerator) {
		$this->factory = $factory;
		$this->url = $urlGenerator;
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

		switch ($notification->getSubject()) {
			case self::TYPE_SCHEDULED:
				$parameters = $notification->getSubjectParameters();
				$notification->setRichSubject($l->t('A Zip archive {target} will be created.'), [
					'target' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => $parameters['target-name'],
					]
				]);
				break;
			case self::TYPE_SUCCESS:
				$parameters = $notification->getSubjectParameters();
				$notification->setRichSubject($l->t('Your files have been stored as a Zip archive in {path}.'), [
					'path' => [
						'type' => 'file',
						'id' => $parameters['fileid'],
						'name' => $parameters['name'],
						'path' => $parameters['path']
					]
				]);
				break;
			case self::TYPE_FAILURE:
				$parameters = $notification->getSubjectParameters();
				$notification->setRichSubject($l->t('Creating the Zip file {path} failed.'), [
					'path' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => basename($parameters['target']),
					]
				]);
				break;
			default:
				throw new UnknownNotificationException();
		}
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files_zip', 'files_zip-dark.svg')));
		$this->setParsedSubjectFromRichSubject($notification);
		return $notification;
	}

	protected function setParsedSubjectFromRichSubject(INotification $notification): void {
		$placeholders = $replacements = [];
		foreach ($notification->getRichSubjectParameters() as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if ($parameter['type'] === 'file') {
				$replacements[] = $parameter['path'];
			} else {
				$replacements[] = $parameter['name'];
			}
		}

		$notification->setParsedSubject(str_replace($placeholders, $replacements, $notification->getRichSubject()));
	}
}
