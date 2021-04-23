<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesZip\Controller;

use OCA\FilesZip\AppInfo\Application;
use OCA\FilesZip\BackgroundJob\ZipJob;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use OCP\IUserSession;

class ZipController extends OCSController {

	/** @var IUserSession */
	private $userSession;

	/** @var IJobList */
	private $jobList;

	public function __construct(IRequest $request, IUserSession $userSession, IJobList $jobList) {
		parent::__construct(Application::APP_NAME, $request);

		$this->userSession = $userSession;
		$this->jobList = $jobList;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int[] $fileIds The fileIds to zip up
	 * @param string $target The target location (relative to the users file root)
	 */
	public function zip(array $fileIds, string $target) {
		$this->jobList->add(ZipJob::class, [
			'uid' => $this->userSession->getUser()->getUID(),
			'fileIds' => $fileIds,
			'target' => $target,
		]);

		return new DataResponse([]);
	}
}
