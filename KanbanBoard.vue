<template>
	<div class="kanban-board">
		<div class="kanban-scroll-container" ref="boardRef">
			<template v-for="(col, index) in kanbanColumns" :key="col.id">
				<div v-if="!readonly && showStageInsertion" class="kanban-insert-slot">
					<button
						class="kanban-insert-btn"
						type="button"
						title="Добавить этап здесь"
						@click="$emit('insert-stage', index)"
					>
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
					</button>
				</div>
				<KanbanColumn
					:data="col"
					:tasks="itemsByStage(col[itemKey])"
					:instance-id="instanceId"
					:index="index"
					:item-key="itemKey"
					:id-key="idKey"
					:allow-delete="showStageInsertion"
					@task-click="$emit('task-click', $event)"
					@add-task="!readonly && $emit('add-task', $event)"
					@delete-column="$emit('delete-stage', $event)"
				>
					<template v-if="$slots['card-content']" #card-content="slotProps">
						<slot name="card-content" v-bind="slotProps" />
					</template>
				</KanbanColumn>
			</template>
			<div v-if="!readonly && showStageInsertion && kanbanColumns.length" class="kanban-insert-slot">
				<button
					class="kanban-insert-btn"
					type="button"
					title="Добавить этап в конец"
					@click="$emit('insert-stage', kanbanColumns.length)"
				>
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
				</button>
			</div>
		</div>
	</div>
</template>

<style lang="scss">
.kanban-board {
  	width: 100%;
}
.kanban-scroll-container {
	display: flex;
	gap: 12px;
	padding-bottom: 16px;
	padding-top: 4px;
	align-items: flex-start;
}
.kanban-scroll-container::-webkit-scrollbar { height: 6px; }
.kanban-scroll-container::-webkit-scrollbar-track { background: transparent; }
.kanban-scroll-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

.kanban-insert-slot {
	flex-shrink: 0;
	width: 20px;
	align-self: stretch;
	position: relative;
	display: flex;
	align-items: flex-start;
	justify-content: center;
}
.kanban-insert-slot::before {
	content: '';
	position: absolute;
	top: 4px;
	bottom: 16px;
	left: 50%;
	width: 2px;
	transform: translateX(-50%);
	background: transparent;
	border-radius: 2px;
	transition: background 0.15s;
}
.kanban-insert-slot:hover::before {
	background: #dbeafe;
}
.kanban-insert-btn {
	position: sticky;
	top: 40px;
	width: 22px;
	height: 22px;
	border-radius: 50%;
	border: none;
	background: #e2e8f0;
	color: #64748b;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	opacity: 0;
	transform: scale(0.8);
	transition: opacity 0.15s, transform 0.15s, background 0.15s, color 0.15s;
	z-index: 5;
}
.kanban-insert-slot:hover .kanban-insert-btn {
	opacity: 1;
	transform: scale(1);
}
.kanban-insert-btn:hover {
	background: #3b82f6;
	color: white;
}
</style>

<script>
import KanbanColumn from './KanbanColumn.vue'
import { autoScrollForElements } from '@atlaskit/pragmatic-drag-and-drop-auto-scroll/element'
import { monitorForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter'
import { extractClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge'

export default {
	name: 'KanbanBoard',
	components: { KanbanColumn },

	props: {
		items:         { type: Array,  default: () => [] },
		kanbanColumns: { type: Array,  default: () => [] },
		itemKey:       { type: String, default: 'stage' },
		idKey:         { type: String, default: 'id' },
		readonly:      { type: Boolean, default: false },
		// Показывать зоны "+" между колонками для создания этапа относительно другого этапа
		showStageInsertion: { type: Boolean, default: false },
	},

	emits: ['task-click', 'add-task', 'update-items', 'update-columns', 'insert-stage', 'delete-stage'],

	data() {
		return {
			instanceId: Symbol('kanban-board'),
			cleanupFns: [],
		}
	},

	mounted() {
		if (this.readonly) return;

		
		const board = this.$refs.boardRef

		const cleanup1 = monitorForElements({
			canMonitor: ({ source }) => source.data.instanceId === this.instanceId,
			onDrop: ({ source, location }) => {
				const sourceData = source.data
				if (sourceData.type === 'card') {
					this.handleCardDrop(sourceData, location)
				} else if (sourceData.type === 'column') {
					this.handleColumnDrop(sourceData, location)
				}
			},
		})

		const cleanup2 = autoScrollForElements({
		element: board,
		canScroll: ({ source }) =>
			source.data.instanceId === this.instanceId && source.data.type === 'column',
		})

		this.cleanupFns = [cleanup1, cleanup2]
	},

	beforeUnmount() {
		this.cleanupFns.forEach(fn => fn && fn())
	},

	methods: {
		itemsByStage(stageValue) {
			return this.items.filter(d => d[this.itemKey] === stageValue)
		},

		handleCardDrop(sourceData, location) {
		const { itemId, columnStage: fromStage } = sourceData

		const cardTarget   = location.current.dropTargets.find(t => t.data.itemId)
		const columnTarget = location.current.dropTargets.find(t => t.data.columnId && !t.data.itemId)

		const toColumnId = cardTarget?.data.columnId ?? columnTarget?.data.columnId
		const toColumn   = this.kanbanColumns.find(c => c.id === toColumnId)
		if (!toColumn) return

		const toStage      = toColumn[this.itemKey]
		const targetItemId = cardTarget?.data.itemId ?? null
		const edge         = extractClosestEdge(cardTarget?.data ?? {})
		const key          = this.itemKey
		const idKey        = this.idKey

		if (fromStage === toStage) {
			if (!targetItemId || itemId === targetItemId) return

			const colItems  = this.items.filter(d => d[key] === toStage)
			const fromIdx   = colItems.findIndex(d => d[idKey] === itemId)
			const toIdx     = colItems.findIndex(d => d[idKey] === targetItemId)
			if (fromIdx === -1 || toIdx === -1) return

			const reordered = [...colItems]
			const [moved]   = reordered.splice(fromIdx, 1)
			const newToIdx  = reordered.findIndex(d => d[idKey] === targetItemId)
			const insertAt  = edge === 'bottom' ? newToIdx + 1 : newToIdx
			reordered.splice(insertAt, 0, moved)

			const result = []
			let ptr = 0
			for (const d of this.items) {
				result.push(d[key] === toStage ? reordered[ptr++] : d)
			}
			this.$emit('update-items', result)

		} else {
				const movedItem = {
					...this.items.find(d => d[idKey] === itemId),
					[key]: toStage,
				}

				const without = this.items.filter(d => d[idKey] !== itemId)

				if (!targetItemId) {
					this.$emit('update-items', [...without, movedItem])
					return
				}

				const targetIdx = without.findIndex(d => d[idKey] === targetItemId)
				if (targetIdx === -1) {
					this.$emit('update-items', [...without, movedItem])
					return
				}

				const insertAt = edge === 'bottom' ? targetIdx + 1 : targetIdx
				const result   = [...without]
				result.splice(insertAt, 0, movedItem)
				this.$emit('update-items', result)
			}
		},

		handleColumnDrop(sourceData, location) {
			const { columnId: fromId } = sourceData
			const target = location.current.dropTargets.find(t => t.data.columnId && t.data.columnId !== fromId)
			if (!target) return

			const toId = target.data.columnId
			const edge = extractClosestEdge(target.data)
			const cols = [...this.kanbanColumns]
			const fromIdx = cols.findIndex(c => c.id === fromId)
			const toIdx   = cols.findIndex(c => c.id === toId)
			if (fromIdx === -1 || toIdx === -1) return

			const [moved]  = cols.splice(fromIdx, 1)
			const newToIdx = cols.findIndex(c => c.id === toId)
			const insertAt = edge === 'left' ? newToIdx : newToIdx + 1
			cols.splice(insertAt, 0, moved)
			this.$emit('update-columns', cols)
		},
	},
}
</script>
