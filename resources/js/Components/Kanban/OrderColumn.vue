<script setup>
import { vDraggable } from 'vue-draggable-plus'
import TaskCard from './OrderColumnCard.vue'

const props = defineProps({
  col: { type: Object, required: true },   // { id, name, desc, hex, tasks: [] }
  idx: { type: Number, required: true },   // индекс в общем списке колонок
})

const emit = defineEmits(['edit', 'tasks-end'])

function onTasksEnd() {
  emit('tasks-end', props.col)
}
</script>

<template>
  <div
    class="min-w-[300px] max-w-[300px] rounded-md bg-transparent"
    :class="idx === 0 ? 'is-static' : 'col-draggable'"
  >
    <!-- Header -->
    <div class="rounded-t-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
      <div class="h-3 w-full rounded-t-md" :style="{ backgroundColor: col.hex }"></div>

      <div class="px-4 py-3 flex items-start justify-between">
        <div class="min-w-0">
          <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="truncate">{{ col.name }}</span>
            <span
              class="shrink-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded-md
                     bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200"
              :title="(col.tasks?.length || 0) + ' cards'"
            >
              {{ col.tasks?.length || 0 }}
            </span>
          </div>
          <div v-if="col.desc" class="text-xs text-gray-500 dark:text-gray-400 truncate">
            {{ col.desc }}
          </div>
        </div>

        <button
          class="shrink-0 p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800"
          title="Edit"
          @click="$emit('edit', col, idx)"
          aria-label="Edit column"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9A1.65 1.65 0 0 0 10 3.09V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c0 .67.39 1.27 1 1.51H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Tasks list (draggable) -->
    <div class="rounded-b-md border-gray-200 dark:border-gray-700 dark:bg-gray-900">
      <div
        v-draggable="[
          col.tasks,
          {
            group: { name: 'kanban-tasks', pull: true, put: true },
            animation: 200,
            onEnd: onTasksEnd
          }
        ]"
        class="flex flex-col gap-2 p-2 px-0 overflow-y-auto"
        :style="{ maxHeight: 'calc(100vh - 260px)' }"
      >
        <TaskCard v-for="task in col.tasks" :key="task.id" :task="task" />

        <div
          v-if="!col.tasks?.length"
          class="p-4 text-sm text-gray-400 dark:text-gray-500 border border-dashed rounded-md text-center"
        >
          Drop here
        </div>
      </div>
    </div>
  </div>
</template>
