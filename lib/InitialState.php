<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\FilesZip;

use OCA\FilesZip\AppInfo\Application;
use OCP\AppFramework\Services\InitialStateProvider;
use OCP\IConfig;

class InitialState extends InitialStateProvider {
	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	public function getKey(): string {
		return 'max_compress_size';
	}

	public function getData() {
		return (int)$this->config->getAppValue(Application::APP_NAME, $this->getKey(), (string)-1);
	}
}
