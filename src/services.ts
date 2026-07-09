/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { INode } from '@nextcloud/files'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import Modal from './Modal.vue'

const MAX_COMPRESS_SIZE = loadState('files_zip', 'max_compress_size', -1)

/**
 *
 * @param nodes the nodes to be compressed
 */
export function getArchivePath(nodes: INode[]) {
	const currentDirectory = nodes[0]?.path
	const currentDirectoryName = currentDirectory?.split('/').slice(-1).pop()

	return (currentDirectoryName ?? t('files_zip', 'Archive')) + '.zip'
}

/**
 *
 * @param fileIds the ids of the files to compress
 * @param target the path to the compressed file
 */
async function compressFiles(fileIds: number[], target: string) {
	try {
		await axios.post(generateOcsUrl('apps/files_zip/api/v1/zip'), {
			fileIds,
			target,
		})
		showSuccess(t('files_zip', 'Creating Zip archive started. We will notify you as soon as the archive is available.'))
	} catch (e) {
		showError(t('files_zip', 'An error happened when trying to compress the file.'))
	}
}

/**
 *
 * @param dir the name of the working directory
 * @param nodes the nodes to be compressed
 */
export async function action(dir: string, nodes: INode[]) {
	const fileIds: number[] = nodes.map((file) => file.fileid) as number[]
	const size = nodes.reduce((carry: number, file: INode) => (file?.size ?? 0) + carry, 0)

	if (MAX_COMPRESS_SIZE !== -1 && (size ?? 0) > MAX_COMPRESS_SIZE) {
		showError(t('files_zip', 'Only files up to {maxSize} can be compressed.', {
			maxSize: formatFileSize(MAX_COMPRESS_SIZE),
		}))
		return null
	}

	const target = await spawnDialog(Modal, { nodes })
	if (target === null) {
		return null
	}

	await compressFiles(fileIds, dir + '/' + target)

	// compressFiles will show a success or error message as needed, so null is
	// returned to prevent the file list from showing its own with less context.
	return null
}
