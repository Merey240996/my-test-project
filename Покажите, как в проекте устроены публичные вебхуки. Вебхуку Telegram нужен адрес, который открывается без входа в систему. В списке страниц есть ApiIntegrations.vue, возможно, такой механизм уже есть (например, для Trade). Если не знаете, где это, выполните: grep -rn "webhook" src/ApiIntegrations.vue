<template>
	<main class="main-content transition-content">
		<div class="transition-content overflow-visible px-4 sm:px-(--margin-x) pb-8">
			<div class="relative mt-4 sm:mt-5 space-y-4 sm:space-y-6 lg:mt-6">

				<!-- Header -->
				<header class="min-h-16 flex flex-wrap items-center justify-between gap-3 px-4 sm:px-8 py-3 border-b border-slate-200 glass-panel z-10 shrink-0">
					<div class="flex items-center flex-wrap gap-2">
						<h1 class="text-lg sm:text-xl font-bold text-slate-800">API & Интеграции</h1>
						<Badge color="success" variant="soft" class="ml-0 sm:ml-4 px-3 py-1 font-semibold flex items-center gap-1.5 shadow-sm text-xs">
							<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-green"></span>
							API Gateway: Online
						</Badge>
					</div>

					<div class="flex items-center gap-3">
						<Button @click="forceSync" :disabled="isSyncing" class="bg-white hover:bg-slate-100 border border-slate-300 text-slate-700 px-3 sm:px-4 py-2 rounded-lg font-medium text-xs sm:text-sm transition-colors shadow-sm disabled:opacity-50 flex items-center gap-2 shrink-0">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
							<span class="hidden sm:inline">{{ isSyncing ? 'Синхронизация...' : 'Принудительная синхронизация' }}</span>
							<span class="sm:hidden">{{ isSyncing ? 'Синк...' : 'Синхронизация' }}</span>
						</Button>
					</div>
				</header>

				<!-- Dashboard Content -->
				<div class="flex-1 overflow-y-auto z-10 scroll-smooth flex flex-col gap-6">

					<!-- Section 1 -->
					<section>
						<div class="mb-4">
							<h2 class="text-lg font-bold text-slate-800">
								Статус внешних систем (Master-Data)
							</h2>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

							<!-- BMC Trade -->
							<div class="glass-panel rounded-xl p-5 border border-slate-200 transition-shadow hover:shadow-md">
								<div class="flex justify-between items-start mb-4">
									<div class="flex items-center gap-3">
										<div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 border border-blue-100">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-store-icon lucide-store"><path d="M15 21v-5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5"/><path d="M17.774 10.31a1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.451 0 1.12 1.12 0 0 0-1.548 0 2.5 2.5 0 0 1-3.452 0 1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.77-3.248l2.889-4.184A2 2 0 0 1 7 2h10a2 2 0 0 1 1.653.873l2.895 4.192a2.5 2.5 0 0 1-3.774 3.244"/><path d="M4 10.95V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8.05"/></svg>
										</div>

										<div>
											<h3 class="font-bold text-slate-800">BMC Trade</h3>
											<div class="text-xs text-slate-500">Маркетплейс & Склады</div>
										</div>
									</div>

									<Badge 
										color="success"
										variant="soft"
										class="border border-this-darker/20 dark:border-this-lighter/20"
									>
										Connected
									</Badge>
								</div>

								<div class="space-y-2 text-sm">
									<div class="flex justify-between border-b border-slate-100 pb-2">
										<span class="text-slate-500">Последний Sync</span>
										<span class="font-medium font-mono text-slate-700">{{ lastSyncTime }}</span>
									</div>

									<div class="flex justify-between border-b border-slate-100 pb-2">
										<span class="text-slate-500">Задержка (Ping)</span>
										<span class="font-medium font-mono text-emerald-600">14 ms</span>
									</div>

									<div class="flex justify-between">
										<span class="text-slate-500">Режим обмена</span>
										<span class="font-medium text-slate-700">Near Real-Time</span>
									</div>
								</div>
							</div>

							<!-- Payment -->
							<div class="glass-panel rounded-xl p-5 border border-slate-200 transition-shadow hover:shadow-md">
								<div class="flex justify-between items-start mb-4">
									<div class="flex items-center gap-3">
										<div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 border border-purple-100">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-credit-card-icon lucide-credit-card"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
										</div>

										<div>
											<h3 class="font-bold text-slate-800">Payment Gateway</h3>
											<div class="text-xs text-slate-500">Kaspi Pay / Stripe</div>
										</div>
									</div>

									<Badge 
										color="success"
										variant="soft"
										class="border border-this-darker/20 dark:border-this-lighter/20"
									>
										Connected
									</Badge>
								</div>

								<div class="space-y-2 text-sm">
									<div class="flex justify-between border-b border-slate-100 pb-2">
										<span class="text-slate-500">Успешные транзакции</span>
										<span class="font-medium font-mono text-slate-700">99.8%</span>
									</div>

									<div class="flex justify-between border-b border-slate-100 pb-2">
										<span class="text-slate-500">Задержка (Ping)</span>
										<span class="font-medium font-mono text-emerald-600">32 ms</span>
									</div>

									<div class="flex justify-between">
										<span class="text-slate-500">Idempotency</span>
										<div class="flex justify-center items-center">
											<span class="font-medium text-emerald-600 inline-flex items-center gap-1">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-icon lucide-check"><path d="M20 6 9 17l-5-5"/></svg>
												Strict
											</span>
										</div>
									</div>
								</div>
							</div>

							<!-- VATS -->
							<div class="glass-panel rounded-xl p-5 border border-slate-200 transition-shadow hover:shadow-md">
								<div class="flex justify-between items-start mb-4">
									<div class="flex items-center gap-3">
										<div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-600 border border-orange-100">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone-call-icon lucide-phone-call"><path d="M13 2a9 9 0 0 1 9 9"/><path d="M13 6a5 5 0 0 1 5 5"/><path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/></svg>
										</div>

										<div>
											<h3 class="font-bold text-slate-800">Телефония ВАТС</h3>
											<div class="text-xs text-slate-500">SIP-коммуникации CRM</div>
										</div>
									</div>

									<Badge 
										color="success"
										variant="soft"
										class="border border-this-darker/20 dark:border-this-lighter/20"
									>
										Connected
									</Badge>
								</div>

								<div class="space-y-2 text-sm">
									<div class="flex justify-between border-b border-slate-100 pb-2">
										<span class="text-slate-500">Активных линий</span>
										<span class="font-medium font-mono text-slate-700">14 / 50</span>
									</div>

									<div class="flex justify-between border-b border-slate-100 pb-2">
										<span class="text-slate-500">Запись аудио</span>
										<span class="font-medium text-slate-700">S3 Storage</span>
									</div>

									<div class="flex justify-between">
										<span class="text-slate-500">WebRTC Socket</span>
										<span class="font-medium text-emerald-600">Active</span>
									</div>
								</div>
							</div>

						</div>
					</section>

					<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

						<!-- Idempotency -->
						<section class="lg:col-span-1 flex flex-col gap-4">
							<div>
								<h2 class="text-lg font-bold text-slate-800">
									Идемпотентность (Idempotency)
								</h2>

								<p class="text-xs text-slate-500">
									Защита от дублирования транзакций при обрывах сети мобильных клиентов.
								</p>
							</div>

							<div class="glass-panel rounded-xl p-6 border border-slate-200 flex-1 flex flex-col items-center justify-center">
								<div class="relative w-40 h-40 mb-6">
									<Circlebar 
										color="success" 
										:value="100" 
										strokeLinecap="square"
										:size="40"
									>
										<div class="absolute inset-0 flex flex-col items-center justify-center">
											<span class="text-3xl font-bold font-mono text-slate-800">
												100<span class="text-xl">%</span>
											</span>

											<span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
												Валидно
											</span>
										</div>
									</Circlebar>
								</div>

								<div class="w-full space-y-3">
									<div class="flex justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
										<div class="flex items-center gap-2 text-sm">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-icon lucide-shield"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>

											<span class="text-slate-600 font-medium">
												Предотвращено дублей
											</span>
										</div>

										<span class="font-mono font-bold text-slate-800">
											{{ stats.duplicatesPrevented }}
										</span>
									</div>

									<div class="flex justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
										<div class="flex items-center gap-2 text-sm">
											<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20	" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert-icon lucide-triangle-alert"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>

											<span class="text-slate-600 font-medium">
												Отклонено (Bad Token)
											</span>
										</div>

										<span class="font-mono font-bold text-slate-800">
											{{ stats.failedTokens }}
										</span>
									</div>
								</div>
							</div>
						</section>

						<!-- Webhooks -->
						<section class="lg:col-span-2 flex flex-col gap-4">
							<div class="flex flex-col sm:flex-row justify-between sm:items-end gap-3">
								<div>
									<h2 class="text-lg font-bold text-slate-800">
										Конфигуратор Webhooks
									</h2>

									<p class="text-xs text-slate-500">
										Управление подписками на асинхронные события экосистемы.
									</p>
								</div>

								<Button 
									color="primary"
								>
									+ Добавить эндпоинт
								</Button>
							</div>

							<div class="glass-panel rounded-xl border border-slate-200 flex-1 min-w-0">
								<div style="width: 0; min-width: 100%; overflow-x: auto;">
									<Table class="w-full text-left rtl:text-right min-w-[600px]">
										<thead class="table-thead group/thead">
											<tr class="table-tr group/tr">
												<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 dark:bg-dark-800 dark:text-dark-100 px-5 py-3 w-12 text-center">
													Вкл
												</th>
												<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 dark:bg-dark-800 dark:text-dark-100 px-5 py-3">
													Event Trigger
												</th>
												<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 dark:bg-dark-800 dark:text-dark-100 px-5 py-3 w-1/2">
													Endpoint URL
												</th>
												<th class="table-th group/th bg-gray-200 font-semibold uppercase text-gray-800 dark:bg-dark-800 dark:text-dark-100 px-5 py-3 text-right">
													Действия
												</th>
											</tr>
										</thead>

										<tbody class="table-tbody group/tbody">
											<tr
												v-for="wh in webhooks"
												:key="wh.id"
												class="table-tr group/tr border-y border-transparent border-b-gray-200 last:border-none dark:border-b-dark-500"
											>
												<td class="table-td group/td px-5 py-3 text-center">
													<Switch 
														v-model="wh.active"
													/>
												</td>

												<td
													class="table-td group/td px-5 py-3 font-mono font-semibold text-xs"
													:class="wh.active ? 'text-slate-800' : 'text-slate-400'"
												>
													{{ wh.event }}
												</td>

												<td class="table-td group/td px-5 py-3">
													<Input
														type="text"
														v-model="wh.url"
														:disabled="!wh.active"
														class="w-full"
														:class="!wh.active ? 'text-slate-400 bg-slate-50' : 'text-slate-700'"
													/>
												</td>

												<td class="table-td group/td px-5 py-3 text-right">
													<Button
														@click="testWebhook(wh)"
														:disabled="!wh.active || wh.testing"
														class="px-2 bg-white border border-slate-200 rounded text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors disabled:opacity-50 gap-1"
													>
														<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send-icon lucide-send"><path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/></svg>

														Test
													</Button>
												</td>
											</tr>
										</tbody>
									</Table>
								</div>
							</div>
						</section>

					</div>

					<!-- Logs -->
					 
					<section class="flex flex-col gap-4 flex-1 mt-2">
						<div class="flex flex-col sm:flex-row justify-between sm:items-end gap-2">
							<div>
								<h2 class="text-lg font-bold text-slate-800">
									Журнал транзакций API (Live)
								</h2>

								<p class="text-xs text-slate-500">
									Мониторинг синхронизации остатков BMC Trade, заказов и возвратов.
								</p>
							</div>

							<div class="flex items-center gap-2">
								<span class="flex h-2 w-2 relative">
									<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>

									<span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
								</span>

								<span class="text-xs text-slate-500 font-mono font-medium">
									Real-time Sync Active
								</span>
							</div>
						</div>

						<div class="glass-panel rounded-xl overflow-hidden border border-slate-200 flex-1 flex flex-col min-h-[300px]">
							<div class="overflow-y-auto p-0 bg-slate-900" style="max-height: 400px; width: 0; min-width: 100%; overflow-x: auto;">
								<Table class="w-full text-left text-xs font-mono text-slate-300 border-collapse min-w-[700px]">
									<thead class="text-slate-400 uppercase bg-slate-950 sticky top-0 shadow-sm z-10 border-b border-slate-800">
										<tr>
											<th class="px-4 py-3 font-semibold w-24">Время</th>
											<th class="px-4 py-3 font-semibold w-20">Method</th>
											<th class="px-4 py-3 font-semibold">Endpoint / Target</th>
											<th class="px-4 py-3 font-semibold w-24">Status</th>
											<th class="px-4 py-3 font-semibold w-1/3">
												Payload / Response
											</th>
										</tr>
									</thead>

									<tbody>
										<tr
											v-for="log in apiLogs"
											:key="log.id"
											class="border-b border-slate-800/50 hover:bg-slate-800 transition-colors"
										>
											<td class="px-4 py-2.5 text-slate-500">
												{{ log.time }}
											</td>

											<td class="px-4 py-2.5">
												<Badge
													unstyled
													class="px-2 py-0.5 rounded font-bold text-[10px]"
													:class="getMethodClass(log.method)"
												>
													{{ log.method }}
												</Badge>
											</td>

											<td
												class="px-4 py-2.5 text-slate-300 truncate max-w-[200px]"
												:title="log.endpoint"
											>
												{{ log.endpoint }}
											</td>

											<td class="px-4 py-2.5">
												<span
													class="flex items-center gap-1.5"
													:class="getStatusColor(log.status)"
												>
													<svg width="10" height="10" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
														<circle cx="12" cy="12" r="11" fill="currentColor"/>
													</svg>
													{{ log.status }}
												</span>
											</td>

											<td
												class="px-4 py-2.5 text-slate-400 truncate max-w-[300px]"
												:title="log.payload"
											>
												{{ log.payload }}
											</td>
										</tr>
									</tbody>
								</Table>
							</div>
						</div>
					</section>

				</div>

			</div>
		</div>
	</main>
</template>

<style lang="scss" scoped>
.glass-panel {
	background: rgba(255, 255, 255, 0.85);
	backdrop-filter: blur(12px);
	border: 1px solid rgba(226, 232, 240, 0.8);
	box-shadow:
		0 4px 6px -1px rgba(0, 0, 0, 0.05),
		0 2px 4px -1px rgba(0, 0, 0, 0.03);
}
</style>

<script>
import Badge from 'io7/components/tailux/ui/Badge.vue';
import Button from 'io7/components/tailux/ui/Button.vue';
import Circlebar from 'io7/components/tailux/loading/Circlebar.vue';
import Switch from 'io7/components/tailux/ui/form/Switch.vue';
import Input from 'io7/components/tailux/ui/form/Input.vue';
import Table from 'io7/components/tailux/ui/Table.vue';

export default {
	components: {
		Badge,
		Button,
		Circlebar,
		Switch,
		Input,
		Table,
	},
	data() {
		return {
			isSyncing: false,

			lastSyncTime: new Date().toLocaleTimeString('ru-RU'),

			stats: {
				duplicatesPrevented: 1420,
				failedTokens: 3,
			},

			webhooks: [
				{
					id: 1,
					active: true,
					event: 'order.created',
					url: 'https://api.namakz.com/v1/webhook/orders',
					testing: false,
				},
				{
					id: 2,
					active: true,
					event: 'return.created',
					url: 'https://api.namakz.com/v1/webhook/refunds',
					testing: false,
				},
				{
					id: 3,
					active: true,
					event: 'inventory.updated',
					url: 'https://api.namakz.com/v1/sync/stock',
					testing: false,
				},
				{
					id: 4,
					active: false,
					event: 'user.registered',
					url: 'https://api.namakz.com/v1/hooks/mlm-tree',
					testing: false,
				},
				{
					id: 5,
					active: true,
					event: 'fabric.reserved',
					url: 'https://api.mektepforma.kz/uber-manuf/sync',
					testing: false,
				},
			],

			apiLogs: [
				{
					id: 1,
					time: '16:15:22.100',
					method: 'POST',
					endpoint: '/api/v1/stock/reserve',
					status: 200,
					payload: '{"sku": "MK-F-42", "qty": 1} - OK',
				},
				{
					id: 2,
					time: '16:15:18.405',
					method: 'GET',
					endpoint: '/api/v1/catalog/sync',
					status: 200,
					payload: 'Fetched 1450 items',
				},
				{
					id: 3,
					time: '16:14:50.001',
					method: 'POST',
					endpoint: '/api/v1/orders/webhook',
					status: 409,
					payload: 'Conflict: Idempotency-Key "req_88f2" already processed',
				},
				{
					id: 4,
					time: '16:13:12.992',
					method: 'PUT',
					endpoint: '/api/v1/mlm/accrue',
					status: 200,
					payload: '{"userId": 9012, "amount": 4500} - Success',
				},
			],

			logInterval: null,
		};
	},

	methods: {
		getMethodClass(method) {
			const map = {
				GET: 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
				POST: 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30',
				PUT: 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
				DELETE: 'bg-red-500/20 text-red-400 border border-red-500/30',
			};

			return map[method] || 'bg-slate-700 text-slate-300';
		},

		getStatusColor(status) {
			if (status >= 200 && status < 300) {
				return 'text-emerald-400';
			}

			if (status === 409) {
				return 'text-amber-400';
			}

			if (status >= 400) {
				return 'text-red-400';
			}

			return 'text-slate-400';
		},

		addLog(method, endpoint, status, payload) {
			const now = new Date();

			this.apiLogs.unshift({
				id: Date.now(),
				time: `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}.${now.getMilliseconds().toString().padStart(3, '0')}`,
				method,
				endpoint,
				status,
				payload,
			});

			if (status === 409) {
				this.stats.duplicatesPrevented++;
			}

			if (this.apiLogs.length > 50) {
				this.apiLogs.pop();
			}
		},

		forceSync() {
			this.isSyncing = true;

			this.$noty.info('Форсированное обновление остатков BMC Trade...', {
				layout: 'bottomRight',
				timeout: 3000
			});

			this.addLog(
				'GET',
				'/api/v1/catalog/force-sync',
				202,
				'Sync job enqueued',
			);

			setTimeout(() => {
				this.isSyncing = false;

				this.lastSyncTime = new Date().toLocaleTimeString('ru-RU');

				this.addLog(
					'GET',
					'/api/v1/catalog/force-sync',
					200,
					'Sync completed successfully (14.2s)',
				);

				this.$noty.success('Каталоги успешно обновлены.', {
					layout: 'bottomRight',
					timeout: 3000
				});
			}, 2500);
		},

		testWebhook(wh) {
			wh.testing = true;

			this.addLog(
				'POST',
				wh.url,
				202,
				'Sending test payload...',
			);

			setTimeout(() => {
				wh.testing = false;

				const success = Math.random() > 0.1;

				if (success) {
					this.addLog(
						'POST',
						wh.url,
						200,
						'Webhook received OK (Response time: 142ms)',
					);

					this.$noty.success(`Успешный ответ от ${wh.event}`, {
						layout: 'bottomRight',
						timeout: 3000
					});
				} else {
					this.addLog(
						'POST',
						wh.url,
						500,
						'Webhook timeout or internal error',
					);

					this.$noty.warning(`Ошибка соединения с эндпоинтом ${wh.event}`, {
						layout: 'bottomRight',
						timeout: 3000
					});
				}
			}, 1000);
		},
	},

	mounted() {
		this.logInterval = setInterval(() => {
			if (Math.random() > 0.6) {
				const methods = ['POST', 'GET', 'POST', 'PUT'];

				const endpoints = [
					'/api/v1/stock/reserve',
					'/api/v1/sync/stock',
					'/api/v1/webhook/orders',
					'/api/v1/mlm/compress',
				];

				const statuses = [200, 200, 200, 200, 201, 409];

				const r = Math.floor(Math.random() * methods.length);

				const stat = statuses[Math.floor(Math.random() * statuses.length)];

				const msg = stat === 409
					? 'Conflict: Idempotency-Key duplicate'
					: 'Payload processed OK';

				this.addLog(
					methods[r],
					endpoints[r],
					stat,
					msg,
				);
			}
		}, 2000);
	},

	beforeUnmount() {
		clearInterval(this.logInterval);
	},
};
</script>

