<?php
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\FilesZip;

use OCA\FilesZip\AppInfo\Application;
use OCP\Capabilities\ICapability;
use OCP\IL10N;
use OCP\IURLGenerator;

class Capabilities implements ICapability {

	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
	) {
	}

	public function getCapabilities() {
		return [
			'files_zip' => [
				'apiVersion' => 'v1'
			],
			'client_integration' => [
				'files_zip' => [
					'version' => 0.1,
					'context-menu' => [
						[
							'name' => $this->l10n->t('Compress to Zip'),
							'url' => $this->urlGenerator->getWebroot() . '/ocs/v2.php/apps/files_zip/api/v1/zip-path',
							'method' => 'POST',
							'params' => [
								'filePath' => '{filePath}',
							],
							'icon' => $this->urlGenerator->imagePath(Application::APP_NAME, 'files_zip.svg'),
						],
					],
				],
			],
		];
	}
}
