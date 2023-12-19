<template>
	<NcDialog v-if="showDialog"
		:name="t('files_zip', 'Compress files')"
		:can-close="true"
		content-classes="zip-dialog"
		@closing="$emit('closing')">
		<template #actions>
			<NcButton type="primary" @click="saveFile">
				{{ t('files_zip', 'Compress') }}
			</NcButton>
		</template>
		<div class="zip-dialog">
			<p>{{ n('files_zip', 'Compress %n file', 'Compress %n files', nodes.length) }}</p>
			<p>{{ t('files_zip', 'The file will be compressed in the background. Once finished you will receive a notification and the file is located in the current directory.') }}</p>
			<NcTextField ref="filenameInput"
				:value.sync="filename"
				:label="t('files_zip', 'Archive file name')" />
		</div>
	</NcDialog>
</template>
<script>
import { NcButton, NcDialog, NcTextField } from '@nextcloud/vue'
import { getArchivePath } from './services.ts'
export default {
	components: {
		NcButton,
		NcDialog,
		NcTextField,
	},
	props: {
		nodes: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			showDialog: true,
			filename: getArchivePath(this.nodes),
		}
	},
	mounted() {
		this.$nextTick(() => {
			const input = this.$refs?.filenameInput?.$refs?.inputField?.$refs?.input
			input.setSelectionRange(0, this.filename.lastIndexOf('.'))
			input.focus()
		})
	},
	methods: {
		saveFile() {
			this.showDialog = false
			this.$emit('confirm', this.filename)
		},
	},
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
