<template>     
	<TransitionRoot appear :show="show" as="template">
		<Dialog as="div" class="mf-crm relative z-[100]" @close="close">
			<TransitionChild
				as="template"
				enter="duration-300 ease-out"
				enter-from="opacity-0"
				enter-to="opacity-100"
				leave="duration-200 ease-in"
				leave-from="opacity-100"
				leave-to="opacity-0"
			>
				<div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" />
			</TransitionChild>
				
			<div class="fixed inset-0 overflow-y-auto">
				<div class="flex min-h-full items-center justify-center p-4">
					<TransitionChild
						as="template"
						enter="duration-300 ease-out"
						enter-from="opacity-0 scale-95"
						enter-to="opacity-100 scale-100"
						leave="duration-200 ease-in"
						leave-from="opacity-100 scale-100"
						leave-to="opacity-0 scale-95"
					>
						<DialogPanel class="bg-white rounded-xl shadow-2xl w-[420px] overflow-hidden border border-slate-200 transition-all duration-300">
							<!-- Header -->
							<div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
								<DialogTitle class="font-bold text-lg text-slate-800">
									Добавить новый этап
								</DialogTitle>
								<Button @click="close" class="text-slate-400 hover:text-slate-700 text-sm">ESC</Button>
							</div>

							<!-- Body -->
							<div class="p-6">
								<Badge
									v-if="contextText"
									unstyled
									class="block w-full text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 mb-4"
								>
									{{ contextText }}
								</Badge>
								<label class="block text-sm font-semibold text-slate-700 mb-2">
									Название этапа
								</label>
								<Input
									ref="nameInput"
									v-model="stageName"
									placeholder="Например: На согласовании"
									class="w-full"
									@keyup.enter="submit"
								/>
							</div>

							<!-- Footer -->
							<div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-3">
								<Button @click="close">Отмена</Button>
								<Button
									color="primary"
									class="flex items-center justify-center gap-2"
									@click="submit"
									:disabled="!stageName.trim() || isSubmitting"
								>
									<Spinner v-if="isSubmitting" class="size-4 border-2" color="white"/>
									{{ isSubmitting ? 'Добавление...' : 'Добавить этап' }}
								</Button>
							</div>
						</DialogPanel>
					</TransitionChild>
				</div>
			</div>
		</Dialog>
	</TransitionRoot>
</template>

<script>
import Button from 'io7/components/tailux/ui/Button.vue';
import Spinner from 'io7/components/tailux/loading/Spinner.vue';
import Badge from 'io7/components/tailux/ui/Badge.vue';
import Input from 'io7/components/tailux/ui/form/Input.vue';
import {
	TransitionRoot,
	TransitionChild,
	Dialog,
	DialogPanel,
	DialogTitle,
} from '@headlessui/vue';

export default {
	name: 'NewStageModal',
	components: { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle, Button, Input, Badge, Spinner },
	
	emits: ['create'],

	data() {
		return {
			show: false,
			stageName: '',
			contextText: '',
			// позиция вставки нового этапа в массиве этапов (null = в конец)
			insertIndex: null,
			isSubmitting: false,
		};
	},

	methods: {
		open(insertIndex = null, contextText = '') {
			this.stageName = '';
			this.insertIndex = insertIndex;
			this.contextText = contextText;
			this.isSubmitting = false;
			this.show = true;
			this.$nextTick(() => this.$refs.nameInput?.focus());
		},

		close() {
			if (this.isSubmitting) return;
			this.show = false;
		},

		submit() {
			if (this.isSubmitting) return;
			const name = this.stageName.trim();
			if (!name) {
			this.$noty.warning('Введите название этапа', { layout: 'bottomRight', timeout: 3000 });
			return;
		}

			this.isSubmitting = true;
			this.$emit('create', {
			label: name,
			value: name,
			stageColor: 'bg-gray-50 text-gray-600 border-gray-200',
			color: '#6b7280',
			insertIndex: this.insertIndex,
			}, {
				done: () => { this.isSubmitting = false; },
				finish: () => { this.isSubmitting = false; this.close(); },
			});
		},
	},
};
</script>
