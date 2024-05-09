<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesZip\Capabilities;
use OCA\FilesZip\InitialState;
use OCA\FilesZip\Listener\LoadAdditionalListener;
use OCA\FilesZip\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_NAME = 'files_zip';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_NAME, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);
		$context->registerCapability(Capabilities::class);
		$context->registerNotifierService(Notifier::class);
		$context->registerInitialStateProvider(InitialState::class);
	}

	public function boot(IBootContext $context): void {
	}
}
