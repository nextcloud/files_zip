<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Controller;

use OCA\FilesZip\AppInfo\Application;
use OCA\FilesZip\Exceptions\MaximumSizeReachedException;
use OCA\FilesZip\Service\ZipService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ZipController extends OCSController {
	/** @var ZipService */
	private $zipService;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(IRequest $request, ZipService $zipService, LoggerInterface $logger) {
		parent::__construct(Application::APP_NAME, $request);

		$this->zipService = $zipService;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int[] $fileIds The fileIds to zip up
	 * @param string $target The target location (relative to the users file root)
	 */
	public function zip(array $fileIds, string $target) {
		try {
			$this->zipService->createZipJob($fileIds, $target);
			return new DataResponse([]);
		} catch (MaximumSizeReachedException $e) {
			return new DataResponse('Failed to add zip job', Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Exception $e) {
			$this->logger->error('Failed to add zip job', ['exception' => $e]);
			return new DataResponse('Failed to add zip job', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
