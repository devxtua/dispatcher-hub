<script setup>
import { ref, computed, onMounted } from 'vue'

// Пропсы от Laravel/Inertia
const props = defineProps({
  shop: { type: String, default: '' },
  appName: { type: String, default: 'My Shopify App' },
  appLogo: { type: String, default: '' },
  apiKey: { type: String, required: true }, // ключ обязателен
})

// Стейт
const shopInput = ref(props.shop ?? '')

// Константы
const APP_REDIRECT = 'https://tandooria.com/authenticate'
const SCOPES = ['read_products', 'write_products'] // добавляй свои

// Проверка домена
const isShopValid = computed(() =>
  !!shopInput.value &&
  /^[a-z0-9-]+\.myshopify\.com$/i.test(shopInput.value)
)

// Отладка
onMounted(() => {
  console.log('[Install] component mounted')
})

// Установка
function installApp() {
  console.log('[Install] click')

  if (!props.apiKey) {
    alert('Не задан SHOPIFY_API_KEY')
    return
  }
  if (!isShopValid.value) {
    alert('Введите корректный домен, например: example.myshopify.com')
    return
  }

  // Собираем install URL
  const params = new URLSearchParams({
    client_id: props.apiKey,
    scope: SCOPES.join(','),
    redirect_uri: APP_REDIRECT,
  })

  const installUrl = `https://${shopInput.value}/admin/oauth/authorize?${params.toString()}`
  console.log('[Install] URL:', installUrl)

  // Переход к OAuth
  window.location.href = installUrl
}
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-6 py-12">
    <img
      v-if="appLogo"
      :src="appLogo"
      alt="App Logo"
      class="w-24 h-24 mb-6 rounded-full shadow-lg"
    />

    <h1 class="text-3xl font-bold text-gray-800 mb-2">
      {{ appName }}
    </h1>

    <p class="text-gray-500 mb-6 text-center">
      Установите приложение в ваш Shopify-магазин
    </p>

    <!-- Оборачиваем в форму и гасим submit на Enter -->
    <form class="w-full max-w-md" @submit.prevent="installApp">
      <input
        v-model="shopInput"
        placeholder="example.myshopify.com"
        class="border border-gray-300 rounded-lg px-4 py-2 w-full shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        type="text"
        autocomplete="off"
        inputmode="url"
      />

      <button
        type="button"
        @click="installApp"
        :disabled="!isShopValid"
        class="mt-4 px-6 py-2 rounded-lg font-semibold transition duration-200 shadow-md
               text-white bg-blue-600 hover:bg-blue-700
               disabled:opacity-50 disabled:cursor-not-allowed w-full"
      >
        Установить приложение
      </button>
    </form>

    <!-- Подсказка -->
    <p class="mt-3 text-xs text-gray-500">
      Домен должен оканчиваться на <code>.myshopify.com</code>
    </p>
  </div>
</template>
