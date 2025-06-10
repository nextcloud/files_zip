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
			<NcButton type="primary" @click="saveFile">
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
import { ref, onMounted, useTemplateRef } from 'vue'
import { NcButton, NcDialog, NcTextField } from '@nextcloud/vue'
import type { Node } from '@nextcloud/files'
import { getArchivePath } from './services'
import { t, n } from '@nextcloud/l10n'

const props = defineProps<{
	nodes: Node[]
}>()

const emit = defineEmits<{
	close: [value: string|null]
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

const handleClosing = () => {
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
