<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\FilesZip\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesZip\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadAdditionalListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}

		Util::addScript(Application::APP_NAME, 'files_zip-main');
		Util::addStyle(Application::APP_NAME, 'files_zip-main');
	}
}
