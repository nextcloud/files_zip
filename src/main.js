import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

(function() {
	const FilesPlugin = {
		attach(fileList) {
			const self = this

			fileList.registerMultiSelectFileAction({
				name: 'files_zip',
				displayName: t('files_zip', 'Compress to zip'),
				iconClass: 'icon-zip',
				order: 0,
				action: (files) => {
					const selectedFiles = files.map(file => file.id)
					// noinspection JSVoidFunctionReturnValueUsed
					window.OC.dialogs.prompt(
						t('files_zip', 'Select a name for the zip archive'),
						n('files_zip', 'Compress {files} file', 'Compress {files} files', selectedFiles.length, { files: selectedFiles.length }),
						function(result, target) {
							if (result) {
								self.compressFiles(selectedFiles, target)
							}
						}, true, t('files_zip', 'File name')
					).then(self.enhancePrompt)
				},
			})
		},
		async compressFiles(fileIds, target) {
			try {
				await axios.post(generateOcsUrl('/apps/files_zip/api/v1/zip'), {
					fileIds,
					target,
				})
				showSuccess('File will be compressed and added to your files once the process has finished.')
			} catch (e) {
				showError('An error happened when trying to compress the file.')
			}
		},

		enhancePrompt() {
			const dialog = document.querySelector('.oc-dialog')
			const input = dialog.querySelector('input[type=text]')
			const buttons = dialog.querySelectorAll('button')

			const icon = dialog.querySelector('.ui-icon')
			icon.parentNode.removeChild(icon)

			buttons[0].innerText = t('files_zip', 'Cancel')
			buttons[1].innerText = t('files_zip', 'Compress files')
			input.value = 'file.zip'
		},
	}

	OC.Plugins.register('OCA.Files.FileList', FilesPlugin)
})()
