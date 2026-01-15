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
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ZipController extends OCSController {

	public function __construct(
		IRequest $request,
		private ZipService $zipService,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
		parent::__construct(Application::APP_NAME, $request);
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

	/**
	 * @NoAdminRequired
	 *
	 * Zips a specific file path.
	 *
	 * This endpoint follows the Client Integration specification.
	 *
	 * @param string $filePath The path to the file to zip up
	 */
	public function zipPath(string $filePath) {
		try {
			$this->zipService->createZipJobForPath($filePath);

			$tooltip = $this->l10n->t('A Zip archive will be created');
			$statusCode = Http::STATUS_OK;
		} catch (MaximumSizeReachedException $e) {
			$tooltip = $this->l10n->t('The file is larger than the configured limit and it could not be compressed');
			$statusCode = Http::STATUS_REQUEST_ENTITY_TOO_LARGE;
		} catch (\Exception $e) {
			$this->logger->error('Failed to add zip job', ['exception' => $e]);

			$tooltip = $this->l10n->t('An error happened when trying to compress the file');
			$statusCode = Http::STATUS_INTERNAL_SERVER_ERROR;
		}

		$data = [
			'version' => 0.1,
			'tooltip' => $tooltip,
		];
		return new DataResponse($data, $statusCode);
	}
}
