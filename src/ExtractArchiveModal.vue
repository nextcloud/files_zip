<!--
 - SPDX-FileCopyrightText: 2026 Happyfeet01
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog
		v-if="showDialog"
		:name="t('files_zip', 'Extract archive')"
		contentClasses="extract-dialog"
		@closing="handleClosing">
		<template #actions>
			<NcButton variant="primary" @click="extract">
				{{ t('files_zip', 'Extract') }}
			</NcButton>
		</template>
		<div class="extract-dialog">
			<p>{{ t('files_zip', 'The archive will be checked and extracted in the background.') }}</p>
			<p>{{ t('files_zip', 'Existing files are never overwritten. Unsafe archive entries are rejected.') }}</p>
			<NcTextField
				ref="targetInput"
				v-model="target"
				:label="t('files_zip', 'New folder name')" />
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import type { INode } from '@nextcloud/files'

import { t } from '@nextcloud/l10n'
import { NcButton, NcDialog, NcTextField } from '@nextcloud/vue'
import { onMounted, ref, useTemplateRef } from 'vue'
import { getExtractTarget } from './services.ts'

const props = defineProps<{
	node: INode
}>()

const emit = defineEmits<{
	close: [value: string | null]
}>()

const showDialog = ref(true)
const target = ref(getExtractTarget(props.node))
const targetInput = useTemplateRef('targetInput')

onMounted(() => {
	const input = targetInput.value?.$refs?.inputField?.$refs?.input
	input?.focus()
})

function extract(): void {
	if (target.value.trim() === '') {
		return
	}
	showDialog.value = false
	emit('close', target.value.trim())
}

function handleClosing() {
	emit('close', null)
}
</script>

<style lang="scss" scoped>
.extract-dialog {
	margin: 12px;
}

p {
	margin-bottom: 12px;
}
</style>
