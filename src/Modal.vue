<!--
 - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showDialog"
		:name="t('files_zip', 'Compress files')"
		:can-close="true"
		content-classes="zip-dialog"
		@closing="handleClosing">
		<template #actions>
			<NcButton variant="primary" @click="saveFile">
				{{ t('files_zip', 'Compress') }}
			</NcButton>
		</template>
		<div class="zip-dialog">
			<p>{{ n('files_zip', 'Compress %n file', 'Compress %n files', nodes.length) }}</p>
			<p>{{ t('files_zip', 'The file will be compressed in the background. Once finished you will receive a notification and the file is located in the current directory.') }}</p>
			<NcTextField ref="filenameInput"
				v-model="filename"
				:label="t('files_zip', 'Archive file name')" />
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import type { INode } from '@nextcloud/files'

import { n, t } from '@nextcloud/l10n'
import { NcButton, NcDialog, NcTextField } from '@nextcloud/vue'
import { onMounted, ref, useTemplateRef } from 'vue'
import { getArchivePath } from './services.ts'

const props = defineProps<{
	nodes: INode[]
}>()

const emit = defineEmits<{
	close: [value: string | null]
}>()

const showDialog = ref(true)
const filename = ref(getArchivePath(props.nodes))
const filenameInput = useTemplateRef('filenameInput')

onMounted(() => {
	const input = filenameInput.value?.$refs?.inputField?.$refs?.input
	if (input) {
		input.setSelectionRange(0, filename.value.lastIndexOf('.'))
		input.focus()
	}
})

/**
 *
 */
function saveFile(): void {
	showDialog.value = false
	emit('close', filename.value)
}

/**
 *
 */
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
</style>
