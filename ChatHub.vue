<template>
	<main class="main-content transition-content">
		<div class="transition-content overflow-hidden px-(--margin-x)">

			<div class="relative mt-5 space-y-6 lg:mt-6">

				<!-- Page Title Row -->
				<header class="h-16 flex items-center justify-between px-8 border-b border-slate-200 glass-panel z-10 shrink-0">
					<h1 class="text-2xl font-bold text-slate-800">Коммуникационный Хаб</h1>
					<div class="flex items-center gap-4 text-xs font-semibold">
						<Badge
							variant="soft"
							color="success"
							class="flex items-center gap-1.5 rounded-full border border-this-darker/20 dark:border-this-lighter/20"
						>
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M6.014 8.00613C6.12827 7.1024 7.30277 5.87414 8.23488 6.01043L8.23339 6.00894C9.14051 6.18132 9.85859 7.74261 10.2635 8.44465C10.5504 8.95402 10.3641 9.4701 10.0965 9.68787C9.7355 9.97883 9.17099 10.3803 9.28943 10.7834C9.5 11.5 12 14 13.2296 14.7107C13.695 14.9797 14.0325 14.2702 14.3207 13.9067C14.5301 13.6271 15.0466 13.46 15.5548 13.736C16.3138 14.178 17.0288 14.6917 17.69 15.27C18.0202 15.546 18.0977 15.9539 17.8689 16.385C17.4659 17.1443 16.3003 18.1456 15.4542 17.9421C13.9764 17.5868 8 15.27 6.08033 8.55801C5.97237 8.24048 5.99955 8.12044 6.014 8.00613Z" fill="currentColor"/>
								<path fill-rule="evenodd" clip-rule="evenodd" d="M12 23C10.7764 23 10.0994 22.8687 9 22.5L6.89443 23.5528C5.56462 24.2177 4 23.2507 4 21.7639V19.5C1.84655 17.492 1 15.1767 1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12C23 18.0751 18.0751 23 12 23ZM6 18.6303L5.36395 18.0372C3.69087 16.4772 3 14.7331 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21C11.0143 21 10.552 20.911 9.63595 20.6038L8.84847 20.3397L6 21.7639V18.6303Z" fill="currentColor"/>
							</svg>
							WA Active
						</Badge>
						<div class="cursor-pointer" @click="showTgPanel = !showTgPanel">
							<Badge
								variant="soft"
								color="primary"
								class="flex items-center gap-1.5 rounded-full border border-this-darker/20 dark:border-this-lighter/20"
							>
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M23.1117 4.49449C23.4296 2.94472 21.9074 1.65683 20.4317 2.227L2.3425 9.21601C0.694517 9.85273 0.621087 12.1572 2.22518 12.8975L6.1645 14.7157L8.03849 21.2746C8.13583 21.6153 8.40618 21.8791 8.74917 21.968C9.09216 22.0568 9.45658 21.9576 9.70712 21.707L12.5938 18.8203L16.6375 21.8531C17.8113 22.7334 19.5019 22.0922 19.7967 20.6549L23.1117 4.49449ZM3.0633 11.0816L21.1525 4.0926L17.8375 20.2531L13.1 16.6999C12.7019 16.4013 12.1448 16.4409 11.7929 16.7928L10.5565 18.0292L10.928 15.9861L18.2071 8.70703C18.5614 8.35278 18.5988 7.79106 18.2947 7.39293C17.9906 6.99479 17.4389 6.88312 17.0039 7.13168L6.95124 12.876L3.0633 11.0816ZM8.17695 14.4791L8.78333 16.6015L9.01614 15.321C9.05253 15.1209 9.14908 14.9366 9.29291 14.7928L11.5128 12.573L8.17695 14.4791Z" fill="currentColor"/>
								</svg>
								{{ tgChannel ? 'TG подключён' : 'TG не подключён' }}
							</Badge>
						</div>
						<Badge
							variant="outlined"
							class="flex items-center gap-1.5 rounded-full"
						>
							<span class="w-2 h-2 rounded-full bg-blue-500 pulse-green"></span> ВАТС Online
						</Badge>
					</div>
				</header>

				<div v-if="showTgPanel" class="glass-panel rounded-xl border border-slate-200 p-4 flex flex-wrap items-end gap-3 bg-white">
					<template v-if="tgChannel">
						<div class="text-sm text-slate-700">
							<span class="font-bold">Telegram-бот подключён:</span> {{ tgChannel.name }}
						</div>
						<Button :disabled="tgBusy" @click="disconnectTelegram">Отключить</Button>
					</template>
					<template v-else>
						<div class="flex-1 min-w-[260px]">
							<label class="block text-xs font-bold text-slate-500 mb-1">Токен бота (из @BotFather)</label>
							<Input v-model="tgToken" type="password" placeholder="123456789:AA..." class="w-full" />
						</div>
						<Button color="primary" :disabled="tgBusy || !tgToken.trim()" @click="connectTelegram">Подключить</Button>
					</template>
				</div>

				<!-- Hub Layout -->
				<div class="flex gap-4 overflow-hidden min-h-0" style="height: calc(100vh - 8rem);">

				<!-- LEFT PANEL: Список диалогов -->
				<div class="w-80 shrink-0 glass-panel rounded-xl flex flex-col border border-slate-200 shadow-sm overflow-hidden">
					<!-- Search & Filters -->
					<div class="p-4 border-b border-slate-100 bg-white">
						<div class="relative mb-3">
							<Input
								type="text"
								v-model="searchQuery"
								placeholder="Поиск (Имя, Телефон)..."
							>
								<template #prefix>
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
								</template>
							</Input>
						</div>
						<TabGroup>
							<TabList class="flex w-full border-b border-slate-200">
								<Tab class="flex-1">
									<Button unstyled class="w-full py-2 text-sm font-medium border-b-2 border-primary-600 text-primary-600">
										Все
									</Button>
								</Tab>
								<Tab class="flex-1">
									<Button unstyled class="w-full py-2 gap-1.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-800">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M6.014 8.00613C6.12827 7.1024 7.30277 5.87414 8.23488 6.01043L8.23339 6.00894C9.14051 6.18132 9.85859 7.74261 10.2635 8.44465C10.5504 8.95402 10.3641 9.4701 10.0965 9.68787C9.7355 9.97883 9.17099 10.3803 9.28943 10.7834C9.5 11.5 12 14 13.2296 14.7107C13.695 14.9797 14.0325 14.2702 14.3207 13.9067C14.5301 13.6271 15.0466 13.46 15.5548 13.736C16.3138 14.178 17.0288 14.6917 17.69 15.27C18.0202 15.546 18.0977 15.9539 17.8689 16.385C17.4659 17.1443 16.3003 18.1456 15.4542 17.9421C13.9764 17.5868 8 15.27 6.08033 8.55801C5.97237 8.24048 5.99955 8.12044 6.014 8.00613Z" fill="currentColor"/>
										<path fill-rule="evenodd" clip-rule="evenodd" d="M12 23C10.7764 23 10.0994 22.8687 9 22.5L6.89443 23.5528C5.56462 24.2177 4 23.2507 4 21.7639V19.5C1.84655 17.492 1 15.1767 1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12C23 18.0751 18.0751 23 12 23ZM6 18.6303L5.36395 18.0372C3.69087 16.4772 3 14.7331 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21C11.0143 21 10.552 20.911 9.63595 20.6038L8.84847 20.3397L6 21.7639V18.6303Z" fill="currentColor"/>
									</svg>
										WA
									</Button>
								</Tab>
								<Tab class="flex-1">
									<Button unstyled class="w-full py-2 gap-1.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-800">
										<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path fill-rule="evenodd" clip-rule="evenodd" d="M23.1117 4.49449C23.4296 2.94472 21.9074 1.65683 20.4317 2.227L2.3425 9.21601C0.694517 9.85273 0.621087 12.1572 2.22518 12.8975L6.1645 14.7157L8.03849 21.2746C8.13583 21.6153 8.40618 21.8791 8.74917 21.968C9.09216 22.0568 9.45658 21.9576 9.70712 21.707L12.5938 18.8203L16.6375 21.8531C17.8113 22.7334 19.5019 22.0922 19.7967 20.6549L23.1117 4.49449ZM3.0633 11.0816L21.1525 4.0926L17.8375 20.2531L13.1 16.6999C12.7019 16.4013 12.1448 16.4409 11.7929 16.7928L10.5565 18.0292L10.928 15.9861L18.2071 8.70703C18.5614 8.35278 18.5988 7.79106 18.2947 7.39293C17.9906 6.99479 17.4389 6.88312 17.0039 7.13168L6.95124 12.876L3.0633 11.0816ZM8.17695 14.4791L8.78333 16.6015L9.01614 15.321C9.05253 15.1209 9.14908 14.9366 9.29291 14.7928L11.5128 12.573L8.17695 14.4791Z" fill="currentColor"/>
										</svg> 
										TG
									</Button>
								</Tab>
							</TabList>
						</TabGroup>
					</div>

					<!-- Chat List -->
					<div class="flex-1 overflow-y-auto custom-scrollbar bg-white">
						<div
							v-for="chat in filteredDialogs"
							:key="chat.id"
							@click="selectChat(chat)"
							class="px-4 py-3 border-b border-slate-50 cursor-pointer hover:bg-slate-50 transition-colors flex gap-3 relative"
							:class="{ 'bg-blue-50/50 hover:bg-blue-50': activeChat?.id === chat.id }"
						>
							<!-- Active Indicator -->
							<div v-if="activeChat?.id === chat.id" class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>

							<div class="relative shrink-0">
								<div class="w-11 h-11 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm border border-slate-200">{{ initials(chat.name) }}</div>
								<div
									class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white flex items-center justify-center text-[8px] text-white shadow-sm"
									:class="chat.platform === 'WA' ? 'bg-emerald-500' : 'bg-blue-500'"
								>
									<svg v-if="chat.platform === 'WA'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
										<path d="M19.05 4.91A9.82 9.82 0 0 0 12.03 2C6.55 2 2.1 6.45 2.1 11.93c0 1.75.46 3.47 1.33 4.98L2 22l5.24-1.37a9.9 9.9 0 0 0 4.79 1.22h.01c5.48 0 9.93-4.45 9.93-9.93a9.83 9.83 0 0 0-2.92-7.01zm-7.02 15.26h-.01a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.11.81.83-3.03-.2-.31a8.24 8.24 0 1 1 6.97 3.85zm4.51-6.15c-.25-.12-1.47-.72-1.7-.8-.23-.09-.39-.12-.56.12-.17.25-.64.8-.79.96-.15.17-.3.19-.55.07-.25-.12-1.05-.39-2-1.25-.74-.66-1.24-1.48-1.39-1.73-.15-.25-.02-.38.11-.5.11-.11.25-.3.37-.44.12-.15.16-.25.25-.42.08-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.83-.2-.48-.4-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.44 1.02 2.61c.12.17 1.77 2.71 4.29 3.8.6.26 1.07.42 1.43.54.6.19 1.14.16 1.57.1.48-.07 1.47-.6 1.68-1.17.21-.58.21-1.07.15-1.17-.06-.1-.23-.16-.48-.28z"/>
									</svg>
									<svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
										<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8-1.64 7.73c-.12.55-.44.69-.89.43l-2.47-1.82-1.19 1.14c-.13.13-.24.24-.49.24l.18-2.56 4.66-4.21c.2-.18-.04-.28-.31-.1l-5.76 3.63-2.48-.78c-.54-.17-.55-.54.11-.8l9.69-3.74c.45-.16.84.1.69.84z"/>
									</svg>
								</div>
							</div>
							<div class="flex-1 min-w-0">
								<div class="flex justify-between items-baseline mb-0.5">
									<h4 class="font-bold text-slate-800 text-sm truncate pr-2">{{ chat.name }}</h4>
									<span class="text-[10px] font-medium" :class="chat.unread > 0 ? 'text-blue-600' : 'text-slate-400'">{{ chat.time }}</span>
								</div>
								<div class="flex justify-between items-center">
									<p class="text-xs text-slate-500 truncate mr-2" :class="{ 'font-semibold text-slate-700': chat.unread > 0 }">{{ chat.lastMessage }}</p>
									<span v-if="chat.unread > 0" class="shrink-0 bg-blue-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center">
										{{ chat.unread }}
									</span>
									<svg v-else-if="chat.isReply" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-check-icon lucide-check-check"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- CENTER PANEL: Активный Чат -->
				<div class="flex-1 glass-panel rounded-xl flex flex-col border border-slate-200 shadow-sm overflow-hidden min-w-[400px]">
					<template v-if="activeChat">
						<!-- Chat Header -->
						<div class="px-6 py-4 border-b border-slate-200 bg-white/80 flex justify-between items-center shrink-0">
							<div class="flex items-center gap-4">
								<div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm border border-slate-200">{{ initials(activeChat.name) }}</div>
								<div>
									<h3 class="font-bold text-slate-800 flex items-center gap-2">
										{{ activeChat.name }}
										<Badge
											class="px-2 py-0.5 rounded text-[10px] uppercase font-bold border"
											:class="activeChat.tag === 'VIP' ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
										>
											{{ activeChat.tag }}
										</Badge>
									</h3>
									<p class="text-xs text-slate-500 flex items-center gap-2 mt-0.5">
										<span>Telegram</span>
									</p>
								</div>
							</div>
							<div class="flex gap-2">
								<Button
									isIcon
									class="w-9 h-9 flex items-center justify-center"
								>
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
								</Button>
								<Button @click="copyPhone" 									
									isIcon
									class="w-9 h-9 flex items-center justify-center"
								>
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone-icon lucide-phone"><path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/></svg>
								</Button>
							</div>
						</div>

						<!-- Chat Messages Area -->
						<div class="flex-1 overflow-y-auto custom-scrollbar p-6 bg-slate-50/50 flex flex-col gap-4" ref="chatContainer">
							
							<!-- Messages -->
							<div v-for="msg in messages" :key="msg.id" class="flex" :class="msg.isMine ? 'justify-end' : 'justify-start'">

								<!-- Received Message -->
								<div v-if="!msg.isMine" class="max-w-[75%] relative chat-bubble-tail-left">
									<div class="bg-slate-100 border border-slate-200 text-slate-800 p-3 rounded-2xl rounded-tl-sm text-sm shadow-sm">
										<div v-if="msg.sentiment" class="flex items-center gap-1.5 mb-1 text-[9px] font-bold uppercase tracking-wider" :class="msg.sentiment === 'positive' ? 'text-blue-500' : 'text-amber-500'">
											<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bot-icon lucide-bot"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg> AI Сентимент: {{ msg.sentiment }}
										</div>
										<div class="whitespace-pre-wrap break-words">{{ msg.text }}</div>
									</div>
									<div class="text-[10px] text-slate-400 mt-1 ml-1 font-medium">{{ msg.time }}</div>
								</div>

								<!-- Sent Message -->
								<div v-else class="max-w-[75%] relative chat-bubble-tail-right">
									<div class="bg-blue-100 border border-blue-200 text-blue-900 p-3 rounded-2xl rounded-tr-sm text-sm shadow-sm">
										<div v-if="msg.attachment" class="mb-2 bg-white rounded-lg p-2 border border-blue-200 flex items-center gap-3">
											<div class="w-8 h-8 rounded bg-red-50 text-red-500 flex items-center justify-center text-lg">
												<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text-icon lucide-file-text"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
											</div>
											<div class="flex-1 min-w-0">
												<div class="text-xs font-bold text-slate-800 truncate">{{ msg.attachment.name }}</div>
												<div class="text-[10px] text-slate-500">{{ msg.attachment.size }}</div>
											</div>
										</div>
										<div v-html="msg.text"></div>
									</div>
									<div class="flex items-center gap-1.5 justify-end text-[10px] text-slate-400 mt-1 mr-1 text-right font-medium">
										{{ msg.time }} <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-check-icon lucide-check-check"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
									</div>
								</div>
							</div>
						</div>

						<!-- Chat Input -->
						<div class="p-4 border-t border-slate-200 bg-white shrink-0">
							<!-- Quick Actions Bar -->
							<div class="flex gap-2 mb-3">
								<Button unstyled @click="sendKP" class="text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
									<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-icon lucide-file"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg> 
									Отправить КП (1 клик)
								</Button>
								<Button unstyled class="text-xs font-bold bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
									<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-zap-icon lucide-zap"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg> 
									Шаблоны (FAQ)
								</Button>
							</div>

							<div class="flex items-end gap-2 bg-slate-50 border border-slate-200 rounded-xl p-2 focus-within:ring-1 focus-within:ring-blue-500 focus-within:border-blue-500 transition-shadow">
								<Button unstyled class="p-2 text-slate-400 hover:text-slate-600 transition-colors shrink-0">
									<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip-icon lucide-paperclip"><path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/></svg>
								</Button>
								<textarea
									v-model="newMessage"
									@keydown.enter.exact.prevent="sendMessage"
									placeholder="Введите сообщение..."
									class="w-full bg-transparent resize-none outline-none text-sm text-slate-800 max-h-32 py-2"
									rows="1"
									ref="chatInput"
								></textarea>
								<Button unstyled class="p-2 text-slate-400 hover:text-slate-600 transition-colors shrink-0">
									<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-smile-icon lucide-smile"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
								</Button>
								<Button
									@click="sendMessage"
									:disabled="!newMessage.trim() || sending"
									class="p-2 bg-blue-500 hover:bg-blue-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-lg transition-colors shrink-0"
								>
									<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send-horizontal-icon lucide-send-horizontal"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg>
								</Button>
							</div>
						</div>
					</template>
					<div v-else class="flex-1 flex flex-col items-center justify-center text-slate-400">
						<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-circle-icon lucide-message-circle"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/></svg>
						<p class="font-medium">Выберите диалог для начала работы</p>
					</div>
				</div>

				<!-- RIGHT PANEL: ВАТС Софтфон & Аудиоплеер -->
				<div class="w-80 shrink-0 flex flex-col gap-4">

					<!-- Софтфон (Dialer) -->
					<div class="glass-panel rounded-xl border border-slate-200 shadow-sm p-5 bg-white">
						<div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
							<h3 class="font-bold text-slate-800">IP Телефония</h3>
							<Badge
								variant="soft"
								color="primary"
								class="flex items-center gap-1.5 text-xs font-bold border border-this-darker/20 dark:border-this-lighter/20"
							>
								<div class="w-1.5 h-1.5 bg-blue-500 rounded-full"></div> Готов
							</Badge>
						</div>

						<!-- Number Input -->
						<div class="relative mb-6 flex items-center">
							<Input
								v-model="dialNumber"
								type="text"
								:cleave-options="{
									numericOnly: true,
									delimiters: ['(', ') ', '-', '-'],
									blocks: [0, 3, 3, 2, 2],
								}"
								placeholder="(700) 000-00-00"
								class="w-full text-center text-xl font-mono font-bold"
								ref="dialInput"
							>
								<template #prefix>
									<span class="font-mono font-bold text-xl text-slate-800 select-none">+7</span>
								</template>
							</Input>
						</div>

						<!-- Dialpad Grid -->
						<div class="grid grid-cols-3 gap-3 mb-6">
							<Button
								v-for="num in ['1','2','3','4','5','6','7','8','9','*','0','#']"
								:key="num"
								@click="appendDial(num)"
								class="h-12 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold text-lg transition-colors flex items-center justify-center"
							>
								{{ num }}
							</Button>
						</div>

						<!-- Call Button -->
						<div class="flex justify-center">
							<Button
								unstyled
								@click="simulateCall"
								:class="phoneState === 'idle' ? 'bg-blue-500 hover:bg-blue-600 shadow-blue-500/30' : 'bg-red-500 hover:bg-red-600 shadow-red-500/30'"
								class="w-16 h-16 rounded-full text-white text-xl shadow-lg transition-all flex items-center justify-center transform hover:scale-105"
							>
								<svg v-if="phoneState === 'idle'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone-icon lucide-phone"><path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/></svg>
								<svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone-off-icon lucide-phone-off"><path d="M10.1 13.9a14 14 0 0 0 3.732 2.668 1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2 18 18 0 0 1-12.728-5.272"/><path d="M22 2 2 22"/><path d="M4.76 13.582A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 .244.473"/></svg>
							</Button>
						</div>

						<div v-if="phoneState === 'in-call'" class="mt-4 text-center animate-fade-in">
							<div class="text-sm font-bold text-blue-600 mb-1">Разговор... 00:14</div>
							<div class="flex justify-center gap-3">
								<Button unstyled class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs flex items-center justify-center">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mic-off-icon lucide-mic-off"><path d="M12 19v3"/><path d="M15 9.34V5a3 3 0 0 0-5.68-1.33"/>
										<path d="M16.95 16.95A7 7 0 0 1 5 12v-2"/><path d="M18.89 13.23A7 7 0 0 0 19 12v-2"/>
										<path d="m2 2 20 20"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12"/>
									</svg>
								</Button>
								<Button unstyled class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs flex items-center justify-center">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pause-icon lucide-pause">
										<rect x="14" y="3" width="5" height="18" rx="1"/>
										<rect x="5" y="3" width="5" height="18" rx="1"/>
									</svg>
								</Button>
							</div>
						</div>
					</div>

					<!-- Аудиоплеер и История звонков -->
					<div class="glass-panel rounded-xl border border-slate-200 shadow-sm flex-1 flex flex-col overflow-hidden bg-white">
						<div class="px-5 py-3 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
							<h3 class="font-bold text-sm text-slate-800">История звонков</h3>
							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw-icon lucide-rotate-ccw"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
						</div>

						<div class="flex-1 overflow-y-auto custom-scrollbar p-2">
							<div
								v-for="call in callHistory"
								:key="call.id"
								class="p-3 border-b border-slate-50 hover:bg-slate-50 transition-colors group rounded-lg"
							>
								<div class="flex justify-between items-start mb-1">
									<div class="flex items-center gap-2">
										<svg v-if="call.type === 'in'" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-arrow-out-up-right-icon lucide-square-arrow-out-up-right"><path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/><path d="m21 3-9 9"/><path d="M15 3h6v6"/></svg>
										<svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out-icon lucide-log-out"><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
										<span class="text-sm font-bold text-slate-800">{{ call.name }}</span>
									</div>
									<span class="text-[10px] text-slate-400 font-medium">{{ call.time }}</span>
								</div>
								<div class="text-xs font-mono text-slate-500 mb-2">{{ call.phone }}</div>

								<!-- Smart Audio Player UI -->
								<div class="bg-slate-100 rounded-lg p-2 border border-slate-200">
									<div class="flex items-center gap-2 mb-1.5">
										<Button @click="togglePlay(call)" unstyled class="w-6 h-6 shrink-0 rounded-full bg-white text-blue-600 flex items-center justify-center shadow-sm hover:bg-blue-50 transition-colors">
											<svg v-if="call.playing" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pause-icon lucide-pause"><rect x="14" y="3" width="5" height="18" rx="1"/><rect x="5" y="3" width="5" height="18" rx="1"/></svg>
											<svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play-icon lucide-play"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
										</Button>
										<div class="flex-1">
											<Range class="full" v-model="call.progress" />
										</div>
										<span class="text-[9px] font-mono font-bold text-slate-500 shrink-0 w-8 text-right">{{ call.duration }}</span>
									</div>
									<div class="flex justify-between items-center">
										<span class="text-[9px] text-slate-400">Запись #{{ call.id }}</span>
										<Button @click="toggleSpeed(call)" unstyled class="text-[9px] font-bold bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-600 hover:text-blue-600 transition-colors">
											{{ call.speed }}x
										</Button>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>

			</div>

			</div><!-- /relative mt-5 -->
		</div><!-- /px-(--margin-x) -->
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

.custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.chat-bubble-tail-left::before {
	content: '';
	position: absolute;
	left: -6px;
	top: 12px;
	border-style: solid;
	border-width: 6px 8px 6px 0;
	border-color: transparent #f1f5f9 transparent transparent;
}

.chat-bubble-tail-right::before {
	content: '';
	position: absolute;
	right: -6px;
	top: 12px;
	border-style: solid;
	border-width: 6px 0 6px 8px;
	border-color: transparent transparent transparent #d1fae5;
}

.pulse-green {
	animation: pulseGreen 2s infinite;
}
@keyframes pulseGreen {
	0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
	70%  { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
	100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

input[type=range] {
	-webkit-appearance: none;
	width: 100%;
	background: transparent;
}
input[type=range]::-webkit-slider-thumb {
	-webkit-appearance: none;
	height: 12px;
	width: 12px;
	border-radius: 50%;
	background: #10b981;
	cursor: pointer;
	margin-top: -4px;
}
input[type=range]::-webkit-slider-runnable-track {
	width: 100%;
	height: 4px;
	cursor: pointer;
	background: #e2e8f0;
	border-radius: 2px;
}
</style>

<script>
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue';
import Badge from 'io7/components/tailux/ui/Badge.vue';
import Input from 'io7/components/tailux/ui/form/Input.vue';
import Button from 'io7/components/tailux/ui/Button.vue';
import Range from 'io7/components/tailux/ui/form/Range.vue';
import lib from 'io7/lib';

export default {
	name: 'CommunicationHub',
	mixins: lib.mixins,

	components: {
		TabGroup,
		TabList,
		Tab,
		TabPanels,
		TabPanel,
		Badge,
		Input,
		Button,
		Range,
	},

	data() {
		return {
			searchQuery: '',
			activeChatId: null,
			newMessage: '',
			phoneState: 'idle',
			dialNumber: '',

			dialogs: [],

			messages: [],

			sending: false,
			pollTimer: null,

			callHistory: [
				{ id: 8912, name: 'TOO KazAgroTrade', phone: '+7 (701) 555-11-22', type: 'out', time: 'Сегодня, 10:15', duration: '04:12', playing: false, progress: 0, speed: 1.0 },
				{ id: 8905, name: 'Алина Касымова', phone: '+7 (705) 112-33-44', type: 'in', time: 'Вчера, 16:40', duration: '01:35', playing: false, progress: 0, speed: 1.0 },
				{ id: 8890, name: 'Неизвестный (Астана)', phone: '+7 (7172) 44-55-66', type: 'in', time: 'Вчера, 11:20', duration: '00:45', playing: false, progress: 0, speed: 1.5 },
			],
			showTgPanel: false,
			tgChannel: null,
			tgToken: '',
			tgBusy: false,
		};
	},

	computed: {
		companyPkid() {
			return this.$store.state.io7.auth.selectedCabPkid;
		},

		filteredDialogs() {
			return this.dialogs.filter(d =>
				d.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
				d.phone.includes(this.searchQuery)
			);
		},

		activeChat() {
			return this.dialogs.find(d => d.id === this.activeChatId) || null;
		},
	},
	methods: {
		selectChat(chat) {
			this.activeChatId = chat.id;
			chat.unread = 0;
			this.scrollToBottom();
		},

		scrollToBottom() {
			this.$nextTick(() => {
				const container = this.$refs.chatContainer;
				if (container) container.scrollTop = container.scrollHeight;
			});
		},

		copyPhone() {
			if (!this.activeChat) return;
			// '+7 (701) 555-11-22' → '77015551122' → slice(1) → '7015551122'
			const digits = this.activeChat.phone.replace(/\D/g, ''); // '77015551122'
			this.dialNumber = digits.slice(1); // '7015551122' — 10 цифр
		},

		simulateCall() {
			if (this.dialNumber.replace(/\D/g, '').length < 10) return;
			if (this.phoneState === 'idle') {
				this.phoneState = 'in-call';
			} else {
				this.phoneState = 'idle';
			}
		},

		togglePlay(call) {
			call.playing = !call.playing;
			if (call.playing) {
				call.interval = setInterval(() => {
					call.progress += 2 * call.speed;
					if (call.progress >= 100) {
						call.progress = 0;
						call.playing = false;
						clearInterval(call.interval);
					}
				}, 1000);
			} else {
				clearInterval(call.interval);
			}
		},

		toggleSpeed(call) {
			const speeds = [1.0, 1.5, 2.0];
			const idx = speeds.indexOf(call.speed);
			call.speed = speeds[(idx + 1) % speeds.length];
		},

		appendDial(num) {
			if (num === '*' || num === '#') return;
			const raw = this.dialNumber;
			if (raw.length >= 10) return;
			this.dialNumber = raw + num;
		},

		created() {
			this.loadChannels();
			this.loadDialogs();
			// Простое обновление раз в 5 секунд (пока вкладка открыта)
			this.pollTimer = setInterval(() => {
				if (document.hidden) return;
				this.loadDialogs();
				if (this.activeChatId) this.loadMessages();
			}, 5000);
		},
		beforeUnmount() {
			clearInterval(this.pollTimer);
		},

		async loadChannels() {
			try {
				const d = await this.mfModel.getChatChannels({ company_pkid: this.companyPkid });
				if (d.error_code === 1) {
					this.tgChannel = (d.items || []).find(c => Number(c.type) === 1 && Number(c.status) === 1) || null;
				}
			} catch (e) {
				console.error(e);
			}
		},
		async connectTelegram() {
			if (this.tgBusy || !this.tgToken.trim()) return;
			this.tgBusy = true;
			try {
				const d = await this.mfModel.connectChatChannel({
					company_pkid: this.companyPkid,
					token: this.tgToken.trim(),
				});
				if (d.error_code === 1) {
					this.$noty.success('Telegram-бот подключён', { layout: 'bottomRight', timeout: 3000 });
					this.tgToken = '';
					await this.loadChannels();
				} else {
					this.$noty.error(d.error_str || 'Не удалось подключить бота', { layout: 'bottomRight', timeout: 4000 });
				}
			} catch (e) {
				console.error(e);
				this.$noty.error('Ошибка сети', { layout: 'bottomRight', timeout: 3000 });
			} finally {
				this.tgBusy = false;
			}
		},
		async disconnectTelegram() {
			if (this.tgBusy || !this.tgChannel) return;
			this.tgBusy = true;
			try {
				const d = await this.mfModel.disconnectChatChannel({
					company_pkid: this.companyPkid,
					pkid: this.tgChannel.pkid,
				});
				if (d.error_code === 1) {
					this.$noty.success('Бот отключён', { layout: 'bottomRight', timeout: 3000 });
					await this.loadChannels();
				} else {
					this.$noty.error(d.error_str || 'Не удалось отключить', { layout: 'bottomRight', timeout: 3000 });
				}
			} catch (e) {
				console.error(e);
			} finally {
				this.tgBusy = false;
			}
		},

	},
	async selectChat(chat) {
		this.activeChatId = chat.id;
		chat.unread = 0;
		this.messages = [];
		await this.loadMessages(true);
	},
	async loadDialogs() {
		try {
			const d = await this.mfModel.getChatConversations({ company_pkid: this.companyPkid });
			if (d.error_code !== 1) return;
			this.dialogs = (d.items || []).map(c => ({
				id: Number(c.pkid),
				name: c.title || 'Без имени',
				phone: '',
				platform: Number(c.type) === 2 ? 'WA' : 'TG',
				lastMessage: c.last_message_text || '',
				time: this.formatTime(c.last_message_at),
				unread: this.activeChatId === Number(c.pkid) ? 0 : Number(c.unread_count) || 0,
				isReply: false,
				tag: '',
			}));
		} catch (e) {
			console.error(e);
		}
	},
	async loadMessages(forceScroll = false) {
		const id = this.activeChatId;
		if (!id) return;
		try {
			const d = await this.mfModel.getChatMessages({ company_pkid: this.companyPkid, conversation_pkid: id });
			if (d.error_code !== 1 || id !== this.activeChatId) return;
			const prev = this.messages.length;
			this.messages = (d.items || []).map(m => ({
				id: Number(m.pkid),
				text: m.text || '',
				time: this.formatTime(m.gmtimeAdd, true),
				isMine: Number(m.direction) === 2,
			}));
			if (forceScroll || this.messages.length > prev) this.scrollToBottom();
		} catch (e) {
			console.error(e);
		}
	},
	async sendMessage() {
		const text = this.newMessage.trim();
		if (!text || !this.activeChatId || this.sending) return;
		this.sending = true;
		try {
			const d = await this.mfModel.sendChatMessage({
				company_pkid: this.companyPkid,
				conversation_pkid: this.activeChatId,
				text,
			});
			if (d.error_code === 1) {
				this.newMessage = '';
				const ta = this.$refs.chatInput;
				if (ta) ta.style.height = 'auto';
				await this.loadMessages(true);
				await this.loadDialogs();
			} else {
				this.$noty.error(d.error_str || 'Не удалось отправить сообщение', { layout: 'bottomRight', timeout: 4000 });
			}
		} catch (e) {
			console.error(e);
			this.$noty.error('Ошибка сети', { layout: 'bottomRight', timeout: 3000 });
		} finally {
			this.sending = false;
		}
	},
	sendKP() {
		this.$noty.info('Отправка КП появится позже', { layout: 'bottomRight', timeout: 3000 });
	},
	initials(name) {
		return String(name || '?').trim().split(/\s+/).slice(0, 2).map(w => (w[0] || '').toUpperCase()).join('') || '?';
	},
	formatTime(value, timeOnly = false) {
		if (!value) return '';
		const d = new Date(String(value).replace(' ', 'T') + 'Z');
		if (isNaN(d.getTime())) return '';
		if (timeOnly || d.toDateString() === new Date().toDateString()) {
			return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
		}
		return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
	},
};
</script>

