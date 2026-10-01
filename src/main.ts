/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Happyfeet01
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { IFileAction } from '@nextcloud/files'

import ZipIcon from '@mdi/svg/svg/zip-box-outline.svg?raw'
import { Permission, registerFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { action, extractAction, isExtractableArchive } from './services.ts'

const compressAction: IFileAction = {
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
		const result = action(folder.path, nodes)
		return Promise.all(nodes.map(() => result))
	},
	async exec({ nodes, folder }): Promise<boolean | null> {
		return action(folder.path, nodes)
	},
}

const extractFileAction: IFileAction = {
	id: 'files_zip_extract',
	order: 61,
	iconSvgInline() {
		return ZipIcon
	},
	displayName() {
		return t('files_zip', 'Extract archive')
	},
	enabled({ nodes, view }) {
		if (view.id === 'trashbin' || nodes.length !== 1) {
			return false
		}
		const node = nodes[0]
		return (node.permissions & Permission.READ) !== 0 && isExtractableArchive(node)
	},
	async execBatch({ nodes, folder }) {
		if (nodes.length !== 1) {
			return Promise.all(nodes.map(async () => false))
		}
		const result = extractAction(folder.path, nodes[0])
		return Promise.all(nodes.map(() => result))
	},
	async exec({ nodes, folder }): Promise<boolean | null> {
		if (nodes.length !== 1) {
			return false
		}
		return extractAction(folder.path, nodes[0])
	},
}

registerFileAction(compressAction)
registerFileAction(extractFileAction)
