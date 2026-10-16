<template>
	<main class="main-content transition-content min-w-0">
		<div class="transition-content overflow-visible px-4 sm:px-(--margin-x) pb-8 min-w-0">
			<div class="relative mt-4 sm:mt-5 space-y-4 sm:space-y-6 lg:mt-6 min-w-0">

				<!-- Header -->
				<header class="min-h-[4rem] py-3 flex flex-wrap items-center justify-between px-4 sm:px-8 border-b border-slate-200 glass-panel z-10 shrink-0 gap-3 rounded-xl">
					<div class="flex items-center gap-3 min-w-0">
						<h1 class="text-lg sm:text-xl font-bold text-slate-800 leading-tight truncate">Базы клиентов</h1>
					</div>
					<div class="flex items-center gap-2 sm:gap-4 shrink-0">
						<Button color="primary" @click="this.$refs.customerModal.open()" class="flex items-center gap-1.5 px-3 sm:px-4">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
							<span class="hidden sm:inline">Создать базу</span>
						</Button>
						<Button
							variant="outlined"
							color="secondary"
							@click="exportBases"
							class="flex items-center gap-1.5 px-3 sm:px-4"
							v-tippy="{
								content: '<span style=\'font-size: 12px\'>' + (permissions.canExport ? 'Выгрузить список баз в Excel' : 'Нет прав на экспорт') + '</span>',
								allowHTML: true,
								placement: 'bottom',
								theme: 'dark'
							}"
						>
							<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
							<span class="hidden sm:inline">Экспорт</span>
						</Button>
						<Button
							variant="outlined"
							color="secondary"
							@click="openImportModal"
							class="flex items-center gap-1.5 px-3 sm:px-4"
							v-tippy="{
								content: '<span style=\'font-size: 12px\'>Импорт баз из Excel</span>',
								allowHTML: true,
								placement: 'bottom',
								theme: 'dark'
							}"
						>
							<svg
								xmlns="http://www.w3.org/2000/svg"
								width="18"
								height="18"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								stroke-linecap="round"
								stroke-linejoin="round"
							>
								<path d="M12 3v12"/>
								<polyline points="7 10 12 15 17 10"/>
								<path d="M5 21h14"/>
							</svg>

							<span class="hidden sm:inline">Импорт</span>
						</Button>
					</div>
				</header>

				<!-- Единый поиск: Базы (фильтр таблицы) / Клиенты (кросс-базовый поиск) + фильтры — один уровень -->
				<div class="glass-panel rounded-xl p-3 sm:p-4 border border-slate-200 shadow-sm relative z-30 flex flex-wrap gap-3 items-center">

					<!-- Переключатель режима -->
					<div class="flex flex-wrap -space-x-px">
						<Button
							v-for="(btn, i) in searchModeButtons"
							:key="i"
							:class="[btn.class, searchMode === btn.mode ? 'bg-slate-50' : '']"
							variant="outlined"
							@click="setSearchMode(btn.mode)"
						>
							{{ btn.label }}
						</Button>
					</div>

					<!-- Единое поле ввода -->
					<div class="flex-1 min-w-[250px] relative">
						<Input
							ref="unifiedSearchInput"
							type="text"
							v-model="unifiedSearch"
							:placeholder="searchMode === 'clients' ? 'Имя, компания, email или телефон — ищем сразу во всех доступных базах...' : 'Поиск базы (название, регион, тип сделок)...'"
							class="w-full"
							@input="onUnifiedInput"
							@focus="onUnifiedFocus"
							@blur="onUnifiedBlur"
							@suffix-click="clearUnifiedSearch"
							:clickableSuffix="true"
						>
							<template #prefix>
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
							</template>
							<template v-if="unifiedSearch !== ''" #suffix>
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
							</template>
						</Input>

						<!-- Выпадающий список результатов кросс-поиска клиентов -->
						<div
							v-if="searchMode === 'clients' && crossOpen && unifiedSearch.trim().length >= 2"
							class="absolute left-0 right-0 top-full mt-2 bg-white border border-slate-200 rounded-xl shadow-lg max-h-96 overflow-y-auto z-40"
						>
							<div v-if="crossLoading" class="p-4 text-center text-sm text-slate-400">Поиск...</div>

							<template v-else>
								<div v-if="crossResults.length" class="px-3 py-1.5 text-[10px] font-semibold text-slate-400 border-b border-slate-100 bg-slate-50/60">
									Показано {{ crossResults.length }} из {{ crossTotal }}{{ crossQueryMs !== null ? ' · ' + crossQueryMs + 'мс' : '' }}
								</div>
								<div
									v-for="item in crossResults"
									:key="item.contact_pkid"
									class="p-3 border-b border-slate-100 last:border-none hover:bg-slate-50 cursor-pointer"
									@mousedown.prevent="goToContact(item.contact_pkid)"
								>
									<div class="flex items-center justify-between gap-3">
										<div class="flex items-center gap-3 min-w-0">
											<div class="w-9 h-9 shrink-0 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 font-bold text-xs">
												{{ (item.displayName || '?').charAt(0).toUpperCase() }}
											</div>
											<div class="min-w-0">
												<div class="font-semibold text-slate-800 text-sm truncate">{{ item.displayName || 'Без имени' }}</div>
												<div class="text-xs text-slate-500 truncate">{{ item.email || item.phone || '—' }}</div>
											</div>
										</div>
										<Badge variant="soft" class="text-[10px] shrink-0">
											{{ item.matchedBasesCount }} {{ baseWord(item.matchedBasesCount) }}
										</Badge>
									</div>

									<div class="flex flex-wrap gap-1.5 mt-2 pl-12">
										<Tag
											v-for="m in item.matches"
											:key="m.client_pkid"
											color="primary"
											class="text-[10px] cursor-pointer"
											v-tippy="{
												content: '<span style=\'font-size: 12px\'>' + (m.company || m.email || m.phone || '') + '</span>',
												allowHTML: true,
												placement: 'top',
												theme: 'dark',
												animation: 'fade',
												duration: 300,
											}"
										>
											{{ m.base_name || ('База #' + m.base_pkid) }}
										</Tag>
									</div>
								</div>

								<button
									v-if="crossResults.length && crossResults.length < crossTotal"
									type="button"
									class="w-full p-2.5 text-center text-xs font-semibold text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-wait"
									:disabled="crossLoadingMore"
									@mousedown.prevent="loadMoreCrossResults"
								>
									{{ crossLoadingMore ? 'Загрузка...' : `Показать ещё (осталось ${crossTotal - crossResults.length})` }}
								</button>

								<div v-if="!crossResults.length" class="p-4 text-center text-sm text-slate-400">
									Ничего не найдено
								</div>
							</template>
						</div>
					</div>

					<!-- is_company Filter -->
					<FacetedFilter
						v-if="table && table.getColumn('is_company')"
						:column="table.getColumn('is_company')"
						title="Тип базы"
						:options="isCompanyOptions"
					/>

					<Badge v-if="searchMode === 'clients' && accessibleBasesLabel" variant="soft" class="text-[10px] shrink-0">
						{{ accessibleBasesLabel }}
					</Badge>
				</div>

				<!-- Content -->
				<div class="flex flex-col z-10 min-h-0">

					<template v-if="!pageLoading">
					<!-- Table Area -->
					<div class="glass-panel rounded-xl border border-slate-200 overflow-hidden flex flex-col shadow-sm min-w-0">
						<div class="overflow-auto bg-white" style="width: 0; min-width: 100%;">
							<Table class="w-full text-left rtl:text-right min-w-[700px]">
								<thead class="table-thead group/thead sticky top-0" ref="theadRef">
									<tr class="table-tr group/tr">
										<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 px-4 sm:px-6 py-3 sm:py-4 w-12 text-center text-xs sm:text-sm">
											<Checkbox
												:checked="table && table.getIsAllRowsSelected()"
												@change="table && table.getToggleAllRowsSelectedHandler()($event)"
											/>
										</th>
										<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 px-4 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm">Название базы</th>
										<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 px-4 sm:px-6 py-3 sm:py-4 text-center text-xs sm:text-sm w-32">Тип</th>
										<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 px-4 sm:px-6 py-3 sm:py-4 text-center text-xs sm:text-sm w-32">Действия</th>
									</tr>
								</thead>
								<tbody class="table-tbody group/tbody">
									<tr v-if="filteredRows.length === 0" class="table-tr group/tr border-y border-transparent border-b-gray-200 last:border-none">
										<td colspan="4" class="table-td group/td px-4 sm:px-6 py-10 sm:py-16 text-center">
											<div class="flex flex-col items-center gap-2 sm:gap-3 text-slate-400">
												<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 sm:w-10 sm:h-10"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
												<p class="font-medium text-sm sm:text-base">Базы не найдены</p>
												<p class="text-xs sm:text-sm">Попробуйте изменить поисковый запрос или фильтры</p>
											</div>
										</td>
									</tr>

									<tr
										v-for="(row, idx) in filteredRows"
										:key="row.original.pkid"
										class="table-tr group/tr border-y border-transparent border-b-gray-200 last:border-none"
									>
										<td class="table-td group/td px-4 sm:px-6 py-3 sm:py-4 text-center align-middle">
											<Checkbox
												:checked="row.getIsSelected()"
												@change="row.toggleSelected()"
											/>
										</td>
										<td class="table-td group/td px-4 sm:px-6 py-3 sm:py-4">
											<div class="font-bold text-slate-800 text-sm sm:text-base leading-tight truncate">{{ row.original.name }}</div>
										</td>
										<td class="table-td group/td px-4 sm:px-6 py-3 sm:py-4 text-center align-middle">
											<Badge :color="row.original.is_company ? 'warning' : 'primary'" class="text-[10px] sm:text-xs px-2 py-1">
												{{ row.original.is_company ? 'Компания' : 'Физлицо' }}
											</Badge>
										</td>
										<td class="table-td group/td px-4 sm:px-6 py-3 sm:py-4 align-middle">
											<div class="flex items-center justify-center gap-1.5 flex-nowrap">
												<Button @click="goToCustomersList(row.original)" isIcon class="p-2 shrink-0" v-tippy="{ 
																																content: '<span style=\'font-size: 12px\'>Перейти в базу</span>',
																																allowHTML: true,
																																placement: 'top',
																																theme: 'dark',
																																animation: 'fade',
																																duration: 300,
																															}">
													<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/> <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
												</Button>
												<Button @click="openAddRoleModal(row.original)" isIcon class="p-2 shrink-0" v-tippy="{ 
																																content: '<span style=\'font-size: 12px\'>Роли базы</span>',
																																allowHTML: true,
																																placement: 'top',
																																theme: 'dark',
																																animation: 'fade',
																																duration: 300,
																															}">
													<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 21a8 8 0 0 1 13.292-6"/><circle cx="10" cy="8" r="5"/><path d="M19 16v6"/><path d="M22 19h-6"/></svg>
												</Button>
												<Button
													@click="$refs.customerModal.openForEdit(row.original)"
													:disabled="!canDo('canEdit', row.original.pkid)"
													isIcon
													class="p-2 shrink-0"
													v-tippy="{ 
																content: '<span style=\'font-size: 12px\'>Редактировать</span>',
																allowHTML: true,
																placement: 'top',
																theme: 'dark',
																animation: 'fade',
																duration: 300,
															}">
													<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/></svg>
												</Button>
												<Button
													@click="openDeleteOne(row.original)"
													:disabled="!canDo('canDelete', row.original.pkid)"
													isIcon
													class="p-2 shrink-0"
													v-tippy="{ 
														content: '<span style=\'font-size: 12px\'>Удалить</span>',
														allowHTML: true,
														placement: 'top',
														theme: 'dark',
														animation: 'fade',
														duration: 300,
													}">
													<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
												</Button>
											</div>
										</td>
									</tr>
								</tbody>
							</Table>
						</div>

						<div class="px-4 pb-4 sm:px-5 sm:pt-4 pt-4">
							<div v-if="table.getRowModel().rows.length" class="p-4 sm:px-5">
								<PaginationSection :table="table" />
							</div>
						</div>
						<SelectedRowsActions :table="table" :height="theadHeight" />
					</div>
					</template>

					<!-- Skeleton: имитация загрузки баз клиентов -->
					<template v-else>
						<div class="glass-panel rounded-xl border border-slate-200 overflow-hidden shadow-sm">
							<div class="px-5 py-3 border-b border-slate-200 bg-gray-100">
								<Skeleton class="h-4 w-full" />
							</div>
							<div v-for="n in 8" :key="n" class="flex items-center gap-4 px-5 py-3.5 border-b border-slate-100 last:border-none">
								<Skeleton class="h-4 w-4 rounded shrink-0" />
								<Skeleton class="h-3.5 flex-1 max-w-[220px]" />
								<Skeleton class="h-3.5 w-20 rounded-full mx-auto" />
								<Skeleton class="h-6 w-24 mx-auto" />
							</div>
						</div>
					</template>

				</div>
			</div>
		</div>

		<CustomerModal ref="customerModal" @create="onClientBaseCreate" @update="onClientBaseUpdate" />
		<DeleteModal ref="deleteModal" @confirm="onDeleteConfirm" />
		<AddRoleModal
			ref="addRoleModal"
			:companyPkid="companyPkid"
			:basePkid="selectedBaseForModal?.pkid"
			@confirm="onRolesConfirmed"
		/>
		<ImportModal
			ref="importModal"
			@import="onClientBaseImport"
		/>
	</main>
</template>

<style lang="scss" scoped>
.glass-panel {
	background: rgba(255, 255, 255, 0.95);
	backdrop-filter: blur(12px);
	border: 1px solid rgba(226, 232, 240, 0.8);
	box-shadow:
		0 4px 6px -1px rgba(0, 0, 0, 0.05),
		0 2px 4px -1px rgba(0, 0, 0, 0.03);
}
</style>

<script>
import lib from 'io7/lib';
import FacetedFilter from 'io7/components/tailux/shared/table/FacedtedFilter.vue';
import Input from 'io7/components/tailux/ui/form/Input.vue';
import { rankItem } from '@tanstack/match-sorter-utils';
import PaginationSection from 'io7/components/tailux/shared/table/PaginationSection.vue';
import Badge from 'io7/components/tailux/ui/Badge.vue';
import Tag from 'io7/components/tailux/ui/Tag.vue';
import Button from 'io7/components/tailux/ui/Button.vue';
import Checkbox from 'io7/components/tailux/ui/form/Checkbox.vue';
import Table from 'io7/components/tailux/ui/Table.vue';
import Skeleton from 'io7/components/tailux/loading/Skeleton.vue';
import SelectedRowsActions from "io7/components/tailux/shared/table/SelectedRowsActions.vue";
import CustomerModal from '../components/pages/customersBase/modals/CustomerModal.vue';
import DeleteModal from '../components/pages/modals/DeleteModal.vue';
import AddRoleModal from '../components/pages/customersBase/modals/AddRoleModal.vue';
import ImportModal from '../components/pages/customersBase/modals/ImportModal.vue';
import {
	getCoreRowModel,
	getFilteredRowModel,
	getSortedRowModel,
	getFacetedRowModel,
	getFacetedUniqueValues,
	useVueTable,
	createColumnHelper,
	getPaginationRowModel,
} from '@tanstack/vue-table';

const fuzzyFilter = (row, columnId, value, addMeta) => {
	const itemRank = rankItem(row.getValue(columnId), value);
	addMeta({ itemRank });
	return itemRank.passed;
};

const facetedFilter = (row, columnId, filterValue) => {
	if (!filterValue) return true;
	const { op, values } = filterValue;
	const cell = row.getValue(columnId);
	if (op === 'empty') return !cell;
	if (op === 'notEmpty') return !!cell;
	if (!values?.length) return true;
	switch (op) {
		case 'equals': return values.includes(cell);
		case 'notEquals': return !values.includes(cell);
		default: return values.includes(cell);
	}
};

const columnHelper = createColumnHelper();

const columns = [
	columnHelper.accessor('name', { id: 'name', filterFn: 'fuzzy' }),
	columnHelper.accessor('is_company', { id: 'is_company', filterFn: facetedFilter }),
];

export default {
	name: 'ClientBasesManagement',
	mixins: lib.mixins,

	components: {
		FacetedFilter,
		Input,
		PaginationSection,
		Badge,
		Tag,
		Button,
		Table,
		Checkbox,
		SelectedRowsActions,
		CustomerModal,
		DeleteModal,
		AddRoleModal,
		Skeleton,
		ImportModal,
	},

	data() {
		return {
			pageLoading: true,
			clientBases: [],
			total: 0,
			loading: false,
			globalFilter: '',
			sorting: [],
			columnFilters: [],
			table: null,
			pagination: { pageIndex: 0, pageSize: 10 },
			selectedBaseForModal: null,
			fullAccess: true,
			accessibleBaseIds: [],
			permissions: {
				canRead: false,
				canCreate: false,
				canEdit: false,
				canDelete: false,
				canViewHistory: false,
				canExport: false,
				canConfigureFields: false,
				canBulkAction: false,
			},
			isCompanyOptions: [
				{ label: 'Компания', value: 1 },
				{ label: 'Физлицо', value: 0 },
			],

			// ---- Единый поиск: режим + общее поле ввода ----
			// 'bases'   — обычный фильтр таблицы баз по названию/региону/типу сделок (по умолчанию);
			// 'clients' — кросс-базовый поиск клиентов.
			searchMode: 'bases',
			unifiedSearch: '',

			searchModeButtons: [
				{ label: 'Базы', mode: 'bases', class: 'ltr:rounded-r-none rtl:rounded-l-none' },
				{ label: 'Клиенты', mode: 'clients', class: 'ltr:rounded-l-none rtl:rounded-r-none' },
			],

			crossResults: [],
			crossTotal: 0,
			crossQueryMs: null,
			crossLoading: false,
			crossLoadingMore: false,
			crossOpen: false,
			_crossDebounceTimer: null,
			_crossRequestId: 0,

			rowSelection: {},
			pendingDeleteIds: [],
			theadHeight: 0,
		};
	},

	computed: {
		companyPkid() {
			return this.$store.state.io7.auth.selectedCabPkid;
		},

		filteredRows() {
			return this.table ? this.table.getRowModel().rows : [];
		},

		accessibleBasesLabel() {
			if (this.fullAccess) return '';
			const n = this.accessibleBaseIds.length;
			return n ? `Доступно баз: ${n}` : '';
		},
	},

	created() {
		const self = this;

		this.table = useVueTable({
			get data() { return self.clientBases; },
			columns,
			enableRowSelection: true,
			filterFns: { fuzzy: fuzzyFilter },
			state: {
				get globalFilter() { return self.globalFilter; },
				get columnFilters() { return self.columnFilters; },
				get sorting() { return self.sorting; },
				get pagination() { return self.pagination; },
				get rowSelection() { return self.rowSelection; },
			},
			onRowSelectionChange: (updater) => {
				self.rowSelection = typeof updater === 'function' ? updater(self.rowSelection) : updater;
			},
			onGlobalFilterChange: (updater) => {
				self.globalFilter = typeof updater === 'function' ? updater(self.globalFilter) : updater;
			},
			onColumnFiltersChange: (updater) => {
				self.columnFilters = typeof updater === 'function' ? updater(self.columnFilters) : updater;
			},
			onSortingChange: (updater) => {
				self.sorting = typeof updater === 'function' ? updater(self.sorting) : updater;
			},
			onPaginationChange: (updater) => {
				self.pagination = typeof updater === 'function' ? updater(self.pagination) : updater;
			},
			globalFilterFn: fuzzyFilter,
			getCoreRowModel: getCoreRowModel(),
			getFilteredRowModel: getFilteredRowModel(),
			getSortedRowModel: getSortedRowModel(),
			getFacetedRowModel: getFacetedRowModel(),
			getFacetedUniqueValues: getFacetedUniqueValues(),
			getPaginationRowModel: getPaginationRowModel(),
			meta: {
				deleteRow: (row) => self.openDeleteOne(row.original),
				deleteRows: (rows) => self.openDeleteMany(rows),
			},
		});
	},

	async mounted() {
		await Promise.all([
			this.loadClientBases(),
			this.loadAccessibleBaseIds(),
		]);
		this.resizeObserver = new ResizeObserver((entries) => {
			for (const entry of entries) {
				if (entry.target === this.$refs.theadRef) {
					this.theadHeight = entry.contentRect.height;
				}
			}
		});
		if (this.$refs.theadRef) {
			this.resizeObserver.observe(this.$refs.theadRef);
		}

		this.pageLoading = false;
	},

	beforeUnmount() {
		if (this.resizeObserver) {
			this.resizeObserver.disconnect();
		}
		clearTimeout(this._crossDebounceTimer);
	},

	methods: {
		async loadClientBases() {
			this.loading = true;
			try {
				const data = await this.mfModel.getClientBases({
					company_pkid: this.companyPkid,
				});
				this.clientBases = data.items;
				this.total = data.total;
			} catch (e) {
				console.error(e);
			} finally {
				this.loading = false;
			}
		},

		async onClientBaseCreate(payload, { done, finish } = {}) {
			try {
				const data = await this.mfModel.createClientBase({ ...payload, company_pkid: this.companyPkid });
				if (data.error_code === 1) {
					this.$noty.success('База успешно создана.', { layout: 'bottomRight', timeout: 3000 });
					finish?.();
					await this.loadClientBases();
				} else {
					this.$noty.error(data.error_str || 'Ошибка создания базы', { layout: 'bottomRight', timeout: 3000 });
					done?.();
				}
			} catch (e) {
				console.error(e);
				this.$noty.error('Ошибка сети', { layout: 'bottomRight', timeout: 3000 });
				done?.();
			}
		},

		async onClientBaseUpdate(payload, { done, finish } = {}) {
			try {
				const { pkid, ...item } = payload;
				const data = await this.mfModel.updateClientBase(pkid, item);
				if (data.error_code === 1) {
					this.$noty.success('База успешно обновлена.', { layout: 'bottomRight', timeout: 3000 });
					finish?.();
					await this.loadClientBases();
				} else {
					this.$noty.error(data.error_str || 'Ошибка обновления базы', { layout: 'bottomRight', timeout: 3000 });
					done?.();
				}
			} catch (e) {
				console.error(e);
				this.$noty.error('Ошибка сети', { layout: 'bottomRight', timeout: 3000 });
				done?.();
			}
		},

		openDeleteOne(base) {
			this.pendingDeleteIds = [base.pkid];
			this.$refs.deleteModal.open();
		},

		openDeleteMany(rows) {
			const ids = rows.map(r => r.original.pkid);
			if (!ids.length) {
				this.$noty.warning('Не выбрано ни одной базы', { layout: 'bottomRight', timeout: 3000 });
				return;
			}
			this.pendingDeleteIds = ids;
			this.$refs.deleteModal.open(ids.length);
		},

		async onDeleteConfirm({ done, finish }) {
			const ids = this.pendingDeleteIds;
			try {
				const results = await Promise.all(ids.map(id => this.mfModel.deleteClientBase(id)));
				const failed = results.filter(r => r.error_code !== 1).length;

				await this.loadClientBases();
				this.rowSelection = {};

				if (failed > 0) {
					this.$noty.warning(`Удалено ${ids.length - failed} из ${ids.length}, часть баз не удалилась`, { layout: 'bottomRight', timeout: 4000 });
				} else {
					this.$noty.warning(ids.length > 1 ? 'Выбранные базы удалены.' : 'База удалена.', { layout: 'bottomRight', timeout: 3000 });
				}

				finish();
			} catch (e) {
				console.error(e);
				this.$noty.error('Ошибка сети', { layout: 'bottomRight', timeout: 3000 });
				done();
			}
		},

		async openAddRoleModal(base) {
			this.selectedBaseForModal = base;
			await this.$nextTick();
			this.$refs.addRoleModal.open();
		},

		async onRolesConfirmed(payload) {
			const { added, removedIds } = payload;

			try {
				const calls = [];

				if (added.length > 0) {
					calls.push(this.mfModel.assignRolesToBase({
						company_pkid: this.companyPkid,
						base_pkid: this.selectedBaseForModal.pkid,
						role_pkids: added.map(r => r.id),
					}));
				}

				if (removedIds.length > 0) {
					calls.push(this.mfModel.unassignRolesFromBase({
						company_pkid: this.companyPkid,
						base_pkid: this.selectedBaseForModal.pkid,
						role_pkids: removedIds,
					}));
				}

				if (calls.length === 0) {
					this.$noty.info('Изменений нет', { layout: 'bottomRight', timeout: 2000 });
					return;
				}

				const results = await Promise.all(calls);
				const hasError = results.some(r => r.error_code !== 1);

				if (!hasError) {
					await this.loadAccessibleBaseIds(); // ← добавили, чтобы кнопки обновили доступность
					this.$noty.success('Роли базы обновлены.', { layout: 'bottomRight', timeout: 3000 });
				} else {
					const firstError = results.find(r => r.error_code !== 1);
					this.$noty.error(firstError?.error_str || 'Ошибка обновления ролей базы');
				}
			} catch (e) {
				console.error(e);
				this.$noty.error('Ошибка сети');
			}
		},

		async loadAccessibleBaseIds() {
			try {
				const data = await this.mfModel.getAccessibleClientBaseIds({
					company_pkid: this.companyPkid,
				});
				if (data.error_code === 1) {
					this.fullAccess = !!data.full_access;
					this.accessibleBaseIds = (data.base_pkids || []).map(id => Number(id));
					this.permissions = data.permissions || this.permissions;
				}
			} catch (e) {
				console.error(e);
			}
		},

		hasAccess(pkid) {
			if (this.fullAccess) return true;
			return this.accessibleBaseIds.includes(Number(pkid));
		},

		canDo(permission, pkid) {
			return this.hasAccess(pkid) && !!this.permissions[permission];
		},

		goToCustomersList(base) {
			this.$router.push({
				name: 'crm-customers-list',
				params: { pkid: base.pkid },
			});
		},

		// ---- Единый поиск: переключение режима + общий инпут ----

		setSearchMode(mode) {
			if (this.searchMode === mode) return;
			this.searchMode = mode;

			// Смена режима сбрасывает поисковую строку и всё, что с ней связано —
			// иначе после переключения останется "призрачный" фильтр таблицы
			// или зависший дропдаун от предыдущего режима.
			this.unifiedSearch = '';
			this.globalFilter = '';
			this.crossResults = [];
			this.crossTotal = 0;
			this.crossQueryMs = null;
			this.crossLoadingMore = false;
			this.crossOpen = false;
			this._crossRequestId++; // отменяем любой недолетевший запрос предыдущего режима
			clearTimeout(this._crossDebounceTimer);
		},

		onUnifiedInput() {
			if (this.searchMode === 'bases') {
				// Клиентский фильтр таблицы — реагирует мгновенно через
				// state таблицы, отдельный debounce не нужен.
				this.globalFilter = this.unifiedSearch;
				return;
			}

			// Режим 'clients' — серверный кросс-базовый поиск с debounce.
			this.crossOpen = true;
			clearTimeout(this._crossDebounceTimer);

			const term = this.unifiedSearch.trim();
			if (term.length < 2) {
				this.crossResults = [];
				this.crossTotal = 0;
				this.crossLoading = false;
				return;
			}

			this._crossDebounceTimer = setTimeout(() => {
				this.performCrossSearch(term);
			}, 350);
		},

		async performCrossSearch(term, options = {}) {
			term = (term ?? this.unifiedSearch).trim();
			if (term.length < 2) return;

			const append = !!options.append;

			// Guard от гонки ответов: если пока летел запрос для "а", юзер
			// допечатал до "айнур" и второй запрос уже стартовал/вернулся —
			// более старый ответ не должен перезаписать более новый результат.
			const requestId = ++this._crossRequestId;

			if (append) {
				this.crossLoadingMore = true;
			} else {
				this.crossLoading = true;
				this.crossResults = [];
			}
			this.crossQueryMs = null;
			const startedAt = performance.now();
			try {
				const data = await this.mfModel.searchContactsAcrossBases({
					company_pkid: this.companyPkid,
					search: term,
					start: append ? this.crossResults.length : 0,
					limit: 20,
				});

				if (requestId !== this._crossRequestId) {
					// Пришёл ответ на уже неактуальный запрос — игнорируем.
					return;
				}

				this.crossQueryMs = Math.round(performance.now() - startedAt);

				if (data.error_code === 1) {
					this.crossResults = append ? [...this.crossResults, ...(data.items || [])] : (data.items || []);
					this.crossTotal = data.total || 0;
				} else {
					if (!append) {
						this.crossResults = [];
						this.crossTotal = 0;
					}
					this.$noty.error(data.error_str || 'Ошибка поиска по базам', { layout: 'bottomRight', timeout: 3000 });
				}
			} catch (e) {
				if (requestId !== this._crossRequestId) return;
				console.error(e);
				if (!append) this.crossResults = [];
				this.$noty.error('Ошибка сети', { layout: 'bottomRight', timeout: 3000 });
			} finally {
				if (requestId === this._crossRequestId) {
					this.crossLoading = false;
					this.crossLoadingMore = false;
				}
			}
		},

		loadMoreCrossResults() {
			const term = this.unifiedSearch.trim();
			if (term.length < 2 || this.crossLoadingMore) return;
			this.performCrossSearch(term, { append: true });
		},

		clearUnifiedSearch() {
			this.unifiedSearch = '';
			this.globalFilter = '';
			this.crossResults = [];
			this.crossTotal = 0;
			this.crossQueryMs = null;
			this.crossLoadingMore = false;
			this.crossOpen = false;
			this._crossRequestId++;
			clearTimeout(this._crossDebounceTimer);
		},

		onUnifiedFocus() {
			if (this.searchMode === 'clients' && this.unifiedSearch.trim().length >= 2) {
				this.crossOpen = true;
			}
		},

		onUnifiedBlur() {
			// Небольшая задержка + @mousedown.prevent на пунктах списка ниже:
			// клик по результату не должен "проглатываться" закрытием дропдауна
			// раньше, чем сработает переход.
			setTimeout(() => {
				this.crossOpen = false;
			}, 150);
		},

		goToContact(contactPkid) {
			if (!contactPkid) return;
			this.crossOpen = false;
			// params.id теперь несёт contact_pkid (pkid из таблицы contacts),
			// а не client_pkid конкретной базы — профиль по контакту сам
			// агрегирует все базы/лиды/сделки, поэтому знать конкретный
			// client_pkid для навигации больше не требуется.
			this.$router.push({ name: 'crm-client', params: { id: contactPkid } });
		},

		baseWord(n) {
			const mod10 = n % 10;
			const mod100 = n % 100;
			if (mod10 === 1 && mod100 !== 11) return 'база';
			if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'базы';
			return 'баз';
		},

		async exportBases() {
			const companyId = this.companyPkid || this.company_pkid || 1;

			try {
				const response = await this.mfModel.getClientBasesReport(companyId);

				if (!response || !response.file_content) {
					this.$toast?.error(response?.error_str || 'Не удалось сформировать файл');
					return;
				}

				const byteCharacters = atob(response.file_content);
				const byteArray = new Uint8Array(byteCharacters.length);

				for (let i = 0; i < byteCharacters.length; i++) {
					byteArray[i] = byteCharacters.charCodeAt(i);
				}

				const blob = new Blob([byteArray], {
					type: response.mime || 'application/vnd.ms-excel'
				});

				const url = window.URL.createObjectURL(blob);
				const link = document.createElement('a');

				link.href = url;
				link.download = response.filename || 'client_bases.xls';

				document.body.appendChild(link);
				link.click();
				link.remove();

				window.URL.revokeObjectURL(url);
				
				this.$toast?.success('Выгрузка успешно выполнена');

			} catch (error) {
				this.$toast?.error('Ошибка экспорта');
			}
		},

		openImportModal() {
			if (!this.permissions.canCreate) {
				this.$noty.error(
					'Нет прав на импорт баз',
					{ layout: 'bottomRight', timeout: 3000 }
				);

				return;
			}

			this.$refs.importModal.open();
		},

		async onClientBaseImport(file, { done, finish } = {}) {
			try {
				const companyId = this.companyPkid || this.company_pkid || 1;

				const data = await this.mfModel.importClientBases(
					companyId,
					file
				);

				if (data.error_code === 1) {

					const created = Number(data.created || 0);
					const skipped = Number(data.skipped || 0);
					const errors = data.errors || [];

					if (errors.length > 0) {
						this.$noty.warning(
							`Импорт завершён: создано ${created}, пропущено ${skipped}, ошибок ${errors.length}`,
							{
								layout: 'bottomRight',
								timeout: 5000,
							}
						);
					} else {
						this.$noty.success(
							`Импорт завершён: создано ${created}, пропущено ${skipped}`,
							{
								layout: 'bottomRight',
								timeout: 4000,
							}
						);
					}

					finish?.();

					await this.loadClientBases();

				} else {

					this.$noty.error(
						data.error_str || 'Ошибка импорта',
						{
							layout: 'bottomRight',
							timeout: 4000,
						}
					);

					done?.();
				}

			} catch (e) {

				console.error('Ошибка импорта:', e);

				this.$noty.error(
					'Ошибка сети при импорте',
					{
						layout: 'bottomRight',
						timeout: 4000,
					}
				);

				done?.();
			}
		},
	},
};
</script>
