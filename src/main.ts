/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerFileAction, FileAction, Node, Permission, View } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import ZipIcon from '@mdi/svg/svg/zip-box-outline.svg?raw'
import { action } from './services'

const fileAction = new FileAction({
	id: 'files_zip',
	order: 60,
	iconSvgInline() {
		return ZipIcon
	},
	displayName() {
		return t('files_zip', 'Compress to Zip')
	},
	enabled(nodes: Node[]) {
		return nodes.filter((node) => (node.permissions & Permission.READ) !== 0).length > 0
	},
	async execBatch(nodes: Node[], view: View, dir: string) {
		const result = action(dir, nodes)
		return Promise.all(nodes.map(() => result))
	},
	async exec(node: Node, view: View, dir: string): Promise<boolean|null> {
		const result = action(dir, [node])
		return result
	},
})

registerFileAction(fileAction)
