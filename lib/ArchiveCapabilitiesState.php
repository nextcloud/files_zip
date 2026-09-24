<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesZip;

use OCA\FilesZip\Service\SevenZipBinary;
use OCP\AppFramework\Services\InitialStateProvider;

final class ArchiveCapabilitiesState extends InitialStateProvider {
	public function __construct(
		private SevenZipBinary $sevenZip,
	) {
	}

	public function getKey(): string {
		return 'archive_capabilities';
	}

	/** @return array{sevenZipAvailable: bool, sevenZipPath: string, minimumSevenZipVersion: string} */
	public function getData(): array {
		return [
			'sevenZipAvailable' => $this->sevenZip->isAvailable(),
			'sevenZipPath' => SevenZipBinary::PATH,
			'minimumSevenZipVersion' => SevenZipBinary::MINIMUM_VERSION,
		];
	}
}
