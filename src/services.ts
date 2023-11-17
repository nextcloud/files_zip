import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import type { Node } from '@nextcloud/files'
import { formatFileSize } from '@nextcloud/files'
import { generateOcsUrl } from '@nextcloud/router'
import Vue from 'vue'
import Modal from './Modal.vue'
import { loadState } from '@nextcloud/initial-state'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

Vue.prototype.t = t
Vue.prototype.n = n

const MAX_COMPRESS_SIZE = loadState('files_zip', 'max_compress_size', -1)

const askForName = async (nodes: Node[]) => {
	const modal = document.createElement('div')
	modal.id = 'files_zip_modal'
	document.body.appendChild(modal)
	const App = Vue.extend(Modal)
	const modalInstance = new App({
		propsData: {
			nodes,
		},
	})
	modalInstance.$mount('#files_zip_modal')

	const promise = new Promise<string|null>((resolve) => {
		modalInstance.$on('confirm', (result: string) => {
			modalInstance.$destroy()
			resolve(result)
		})
		modalInstance.$on('closing', () => {
			modalInstance.$destroy()
			resolve(null)
		})
	})

	return promise
}

export const getArchivePath = (nodes: Node[]) => {
	const currentDirectory = nodes[0].path
	const currentDirectoryName = currentDirectory.split('/').slice(-1).pop()

	return (currentDirectoryName === '' ? t('files_zip', 'Archive') : currentDirectoryName) + '.zip'
}

const compressFiles = async (fileIds: number[], target: string) => {
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

export const action = async (dir: string, nodes: Node[]) => {
	const fileIds: number[] = nodes.map(file => file.fileid) as number[]
	const size = nodes.reduce((carry: number, file: Node) => (file?.size ?? 0) + carry, 0)

	if (MAX_COMPRESS_SIZE !== -1 && (size ?? 0) > MAX_COMPRESS_SIZE) {
		showError(t('files_zip', 'Only files up to {maxSize} can be compressed.', {
			maxSize: formatFileSize(MAX_COMPRESS_SIZE),
		}))
		return null
	}

	const target = await askForName(nodes)
	if (target === null) {
		return null
	}

	await compressFiles(fileIds, dir + '/' + target)

	return true
}
