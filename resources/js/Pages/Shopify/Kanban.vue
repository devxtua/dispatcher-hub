<!-- resources/js/Pages/Shopify/Kanban.vue -->
<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch, nextTick } from 'vue'
import Default from '../../Layouts/Default.vue'
import { vDraggable } from 'vue-draggable-plus'
import ColumnCard from '../../Components/Kanban/OrderColumn.vue'
import ColumnModal from '../../Components/Kanban/OrderColumnEditModal.vue'
import { KanbanApi } from '../../api/kanban'
import { normalizeHex } from '../../utils/colors'

defineOptions({ layout: Default })

const props = defineProps({
  columns: { type: Array, default: () => [] },
})

/* ---------- перезапуск Sortable ---------- */
const refreshKey = ref(0)
const bumpRefresh = () => { refreshKey.value = (refreshKey.value + 1) % 1_000_000 }

/* ---------- данные колонок ---------- */
const staticColumn = { id: 'new', name: 'New Orders', desc: 'System column', hex: '#0284c7', tasks: [] }

const firstCol = ref(
  props.columns?.length
    ? { ...props.columns[0], tasks: [ ...(props.columns[0].tasks ?? []) ] }
    : staticColumn
)
const userColumns = ref(
  props.columns?.length
    ? props.columns.slice(1).map(c => ({ ...c, tasks: [ ...(c.tasks ?? []) ] }))
    : []
)

watch(
  () => props.columns,
  (v) => {
    const arr = (v?.length ? v : [staticColumn]).map(c => ({ ...c, tasks: [ ...(c.tasks ?? []) ] }))
    firstCol.value = arr[0]
    userColumns.value = arr.slice(1)
    bumpRefresh()
  }
)

const totalCards = computed(() => {
  const left = firstCol.value?.tasks?.length || 0
  const right = userColumns.value.reduce((n, c) => n + (c.tasks?.length || 0), 0)
  return left + right
})

/* ---------- компактный режим + квадратик ---------- */
const isCompact = ref(false)
const isDraggingTasks = ref(false)
const isOverCompact = ref(false)   // подсветка квадратика

// пустой список Sortable: drop запрещён (return false), но hover стабилен
const compactDummy = ref([])
const compactDragOptions = {
  group: { name: 'kanban-tasks', pull: false, put: true },
  sort: false,
  onMove: () => {
    if (!isDraggingTasks.value) return false
    isOverCompact.value = true
    isCompact.value = true
    return false // запрет дропа в квадратик
  },
}

// нативный ховер (резервный триггер)
function onCompactEnter() {
  if (!isDraggingTasks.value) return
  isOverCompact.value = true
  isCompact.value = true
}
function onCompactLeave() {
  isOverCompact.value = false
}

/* ---------- модалка статусов ---------- */
const showModal = ref(false)
const modalMode = ref('create')
const editingIndex = ref(-1)
const form = ref({ id: '', name: '', desc: '', hex: '#0284c7' })

function openCreateModal () {
  modalMode.value = 'create'
  editingIndex.value = -1
  form.value = { id: 'col-' + Date.now(), name: '', desc: '', hex: '#0284c7' }
  showModal.value = true
}
function openEditModal (col, idx) {
  modalMode.value = 'edit'
  editingIndex.value = idx
  form.value = { id: col.id, name: col.name, desc: col.desc || '', hex: col.hex || '#0284c7' }
  showModal.value = true
}

const saving = ref(false)
async function saveColumn () {
  const name = form.value.name.trim()
  if (!name) return
  const code = form.value.id
  const desc = (form.value.desc || '').trim()
  const hex  = normalizeHex(form.value.hex)

  try {
    saving.value = true
    if (modalMode.value === 'create') {
      const res = await KanbanApi.createColumn({ code, name, desc, hex })
      const c = res?.data?.column ?? { id: code, name, desc, hex }
      userColumns.value.push({ id: c.id, name: c.name, desc: c.desc, hex: c.hex, tasks: [] })
      bumpRefresh()
    } else {
      const idx = editingIndex.value
      if (idx === 0) {
        await KanbanApi.updateColumn(firstCol.value.id, { name, desc, hex })
        firstCol.value = { ...firstCol.value, name, desc, hex }
      } else if (idx > 0) {
        const i = idx - 1
        const cur = userColumns.value[i]
        if (!cur) return
        await KanbanApi.updateColumn(cur.id, { name, desc, hex })
        userColumns.value[i] = { ...cur, name, desc, hex }
      }
      bumpRefresh()
    }
    showModal.value = false
  } catch (e) {
    console.error('Failed to save column', e)
  } finally {
    saving.value = false
  }
}

async function deleteColumn () {
  const idx = editingIndex.value
  if (idx <= 0) return
  const i = idx - 1
  const id = userColumns.value[i]?.id
  if (!id) return
  await KanbanApi.deleteColumn(id)
  userColumns.value.splice(i, 1)
  bumpRefresh()
  showModal.value = false
}

/* ===================== UNDO/REDO и снапшоты ===================== */
const HISTORY_LIMIT = 100
const history = ref([])
const future  = ref([])

function pushAction(action) {
  history.value.push(action)
  if (history.value.length > HISTORY_LIMIT) history.value.shift()
  future.value = []
}

const colKey = (c) => String(c?.code ?? c?.id ?? '')

function snapshotColumnIds() {
  return userColumns.value.map(colKey)
}
function snapshotTasks() {
  const map = {}
  if (firstCol.value) map[colKey(firstCol.value)] = (firstCol.value.tasks || []).map(t => String(t.id))
  for (const c of userColumns.value) {
    map[colKey(c)] = (c.tasks || []).map(t => String(t.id))
  }
  return map
}
function pick(obj, keys) {
  const out = {}
  for (const k of keys) if (obj && obj[k]) out[k] = obj[k].slice()
  return out
}

/* ---------- helpers ---------- */
function findCol(key) {
  if (!key) return null
  const k = String(key)
  if (firstCol.value && colKey(firstCol.value) === k) return firstCol.value
  return userColumns.value.find(c => colKey(c) === k) || null
}
function getAllTaskMap() {
  const m = new Map()
  const push = (t) => { if (t && t.id != null) m.set(String(t.id), t) }
  if (firstCol.value) (firstCol.value.tasks || []).forEach(push)
  userColumns.value.forEach(c => (c.tasks || []).forEach(push))
  return m
}

/* === ключевой фикс: карта задач на момент старта DnD === */
let taskMapAtDragStart = null

function applyTaskOrders(partial, fallbackMap = null) {
  // объединённая карта: сначала текущая, затем fallback со старта
  const cur = getAllTaskMap()
  const get = (id) => cur.get(String(id)) || (fallbackMap && fallbackMap.get(String(id))) || null

  for (const [colId, ids] of Object.entries(partial || {})) {
    const col = findCol(colId)
    if (!col) continue
    col.tasks = (ids || []).map(id => get(id)).filter(Boolean)
  }
  bumpRefresh()
}

function applyColumns(orderIds) {
  const byId = new Map(userColumns.value.map(c => [colKey(c), c]))
  const reordered = orderIds.map(id => byId.get(String(id))).filter(Boolean)
  const rest = userColumns.value.filter(c => !orderIds.includes(colKey(c)))
  userColumns.value = [...reordered, ...rest]
  bumpRefresh()
}

/* ---------- DnD: колонки ---------- */
const colsDragStart = ref(null)
function onColumnsStart() { colsDragStart.value = snapshotColumnIds() }
async function onColumnsEnd() {
  await nextTick()
  const before = colsDragStart.value || []
  const after  = snapshotColumnIds()
  colsDragStart.value = null
  if (JSON.stringify(before) === JSON.stringify(after)) return
  pushAction({ type: 'columns', before, after })
  try { await KanbanApi.reorderColumns(after) } catch (e) { console.error(e) }
}

/* ---------- DnD: карточки ---------- */
const tasksDragStart = ref(null)

function onTasksStart() {
  tasksDragStart.value = snapshotTasks()
  taskMapAtDragStart = getAllTaskMap()           // <— СНИМОК ОБЪЕКТОВ ЗАДАЧ
  isDraggingTasks.value = true
  isOverCompact.value = false
  isCompact.value = false // включится по наведению на квадратик
}

// onMove не нужен — compact включаем по ховеру квадрата
function onTasksMove() {}

async function onTasksEnd({ fromId, toId, taskId, newIdx }) {
  await nextTick()

  const prevSnapshot = tasksDragStart.value
  const to   = findCol(toId)
  const from = findCol(fromId)

  // 1) дроп не в колонку (например, в Compact или «мимо») — плавно откатываемся
  if (!to) {
    applyTaskOrders(prevSnapshot || {}, taskMapAtDragStart)
    tasksDragStart.value = null
    taskMapAtDragStart = null
    isDraggingTasks.value = false
    isCompact.value = false
    isOverCompact.value = false
    return
  }

  // 2) нормальная обработка дропа в колонку
  const affected = Array.from(new Set([fromId, toId].filter(Boolean)))
  const before = pick(prevSnapshot || {}, affected)
  const now    = snapshotTasks()
  const after  = pick(now, affected)

  const toIds   = (to?.tasks   || []).map(t => String(t.id))
  const fromIds = (from?.tasks || []).map(t => String(t.id))

  if (JSON.stringify(before) !== JSON.stringify(after)) {
    pushAction({ type: 'tasks', before, after })
  }

  const moved = (to?.tasks || []).find(t => String(t.id) === String(taskId))
  const rawNum = moved?.order?.order_number ?? moved?.name ?? null
  const shopOrderNumber = rawNum != null ? String(String(rawNum).replace(/^#/, '')) : null

  try {
    if (taskId && fromId && toId && fromId !== toId) {
      await KanbanApi.moveOrder(taskId, toId, (newIdx ?? 0), toIds, shopOrderNumber)
      const calls = []
      if (toIds.length) calls.push(KanbanApi.reorderOrders(String(toId), toIds).catch(() => {}))
      if (from && String(fromId) !== 'new' && fromIds.length) calls.push(KanbanApi.reorderOrders(String(fromId), fromIds))
      if (calls.length) await Promise.all(calls)
    } else if (to && toIds.length) {
      const prev = (prevSnapshot?.[toId] || [])
      const changed = prev.length !== toIds.length || prev.some((id, i) => id !== toIds[i])
      if (changed) { try { await KanbanApi.reorderOrders(String(toId), toIds) } catch {} }
    }
  } catch (e) {
    console.error('Tasks DnD sync failed', e?.response?.status, e?.response?.data ?? e)
  }

  // завершили DnD — гасим компакт
  tasksDragStart.value = null
  taskMapAtDragStart = null
  isDraggingTasks.value = false
  isCompact.value = false
  isOverCompact.value = false
}

/* ---------- undo / redo ---------- */
async function undo() {
  if (!history.value.length) return
  const action = history.value.pop()
  future.value.push(action)
  if (action.type === 'columns') {
    applyColumns(action.before)
    try { await KanbanApi.reorderColumns(action.before) } catch {}
  } else if (action.type === 'tasks') {
    applyTaskOrders(action.before)
    // серверная синхронизация опущена для простоты
  }
}
async function redo() {
  if (!future.value.length) return
  const action = future.value.pop()
  history.value.push(action)
  if (action.type === 'columns') {
    applyColumns(action.after)
    try { await KanbanApi.reorderColumns(action.after) } catch {}
  } else if (action.type === 'tasks') {
    applyTaskOrders(action.after)
    // серверная синхронизация опущена для простоты
  }
}

/* ---------- горячие клавиши ---------- */
function keyHandler (e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
    e.preventDefault()
    if (e.shiftKey) redo()
    else undo()
  }
}
</script>

<template>
  <Head title="Kanban" />

  <div class="mt-2 mb-4 flex items-center justify-end gap-2">
    <!-- Квадратик Compact — появляется только во время DnD; до 50% ширины -->
    <div class="flex-1 max-w-[50%]">
      <transition name="compact-zone">
        <div
          v-if="isDraggingTasks"
          :key="'compact-'+refreshKey"
          class="compact-zone w-full h-9 rounded-md border-2
                 flex items-center justify-center text-xs select-none transition-colors duration-150
                 bg-amber-100 border-amber-300 text-amber-900"
          :class="isOverCompact ? 'ring-2 ring-amber-400' : ''"
          @mouseenter="onCompactEnter"
          @mouseleave="onCompactLeave"
          v-draggable="[compactDummy, compactDragOptions]"
          title="Hover to enable compact view"
        >
          Compact
        </div>
      </transition>
    </div>

    <button
      class="px-2.5 py-2 rounded-lg text-sm bg-gray-100 dark:bg-gray-800
             text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700 disabled:opacity-50"
      :disabled="!history.length"
      @click="undo"
      title="Undo (Ctrl/Cmd+Z)"
    >↶</button>

    <button
      class="px-2.5 py-2 rounded-lg text-sm bg-gray-100 dark:bg-gray-800
             text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700 disabled:opacity-50"
      :disabled="!future.length"
      @click="redo"
      title="Redo (Ctrl/Cmd+Shift+Z)"
    >↷</button>

    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
      Total orders: {{ totalCards }}
    </span>

    <button class="px-3 py-2 rounded-lg text-white bg-blue-600 hover:bg-blue-700 text-sm"
            @click="openCreateModal">
      Add status
    </button>
  </div>

  <!-- Канбан -->
  <div
    :class="['flex overflow-x-auto pb-1', isCompact ? 'gap-1 is-compact' : 'gap-2']"
    ref="columnsWrapEl"
  >
    <ColumnCard
      :key="'first-'+refreshKey"
      :col="firstCol"
      :idx="0"
      :refresh="refreshKey"
      @edit="openEditModal"
      @tasks-start="onTasksStart"
      @tasks-move="onTasksMove"
      @tasks-end="onTasksEnd"
    />

    <div
      :key="'cols-'+refreshKey"
      v-draggable="[
        userColumns,
        {
          group: { name: 'kanban-columns', pull: true, put: false },
          animation: 200,
          onStart: onColumnsStart,
          onEnd: onColumnsEnd,
          draggable: '.kanban-col',
          filter: '.task-card',
          preventOnFilter: false
        }
      ]"
      class="flex"
      :class="isCompact ? 'gap-1' : 'gap-2'"
    >
      <ColumnCard
        v-for="(col, i) in userColumns"
        :key="col.id + '-' + refreshKey"
        :col="col"
        :idx="i + 1"
        :refresh="refreshKey"
        @edit="openEditModal"
        @tasks-start="onTasksStart"
        @tasks-move="onTasksMove"
        @tasks-end="onTasksEnd"
      />
    </div>
  </div>

  <ColumnModal
    v-model="showModal"
    :mode="modalMode"
    :form="form"
    :canDelete="editingIndex > 0"
    @save="saveColumn"
    @delete="deleteColumn"
  />
</template>

<style scoped>
/* ширина колонок и компактный режим */
:deep(.kanban-col) { width: 320px; transition: width .15s ease; }
.is-compact :deep(.kanban-col) { width: 220px; }

/* плавное появление квадратика */
.compact-zone-enter-active, .compact-zone-leave-active { transition: all .15s ease; }
.compact-zone-enter-from,  .compact-zone-leave-to      { opacity: 0; transform: translateY(-6px); }
.compact-zone-enter-to,    .compact-zone-leave-from    { opacity: 1; transform: translateY(0); }
</style>
