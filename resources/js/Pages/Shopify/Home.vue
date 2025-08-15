<script setup>
import { Head, usePage, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import Default from '../../Layouts/Default.vue'

const host = new URLSearchParams(location.search).get('host') ?? ''

defineOptions({ layout: Default })

const page = usePage()
const shopName = computed(() => page.props.auth?.shop?.name || 'Shop')
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
})
const formattedDate = computed(() =>
  new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
)
</script>

<template>
  <Head title="Shopify Home" />

  <main class="min-h-screen">
    <div class="max-w-6xl mx-auto">
      <!-- Header -->
      <header class="mb-10">
        <div class="flex items-center justify-between mb-2">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            {{ greeting }}, {{ shopName }}
            <span class="text-green-500 dark:text-green-400 animate-pulse">•</span>
          </h1>
          <time
            class="text-sm font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-full shadow-sm">
            {{ formattedDate }}
          </time>
        </div>
        <p class="text-gray-600 dark:text-gray-400">
          Your Shopify app area is ready.
        </p>

        <!-- Authorized badge -->
        <div class="mt-4 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium
                    bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Магазин авторизован
        </div>

        <!-- Dashboard link -->
        <!-- <div class="mt-6">
          <Link :href="`/shop/dashboard${host ? `?host=${host}` : ''}`"
                class="inline-block px-6 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            Перейти в Dashboard
          </Link>
        </div> -->
      </header>
    </div>
  </main>
</template>
