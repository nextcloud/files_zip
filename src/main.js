import axios from '@nextcloud/axios'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

(function() {
	const MAX_COMPRESS_SIZE = loadState('files_zip', 'max_compress_size', -1)

	const FilesPlugin = {
		attach(fileList) {
			const displayName = t('files_zip', 'Compress to Zip')

			const actionHandler = (files) => {
				const sum = files.reduce((carry, file) => file.size + carry, 0)
				if (MAX_COMPRESS_SIZE !== -1 && sum > MAX_COMPRESS_SIZE) {
					showError(t('files_zip', 'Only files up to {maxSize} can be compressed.', {
						maxSize: formatFileSize(MAX_COMPRESS_SIZE),
					}))
					return
				}

				const parentFolderName = files.length === 1 ? (files[0].name ?? files[0].get('name')) : fileList.getCurrentDirectory().split('/').slice(-1).pop()
				const suggestedFilename = fileList.getUniqueName(
					(parentFolderName === '' ? t('files_zip', 'Archive') : parentFolderName) + '.zip'
				)

				const selectedFiles = files.map(file => file.id)
				// noinspection JSVoidFunctionReturnValueUsed
				window.OC.dialogs.prompt(
					t('files_zip', 'Select a name for the Zip archive'),
					n('files_zip', 'Compress {files} file', 'Compress {files} files', selectedFiles.length, { files: selectedFiles.length }),
					(result, target) => {
						if (!result) {
							return
						}
						if (target.length === 0) {
							showError(t('files_zip', 'The name selected is invalid.'))
							return
						}
						this.compressFiles(selectedFiles, fileList.getCurrentDirectory() + '/' + target)
					}, true, t('files_zip', 'File name')
				).then(this.enhancePrompt.bind(this, suggestedFilename))
			}

			fileList.fileActions.registerAction({
				name: 'files_zip2',
				displayName,
				mime: 'all',
				type: 0,
				permissions: OC.PERMISSION_READ,
				order: 0,
				iconClass: 'icon-zip',
				actionHandler(fileName, context) {
					actionHandler([context.fileInfoModel])
				},
			})

			fileList.registerMultiSelectFileAction({
				name: 'files_zip',
				displayName: t('files_zip', 'Compress to Zip'),
				iconClass: 'icon-zip',
				order: 0,
				action: actionHandler,
			})

			fileList.$el.on('urlChanged', data => {
				const canCreate = !!(fileList.dirInfo.permissions & OC.PERMISSION_CREATE)
				fileList.fileMultiSelectMenu.toggleItemVisibility('files_zip', canCreate)
			})
			fileList.$el.on('afterChangeDirectory', data => {
				const canCreate = !!(fileList.dirInfo.permissions & OC.PERMISSION_CREATE)
				fileList.fileMultiSelectMenu.toggleItemVisibility('files_zip', canCreate)
			})
		},
		async compressFiles(fileIds, target) {
			try {
				await axios.post(generateOcsUrl('apps/files_zip/api/v1/zip'), {
					fileIds,
					target,
				})
				showSuccess(t('files_zip', 'Creating Zip archive started. We will notify you as soon as the archive is available.'))
			} catch (e) {
				showError(t('files_zip', 'An error happened when trying to compress the file.'))
			}
		},

		enhancePrompt(suggestedFilename) {
			const dialog = document.querySelector('.oc-dialog')
			const input = dialog.querySelector('input[type=text]')
			const buttons = dialog.querySelectorAll('.oc-dialog-buttonrow button')

			const icon = dialog.querySelector('.ui-icon')
			icon.parentNode.removeChild(icon)

			buttons[0].innerText = t('files_zip', 'Cancel')
			buttons[1].innerText = t('files_zip', 'Compress files')
			input.value = suggestedFilename
		},
	}

	OC.Plugins.register('OCA.Files.FileList', FilesPlugin)
})()
