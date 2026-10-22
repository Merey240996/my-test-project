<template>
	<TransitionRoot appear :show="isOpen" as="template">
		<Dialog as="div" class="relative z-[100]" @close="close">
			<TransitionChild
				as="template"
				enter="ease-out duration-200"
				enter-from="opacity-0"
				enter-to="opacity-100"
				leave="ease-in duration-150"
				leave-from="opacity-100"
				leave-to="opacity-0"
			>
				<div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" />
			</TransitionChild>

			<div class="fixed inset-0 overflow-y-auto">
				<div class="flex min-h-full items-center justify-center p-4">
					<TransitionChild
						as="template"
						enter="ease-out duration-200"
						enter-from="opacity-0 scale-95"
						enter-to="opacity-100 scale-100"
						leave="ease-in duration-150"
						leave-from="opacity-100 scale-100"
						leave-to="opacity-0 scale-95"
					>
						<DialogPanel
							:style="{ height: '450px' }"
							class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl"
						>
							<div class="flex items-center justify-between border-b px-5 py-4">
								<h2 class="text-base font-bold text-gray-800">
									Источники лидов
								</h2>

								<Button
									variant="flat"
									is-icon
									class="size-7 rounded-full"
									@click="close"
								>
									<svg
										xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 24 24"
										fill="currentColor"
										class="size-4"
									>
										<path
											fill-rule="evenodd"
											d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47-5.47 5.47a.75.75 0 1 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z"
											clip-rule="evenodd"
										/>
									</svg>
								</Button>
							</div>

							<div class="flex min-h-0 flex-1 flex-col overflow-hidden p-5">
								<div class="min-h-0 flex-1 space-y-3 overflow-x-hidden overflow-y-auto overscroll-contain pr-1">
									<div
										v-for="source in sources"
										:key="source.pkid"
										class="flex items-center justify-between rounded-xl border border-gray-100 px-4 py-3"
									>
										<div>
											<p class="text-sm font-semibold text-gray-800">
												{{ source.name }}
											</p>
											<p class="text-xs text-gray-400">
												#{{ source.pkid }}
											</p>
										</div>

										<div class="flex items-center gap-2">
											<Button
												variant="outlined"
												class="!px-3 !py-1.5 text-xs"
												@click="editSource(source)"
											>
												Изменить
											</Button>

											<Button
												variant="outlined"
												class="!px-3 !py-1.5 text-xs text-red-600"
												@click="deleteSource(source)"
											>
												Удалить
											</Button>
										</div>
									</div>

									<p
										v-if="!sources.length"
										class="py-6 text-center text-sm text-gray-400"
									>
										Источников пока нет
									</p>
								</div>

								<div class="mt-5">
									<div v-if="isCreating" class="flex gap-2">
										<input
											v-model="newSourceName"
											type="text"
											maxlength="255"
											placeholder="Название источника"
											class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm"
											@keyup.enter="submitCreate"
										/>

										<Button color="primary" @click="submitCreate">
											{{ editingSourcePkid !== null ? 'Сохранить изменения' : 'Сохранить' }}
										</Button>

										<Button variant="outlined" @click="cancelCreate">
											Отмена
										</Button>
									</div>

									<div v-else class="flex justify-end">
										<Button color="primary" @click="createSource">
											+ Добавить источник
										</Button>
									</div>
								</div>
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
import {
	TransitionRoot,
	TransitionChild,
	Dialog,
	DialogPanel,
} from '@headlessui/vue';

export default {
	name: 'LeadSourceModal',

	components: {
		Button,
		TransitionRoot,
		TransitionChild,
		Dialog,
		DialogPanel,
	},

	data() {
		return {
			isOpen: false,
			sources: [],
			isCreating: false,
			newSourceName: '',
       		editingSourcePkid: null,
		};
	},

	methods: {
		open(sources = []) { 
			this.sources = [...sources]; 
			this.isCreating = false; 
			this.newSourceName = ''; 
			this.isOpen = true; 
		}, 
		
		close() { 
			this.isOpen = false; 
			this.isCreating = false; 
			this.newSourceName = ''; 
		}, 
		
		createSource() { 
			this.newSourceName = ''; 
			this.isCreating = true; 
		}, 
		
		submitCreate() {
			const name = this.newSourceName.trim();

			if (!name) {
				return;
			}

			if (this.editingSourcePkid !== null) {
				this.$emit('edit', {
					pkid: this.editingSourcePkid,
					name,
				});
				return;
			}

			this.$emit('create', name);
		},
		
		cancelCreate() {
			this.isCreating = false;
			this.newSourceName = '';
			this.editingSourcePkid = null;
		},
		
		// Вызывается родителем после успешного создания. 
		setSources(sources = []) { 
			this.sources = [...sources]; 
			this.isCreating = false; 
			this.newSourceName = '';
			this.editingSourcePkid = null; 
		},

		editSource(source) {
			this.editingSourcePkid = source.pkid;
			this.newSourceName = source.name;
			this.isCreating = true;
		},

		deleteSource(source) {
			this.$emit('delete', source);
		},
	},
};
</script>
