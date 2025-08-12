<script setup>
const props = defineProps({
  task: { type: Object, required: true },
})
</script>

<template>
  <div
    class="p-3 rounded-md border border-gray-200 dark:border-gray-700
           bg-white dark:bg-gray-800 shadow-sm cursor-move"
  >
    {{ task.name }}
  </div>
</template>
