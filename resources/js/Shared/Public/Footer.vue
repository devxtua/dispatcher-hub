<script setup>
import { ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const version = ref('v1.0.0')
const personalisation = page.props.personalisation || {}

</script>

<template>
    <footer
        class="bg-gray-100 border-t border-gray-200 text-gray-500 dark:border-t dark:border-gray-700 dark:bg-gray-800">
        <div class="mx-auto px-2 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center space-x-2 text-xs">
                    <p>DispatcherHub</p>
                    <span class="text-gray-400">v1.0.0</span>
                </div>
            </div>
        </div>
    </footer>
</template>
