/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 Happyfeet01
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
import CompressFilesModal from './CompressFilesModal.vue'
import ExtractArchiveModal from './ExtractArchiveModal.vue'
import { logger } from './logger.ts'

const MAX_COMPRESS_SIZE = loadState('files_zip', 'max_compress_size', -1)

export interface ArchiveCapabilities {
	sevenZipAvailable: boolean
	sevenZipPath: string
	minimumSevenZipVersion: string
}

export const ARCHIVE_CAPABILITIES = loadState<ArchiveCapabilities>('files_zip', 'archive_capabilities', {
	sevenZipAvailable: false,
	sevenZipPath: '/usr/bin/7z',
	minimumSevenZipVersion: '25.01',
})

export type ArchiveCompressionFormat = 'zip' | 'tar' | 'tar.gz' | '7z'

export interface CompressionDialogResult {
	filename: string
	format: ArchiveCompressionFormat
}

export function archiveExtension(format: ArchiveCompressionFormat): string {
	return format === 'tar.gz' ? '.tar.gz' : `.${format}`
}

export function replaceArchiveExtension(filename: string, format: ArchiveCompressionFormat): string {
	const base = filename.replace(/(?:\.tar\.gz|\.tgz|\.zip|\.tar|\.7z)$/i, '') || t('files_zip', 'Archive')
	return base + archiveExtension(format)
}

export function getArchivePath(nodes: INode[], format: ArchiveCompressionFormat = 'zip') {
	const currentDirectory = nodes[0]?.path
	const currentDirectoryName = currentDirectory?.split('/').slice(-1).pop()

	return (currentDirectoryName ?? t('files_zip', 'Archive')) + archiveExtension(format)
}

export function getExtractTarget(node: INode): string {
	return node.basename
		.replace(/\.tar\.gz$/i, '')
		.replace(/\.tgz$/i, '')
		.replace(/\.(zip|tar|7z)$/i, '')
}

export function isExtractableArchive(node: INode): boolean {
	const name = node.basename.toLowerCase()
	return name.endsWith('.zip')
		|| name.endsWith('.tar')
		|| name.endsWith('.tar.gz')
		|| name.endsWith('.tgz')
		|| (ARCHIVE_CAPABILITIES.sevenZipAvailable && name.endsWith('.7z'))
}

async function compressFiles(fileIds: number[], target: string, format: ArchiveCompressionFormat) {
	try {
		if (format === 'zip') {
			await axios.post(generateOcsUrl('apps/files_zip/api/v1/zip'), {
				fileIds,
				target,
			})
		} else {
			await axios.post(generateOcsUrl('apps/files_zip/api/v1/archive'), {
				fileIds,
				target,
				format,
			})
		}
		showSuccess(t('files_zip', 'Creating {format} archive started. We will notify you as soon as the archive is available.', {
			format: format.toUpperCase(),
		}))
	} catch (error) {
		logger.error('Error when compressing the file', { error, format })
		showError(t('files_zip', 'An error happened when trying to create the archive.'))
	}
}

async function extractArchive(fileId: number, target: string) {
	try {
		await axios.post(generateOcsUrl('apps/files_zip/api/v1/extract'), {
			fileId,
			target,
		})
		showSuccess(t('files_zip', 'Archive extraction started. Unsafe entries will be rejected.'))
	} catch (error) {
		logger.error('Error when scheduling archive extraction', { error })
		showError(t('files_zip', 'The archive could not be scheduled for extraction.'))
	}
}

export async function action(dir: string, nodes: INode[]) {
	const fileIds: number[] = nodes.map((file) => file.fileid) as number[]
	const size = nodes.reduce((carry: number, file: INode) => (file?.size ?? 0) + carry, 0)

	if (MAX_COMPRESS_SIZE !== -1 && (size ?? 0) > MAX_COMPRESS_SIZE) {
		showError(t('files_zip', 'Only files up to {maxSize} can be compressed.', {
			maxSize: formatFileSize(MAX_COMPRESS_SIZE),
		}))
		return null
	}

	const result = await spawnDialog(CompressFilesModal, { nodes }) as CompressionDialogResult | null
	if (result === null) {
		return null
	}

	await compressFiles(fileIds, dir + '/' + result.filename, result.format)
	return null
}

export async function extractAction(dir: string, node: INode) {
	const target = await spawnDialog(ExtractArchiveModal, { node })
	if (target === null) {
		return null
	}

	await extractArchive(node.fileid as number, dir + '/' + target)
	return null
}
