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

namespace OCA\FilesZip\Notification;

use InvalidArgumentException;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

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
			throw new InvalidArgumentException('Application should be files_zip instead of ' . $notification->getApp());
		}

		$l = $this->factory->get('files_zip', $languageCode);

		switch ($notification->getSubject()) {
			case self::TYPE_SCHEDULED:
				$parameters = $notification->getSubjectParameters();
				$notification->setRichSubject($l->t('A zip archive {target} will be created.'), [
					'target' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => $parameters['target-name'],
					]
				]);
				break;
			case self::TYPE_SUCCESS:
				$parameters = $notification->getSubjectParameters();
				$notification->setRichSubject($l->t('Your files have been stored as a zip archive in {path}.'), [
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
				$notification->setRichSubject($l->t('Creating the zip file {path} failed.'), [
					'path' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => basename($parameters['target']),
					]
				]);
				break;
			default:
				throw new InvalidArgumentException();
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
