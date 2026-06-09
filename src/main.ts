/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { IFileAction } from '@nextcloud/files'

import { registerFileAction, Permission } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import ZipIcon from '@mdi/svg/svg/zip-box-outline.svg?raw'
import { action } from './services'

const fileAction: IFileAction = {
	id: 'files_zip',
	order: 60,
	iconSvgInline() {
		return ZipIcon
	},
	displayName() {
		return t('files_zip', 'Compress to Zip')
	},
	enabled({ nodes, view }) {
		if (view.id === 'trashbin') {
			return false
		}
		return nodes.filter((node) => (node.permissions & Permission.READ) !== 0).length > 0
	},
	async execBatch({ nodes, folder }) {
		const result = action(folder.dirname, nodes)
		return Promise.all(nodes.map(() => result))
	},
	async exec({ nodes, folder }): Promise<boolean|null> {
		const result = action(folder.dirname, nodes)
		return result
	},
}

registerFileAction(fileAction)
