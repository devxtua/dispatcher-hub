<!-- resources/js/Components/Kanban/OrderColumnCard.vue -->

<!-- Карточка задачи с редактированием примечания и DnD-safe поведением.
     - корневой div: class="task-card" + data-task-id (нужно родителю)
     - класс состояния is-editing (когда открыт редактор)
     - «ручка» перетаскивания .drag-handle: тянуть только за шапку
-->

<script setup>
import { ref, watch } from 'vue'
import { KanbanApi } from '../../api/kanban'

const props = defineProps({
  // task: { id, name, note?, ... }
  task: { type: Object, required: true },
})

const editing = ref(false)
const saving  = ref(false)
const note    = ref(props.task.note ?? '')

// если note обновят извне — синхроним поле, когда не редактируем
watch(() => props.task.note, v => {
  if (!editing.value) note.value = v ?? ''
})

function startEdit () { editing.value = true }
function cancel () { note.value = props.task.note ?? ''; editing.value = false }

async function save () {
  try {
    saving.value = true
    const payload = note.value.trim()
    await KanbanApi.updateOrderNote(props.task.id, payload === '' ? null : payload)
    // оптимистичное обновление локально
    props.task.note = payload === '' ? null : payload
    editing.value = false
  } catch (e) {
    console.error('Failed to save note', e)
  } finally {
    saving.value = false
  }
}

function onKey (e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); save() }
  if (e.key === 'Escape') { e.preventDefault(); cancel() }
}
</script>

<template>
  <div
    :data-task-id="task.id"
    :class="[
      'task-card','p-3','rounded-md','border','border-gray-200','dark:border-gray-700',
      'bg-white','dark:bg-gray-800','shadow-sm',
      editing ? 'cursor-default' : 'cursor-move',
      { 'is-editing': editing }
    ]"
    role="listitem"
    :aria-label="`Order ${task.name}`"
  >
    <!-- Шапка: тянем карточку только за .drag-handle -->
    <div class="drag-handle flex items-start justify-between gap-2 select-none">
      <div class="font-medium truncate">{{ task.name }}</div>
      <button
        class="shrink-0 text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700"
        type="button"
        @mousedown.stop
        @click.stop="startEdit"
        title="Edit note"
        aria-label="Edit note"
      >
        📝
      </button>
    </div>

    <!-- Просмотр примечания -->
    <div
      v-if="!editing && task.note"
      class="mt-1 text-xs text-gray-600 dark:text-gray-300 whitespace-pre-line"
    >
      {{ task.note }}
    </div>

    <!-- Редактор примечания -->
    <div v-else-if="editing" class="mt-2 space-y-2">
      <textarea
        v-model="note"
        rows="3"
        class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600
               bg-white dark:bg-gray-900 text-sm outline-none focus:ring-2 ring-blue-500"
        placeholder="Note… (Ctrl/Cmd+Enter — сохранить, Esc — отмена)"
        @keydown="onKey"
        @mousedown.stop
        @click.stop
        @dragstart.prevent
      ></textarea>
      <div class="flex gap-2 justify-end">
        <button
          class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-sm"
          type="button"
          @mousedown.stop
          @click="cancel"
        >
          Cancel
        </button>
        <button
          class="px-2 py-1 rounded text-white bg-blue-600 hover:bg-blue-700 text-sm disabled:opacity-50"
          :disabled="saving"
          type="button"
          @mousedown.stop
          @click="save"
        >
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </div>
  </div>
</template>
