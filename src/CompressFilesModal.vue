<!--
 - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 - SPDX-FileCopyrightText: 2026 Happyfeet01
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog
		v-if="showDialog"
		:name="t('files_zip', 'Compress files')"
		contentClasses="zip-dialog"
		@closing="handleClosing">
		<template #actions>
			<NcButton variant="primary" @click="saveFile">
				{{ t('files_zip', 'Compress') }}
			</NcButton>
		</template>
		<div class="zip-dialog">
			<p>{{ n('files_zip', 'Compress %n file', 'Compress %n files', nodes.length) }}</p>
			<p>{{ t('files_zip', 'The archive will be created in the background. Once finished you will receive a notification and the file is located in the current directory.') }}</p>

			<label class="format-label" for="files-zip-format">
				{{ t('files_zip', 'Archive format') }}
			</label>
			<select id="files-zip-format" v-model="format" class="format-select">
				<option v-for="option in formatOptions" :key="option.value" :value="option.value">
					{{ option.label }}
				</option>
			</select>

			<NcTextField
				ref="filenameInput"
				v-model="filename"
				:label="t('files_zip', 'Archive file name')" />
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import type { INode } from '@nextcloud/files'
import type { ArchiveCompressionFormat, CompressionDialogResult } from './services.ts'

import { n, t } from '@nextcloud/l10n'
import { NcButton, NcDialog, NcTextField } from '@nextcloud/vue'
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { ARCHIVE_CAPABILITIES, getArchivePath, replaceArchiveExtension } from './services.ts'

const props = defineProps<{
	nodes: INode[]
}>()

const emit = defineEmits<{
	close: [value: CompressionDialogResult | null]
}>()

const showDialog = ref(true)
const format = ref<ArchiveCompressionFormat>('zip')
const filename = ref(getArchivePath(props.nodes, format.value))
const filenameInput = useTemplateRef('filenameInput')

const formatOptions = computed<Array<{ value: ArchiveCompressionFormat, label: string }>>(() => {
	const options: Array<{ value: ArchiveCompressionFormat, label: string }> = [
		{ value: 'zip', label: 'ZIP' },
	]
	if (ARCHIVE_CAPABILITIES.sevenZipAvailable) {
		options.push(
			{ value: 'tar', label: 'TAR' },
			{ value: 'tar.gz', label: 'TAR.GZ' },
			{ value: '7z', label: '7z' },
		)
	}
	return options
})

watch(format, (newFormat) => {
	filename.value = replaceArchiveExtension(filename.value, newFormat)
})

onMounted(() => {
	const input = filenameInput.value?.$refs?.inputField?.$refs?.input
	if (input) {
		input.setSelectionRange(0, filename.value.lastIndexOf('.'))
		input.focus()
	}
})

function saveFile(): void {
	showDialog.value = false
	emit('close', {
		filename: filename.value,
		format: format.value,
	})
}

function handleClosing() {
	emit('close', null)
}
</script>

<style lang="scss" scoped>
.zip-dialog {
	margin: 12px;
}

p {
	margin-bottom: 12px;
}

.format-label {
	display: block;
	font-weight: 600;
	margin-bottom: 4px;
}

.format-select {
	width: 100%;
	min-height: 44px;
	margin-bottom: 12px;
	padding: 0 12px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
}
</style>
