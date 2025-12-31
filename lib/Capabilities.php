<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\FilesZip;

use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {
	public function getCapabilities() {
		return [
			'files_zip' => [
				'apiVersion' => 'v1'
			]
		];
	}
}
