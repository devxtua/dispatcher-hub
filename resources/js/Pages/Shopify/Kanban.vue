<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
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

/** Первая (системная) и правые (пользовательские) — раздельно */
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

/** Пришли новые props — разложили заново */
watch(
  () => props.columns,
  (v) => {
    const arr = (v?.length ? v : [staticColumn]).map(c => ({ ...c, tasks: [ ...(c.tasks ?? []) ] }))
    firstCol.value = arr[0]
    userColumns.value = arr.slice(1)
  }
)

const totalCards = computed(() => {
  const left = firstCol.value?.tasks?.length || 0
  const right = userColumns.value.reduce((n, c) => n + (c.tasks?.length || 0), 0)
  return left + right
})

/** ----- МОДАЛКА ----- */
const showModal = ref(false)
const modalMode = ref('create') // create | edit
// editingIndex: 0 — первая колонка, 1..N — правые
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
  if (idx <= 0) return // системную не удаляем
  const i = idx - 1
  const id = userColumns.value[i]?.id
  if (!id) return
  await KanbanApi.deleteColumn(id)
  userColumns.value.splice(i, 1)
  showModal.value = false
}

/** ----- Drag + Undo/Redo ТОЛЬКО для правых колонок ----- */
const past = ref([])   // [['col-a','col-b', ...], ...]
const future = ref([])
const HISTORY_LIMIT = 50

function snapshotIds () {
  return userColumns.value.map(c => c.id)
}
function applyOrder (ids) {
  const byId = new Map(userColumns.value.map(c => [c.id, c]))
  const reordered = ids.map(id => byId.get(id)).filter(Boolean)
  const rest = userColumns.value.filter(c => !ids.includes(c.id))
  userColumns.value = [...reordered, ...rest]
}

function onColumnsStart () {
  past.value.push(snapshotIds())
  if (past.value.length > HISTORY_LIMIT) past.value.shift()
  future.value = []
}

async function onColumnsEnd () {
  await nextTick()
  const codes = snapshotIds() // только правые
  if (codes.length) {
    try { await KanbanApi.reorderColumns(codes) } catch (e) {
      console.error('Failed to reorder columns', e)
    }
  }
}

async function undo () {
  if (!past.value.length) return
  const prev = past.value.pop()
  const current = snapshotIds()
  future.value.push(current)
  applyOrder(prev)
  try { await KanbanApi.reorderColumns(prev) } catch {}
}
async function redo () {
  if (!future.value.length) return
  const nextIds = future.value.pop()
  past.value.push(snapshotIds())
  applyOrder(nextIds)
  try { await KanbanApi.reorderColumns(nextIds) } catch {}
}

/** ----- Горячие клавиши ----- */
function keyHandler (e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
    e.preventDefault()
    if (e.shiftKey) redo()
    else undo()
  }
}
onMounted(() => window.addEventListener('keydown', keyHandler))
onBeforeUnmount(() => window.removeEventListener('keydown', keyHandler))
</script>

<template>
  <Head title="Kanban" />

  <div class="mt-2 mb-4 flex items-center justify-end gap-2">
    <button
      class="px-2.5 py-2 rounded-lg text-sm bg-gray-100 dark:bg-gray-800
             text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700 disabled:opacity-50"
      :disabled="!past.length"
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

  <!-- Слева — фиксированная, справа — только draggable правые -->
  <div class="flex gap-2 overflow-x-auto pb-1">
    <ColumnCard
      :col="firstCol"
      :idx="0"
      @edit="openEditModal"
      @tasks-end="() => {}"
    />

    <div
      v-draggable="[
        userColumns,
        {
          group: { name: 'kanban-columns', pull: true, put: true },
          animation: 200,
          onStart: onColumnsStart,
          onEnd: onColumnsEnd
        }
      ]"
      class="flex gap-2"
    >
      <ColumnCard
        v-for="(col, i) in userColumns"
        :key="col.id"
        :col="col"
        :idx="i + 1"
        @edit="openEditModal"
        @tasks-end="() => {}"
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
/* Первая и так не двигается (в отдельном контейнере), класс для читаемости */
.is-static {}
</style>
