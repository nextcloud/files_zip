<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip\Controller;

use OCA\FilesZip\Exceptions\ArchiveLimitExceededException;
use OCA\FilesZip\Exceptions\TargetAlreadyExists;
use OCA\FilesZip\Exceptions\UnsafeArchiveEntryException;
use OCA\FilesZip\Exceptions\UnsupportedArchiveFormatException;
use OCA\FilesZip\Service\ArchiveCompressionService;
use OCA\FilesZip\Service\ArchiveExtractionService;
use OCA\FilesZip\Service\NotificationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

final class ArchiveController extends OCSController {
	public function __construct(
		IRequest $request,
		private ArchiveExtractionService $extractionService,
		private ArchiveCompressionService $compressionService,
		private NotificationService $notificationService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct('files_zip', $request);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param list<int> $fileIds
	 */
	public function compress(array $fileIds, string $target, string $format): DataResponse {
		try {
			$this->compressionService->createJob($fileIds, $target, $format);
			return new DataResponse([]);
		} catch (TargetAlreadyExists $e) {
			return new DataResponse('Archive target already exists', Http::STATUS_CONFLICT);
		} catch (UnsupportedArchiveFormatException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_UNSUPPORTED_MEDIA_TYPE);
		} catch (ArchiveLimitExceededException $e) {
			return new DataResponse('Archive exceeds the configured limits', Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to schedule archive compression', ['exception' => $e]);
			return new DataResponse('Failed to schedule archive compression', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function extract(int $fileId, string $target): DataResponse {
		try {
			$this->extractionService->createExtractJob($fileId, $target);

			$user = $this->userSession->getUser();
			if ($user !== null) {
				try {
					$this->notificationService->sendExtractionPending($user->getUID(), $target);
				} catch (\Throwable $notificationError) {
					$this->logger->warning('Extraction was queued but the pending notification failed', [
						'exception' => $notificationError,
					]);
				}
			}

			return new DataResponse([]);
		} catch (UnsafeArchiveEntryException $e) {
			return new DataResponse('Unsafe archive path', Http::STATUS_BAD_REQUEST);
		} catch (UnsupportedArchiveFormatException $e) {
			return new DataResponse('Unsupported archive format', Http::STATUS_UNSUPPORTED_MEDIA_TYPE);
		} catch (ArchiveLimitExceededException $e) {
			return new DataResponse('Archive exceeds the configured limits', Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to schedule archive extraction', ['exception' => $e]);
			return new DataResponse('Failed to schedule archive extraction', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
