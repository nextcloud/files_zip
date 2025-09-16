<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
return [
	'ocs' => [
		[
			'name' => 'Zip#zip',
			'url' => '/api/v1/zip',
			'verb' => 'POST',
		],
		[
			'name' => 'Zip#zipPath',
			'url' => '/api/v1/zip-path',
			'verb' => 'POST',
		],
	],
];
