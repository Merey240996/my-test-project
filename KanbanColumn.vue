<template>
	<div
		ref="columnRef"
		class="kanban-column"
		:class="{ 'column-dragging': !readonly && colState.type === 'dragging' }"
	>
		<DropIndicator
			v-if="!readonly && colState.type === 'is-column-over' && colState.closestEdge === 'left'"
			edge="left"
			gap="13px"
		/>

		<div
			class="column-inner"
			:class="{ 'column-over-card': !readonly && cardState.type === 'dragging' }"
		>
			<div ref="headerRef" class="column-header" :class="{ 'column-header--readonly': readonly }">
				<div class="column-header-left">
					<div
						class="column-avatar"
						:style="{ background: avatarBg, color: avatarColor }"
					>
						<span v-if="data.icon" v-html="data.icon" class="avatar-icon"></span>
						<svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M11 11.5v-1a1 1 0 0 1 1-1h3"/><path d="M13 13.5v1a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h3"/><path d="M8 6V4a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-2"/>
						</svg>
					</div>
					<span class="column-title">{{ data.label }}</span>
					<span class="column-count">{{ tasks.length }}</span>
				</div>
				<button
					v-if="!readonly && allowDelete"
					class="column-delete-btn"
					type="button"
					draggable="false"
					title="Удалить этап"
					@mousedown.stop
					@dragstart.stop.prevent
					@click.stop="$emit('delete-column', data)"
				>
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
					</svg>
				</button>
			</div>

			<div ref="tasksWrapperRef" class="tasks-wrapper">
				<template v-if="tasks.length > 0">
					<KanbanTaskCard
						v-for="(task, i) in tasks"
						:key="task[idKey]"
						:data="task"
						:column-data="data"
						:index="i"
						:instance-id="instanceId"
						:item-key="itemKey"
						:id-key="idKey"
						:readonly="readonly"
						@click="$emit('task-click', task)"
					>
						<template v-if="$slots['card-content']" #default="slotProps">
							<slot name="card-content" v-bind="slotProps" />
						</template>
					</KanbanTaskCard>
				</template>
				<div v-else class="empty-state">
					<span class="empty-emoji">👀</span>
					<span class="empty-text">Нет элементов</span>
					<button v-if="!readonly" class="empty-add-btn" @click="$emit('add-task', data.id)">
						Добавьте элементы сюда
					</button>
				</div>
			</div>

			<button v-if="!readonly" class="add-task-btn" @click="$emit('add-task', data.id)">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
				{{ addButtonText }}
			</button>
		</div>

		<DropIndicator
			v-if="!readonly && colState.type === 'is-column-over' && colState.closestEdge === 'right'"
			edge="right"
			gap="13px"
		/>
	</div>
</template>

<style lang="scss">
.kanban-column {
	position: relative;
	flex-shrink: 0;
	width: 288px;
	display: flex;
	flex-direction: column;
	border-radius: 10px;
	padding: 0 2px;
	margin: 0 -2px;
	transition: background 0.15s;
}

.column-inner {
	position: relative;
	display: flex;
	flex-direction: column;
	max-height: calc(100vh - 260px);
	min-height: 200px;
	border-radius: 10px;
	transition: background 0.15s;
}

.column-over-card { background: #f1f5f9; }
.column-dragging .column-inner { opacity: 0.6; }

.column-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 2px 8px;
	cursor: grab;
	position: relative;
}
.column-header:active { cursor: grabbing; }

.column-header-left {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
	flex: 1;
}

.column-delete-btn {
	flex-shrink: 0;
	width: 24px;
	height: 24px;
	border-radius: 6px;
	border: none;
	background: transparent;
	color: #cbd5e1;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	opacity: 0;
	transition: opacity 0.15s, background 0.15s, color 0.15s;
}
.column-header:hover .column-delete-btn {
	opacity: 1;
}
.column-delete-btn:hover {
	background: #fef2f2;
	color: #dc2626;
}

.column-avatar {
	width: 32px;
	height: 32px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.avatar-icon { display: flex; align-items: center; }

.column-title {
	font-size: 15px;
	color: #1f2937;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.column-count {
	background: #f1f5f9;
	color: #6b7280;
	font-size: 11px;
	font-weight: 700;
	border-radius: 20px;
	padding: 1px 7px;
	flex-shrink: 0;
}

.tasks-wrapper {
	flex: 1;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 10px;
	padding: 4px 0;
	scrollbar-width: thin;
	scrollbar-color: #cbd5e1 transparent;
}
.tasks-wrapper::-webkit-scrollbar { width: 4px; }
.tasks-wrapper::-webkit-scrollbar-track { background: transparent; }
.tasks-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 6px;
	padding: 40px 16px;
}
.empty-emoji { font-size: 24px; }
.empty-text { font-size: 16px; color: #6b7280; }
.empty-add-btn {
	background: none;
	border: none;
	cursor: pointer;
	font-size: 13px;
	color: #9ca3af;
	text-align: center;
	padding: 0;
}
.empty-add-btn:hover { color: #6b7280; text-decoration: underline; }

.add-task-btn {
	margin-top: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	width: 100%;
	padding: 9px;
	background: none;
	border: 1.5px dashed #e2e8f0;
	border-radius: 8px;
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
	color: #9ca3af;
	transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.add-task-btn:hover {
	border-color: #94a3b8;
	color: #6b7280;
	background: #f8fafc;
}
.column-header--readonly {
    cursor: default;
}
.column-header--readonly:active {
    cursor: default;
}
</style>

<script>
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine'
import { draggable, dropTargetForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter'
import { attachClosestEdge, extractClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge'
import { autoScrollForElements } from '@atlaskit/pragmatic-drag-and-drop-auto-scroll/element'
import KanbanTaskCard from './KanbanTaskCard.vue'
import DropIndicator from 'io7/components/tailux/shared/DropIndicator.vue'

const IDLE = { type: 'idle' }
const DRAGGING = { type: 'dragging' }

export default {
	name: 'KanbanColumn',
	components: { KanbanTaskCard, DropIndicator },

	props: {
		data:          { type: Object,  required: true },
		tasks:         { type: Array,   default: () => [] },
		instanceId:    { type: Symbol,  required: true },
		index:         { type: Number,  default: 0 },
		itemKey:       { type: String,  default: 'stage' },
		idKey:         { type: String,  default: 'id' },
		addButtonText: { type: String,  default: 'Добавить' },
		allowDelete:   { type: Boolean, default: false },
	},

	emits: ['task-click', 'add-task', 'delete-column'],

	data() {
		return {
			colState:  { ...IDLE },
			cardState: { ...IDLE },
			cleanup:   null,
		}
	},

	computed: {
		avatarBg() {
			const colorMap = {
				blue: '#eff6ff', emerald: '#ecfdf5', green: '#ecfdf5',
				red: '#fef2f2', yellow: '#fefce8', neutral: '#f3f4f6',
			}
			return colorMap[this.data.color] || '#f3f4f6'
		},
		avatarColor() {
			const colorMap = {
				blue: '#2563eb', emerald: '#059669', green: '#059669',
				red: '#dc2626', yellow: '#d97706', neutral: '#6b7280',
			}
			return colorMap[this.data.color] || '#6b7280'
		},
	},

	mounted() {
		this.$nextTick(() => {
			if (!this.readonly) this.initDragAndDrop()
		})
	},

	beforeUnmount() {
		if (this.cleanup) this.cleanup()
	},

	methods: {
		initDragAndDrop() {
			const column = this.$refs.columnRef
			const header = this.$refs.headerRef
			const tasksWrapper = this.$refs.tasksWrapperRef
			if (!column || !header || !tasksWrapper) return

			const { id } = this.data
			const instanceId = this.instanceId

			this.cleanup = combine(
				draggable({
					element: column,
					dragHandle: header,
					getInitialData: () => ({ columnId: id, type: 'column', instanceId }),
					onDragStart: () => { this.colState = { ...DRAGGING } },
					onDrop: () => { this.colState = { ...IDLE } },
				}),

				dropTargetForElements({
					element: tasksWrapper,
					getData: () => ({ columnId: id }),
					canDrop: ({ source }) =>
						source.data.instanceId === instanceId && source.data.type === 'card',
					getIsSticky: () => true,
					onDragEnter: () => { this.cardState = { ...DRAGGING } },
					onDragLeave: () => { this.cardState = { ...IDLE } },
					onDragStart: () => { this.cardState = { ...DRAGGING } },
					onDrop: () => { this.cardState = { ...IDLE } },
				}),

				dropTargetForElements({
					element: column,
					canDrop: ({ source }) =>
						source.data.instanceId === instanceId && source.data.type === 'column',
					getIsSticky: () => true,
					getData: ({ input, element }) =>
						attachClosestEdge({ columnId: id }, {
							input, element, allowedEdges: ['left', 'right'],
						}),
					onDragEnter: (args) => {
						this.colState = {
							type: 'is-column-over',
							closestEdge: extractClosestEdge(args.self.data),
						}
					},
					onDrag: (args) => {
						const closestEdge = extractClosestEdge(args.self.data)
						if (
							this.colState.type === 'is-column-over' &&
							this.colState.closestEdge === closestEdge
						) return
						this.colState = { type: 'is-column-over', closestEdge }
					},
					onDragLeave: () => { this.colState = { ...IDLE } },
					onDrop: () => { this.colState = { ...IDLE } },
				}),

				autoScrollForElements({
					element: tasksWrapper,
					canScroll: ({ source }) =>
						source.data.instanceId === instanceId && source.data.type === 'card',
				}),
			)
		},
	},
}
</script>
