<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  shop:    { type: String, default: '' },
  appName: { type: String, default: 'My Shopify App' },
  appLogo: { type: String, default: '' },
  apiKey:  { type: String, required: true },
  // NEW: state from server (see controller snippet below)
  state:   { type: String, default: '' },
  // Optional: request per-user tokens
  perUser: { type: Boolean, default: true },
})

// State
const shopInput = ref(props.shop ?? '')
const busy = ref(false)

// OAuth config (ensure redirect is whitelisted in your app settings)
const APP_REDIRECT = 'https://tandooria.com/authenticate'
const SCOPES = ['read_orders','write_orders'] // add yours

// Helpers
function cleanShop(raw) {
  if (!raw) return ''
  // strip protocol & path, lowercase
  let s = String(raw).trim().toLowerCase()
  s = s.replace(/^https?:\/\//, '').replace(/\/.*$/, '')
  // append .myshopify.com if missing a dot
  if (!/\./.test(s)) s = `${s}.myshopify.com`
  return s
}

const isShopValid = computed(() =>
  /^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i.test(cleanShop(shopInput.value))
)

function normalizeShop() { shopInput.value = cleanShop(shopInput.value) }

onMounted(() => console.log('[Install] mounted'))

function randomState(len = 32) {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789'
  let out = ''
  for (let i = 0; i < len; i++) out += chars[Math.floor(Math.random()*chars.length)]
  return out
}

async function installApp() {
  if (!props.apiKey) return alert('SHOPIFY_API_KEY is not set')
  if (!isShopValid.value) return alert('Enter a valid domain, e.g. example.myshopify.com')

  const shopDomain = cleanShop(shopInput.value)
  const state = props.state || randomState() // prefer server-provided

  const params = new URLSearchParams({
    client_id: props.apiKey,
    scope: SCOPES.join(','),
    redirect_uri: APP_REDIRECT,
    state, // IMPORTANT
  })
  if (props.perUser) params.append('grant_options[]', 'per-user')

  const url = `https://${shopDomain}/admin/oauth/authorize?${params.toString()}`
  busy.value = true
  window.location.href = url
}
</script>

<template>
  <Head :title="`${appName} · Install`" />

  <div class="min-h-screen bg-gradient-to-br from-teal-50 via-white to-emerald-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-900 text-slate-800 dark:text-slate-100">
    <!-- Header -->
    <header class="sticky top-0 z-40 backdrop-blur bg-white/70 dark:bg-gray-950/60 border-b border-black/5 dark:border-white/10">
      <div class="max-w-3xl mx-auto px-4 py-3 flex items-center gap-3">
        <img v-if="appLogo" :src="appLogo" class="h-8 w-auto rounded-md ring-1 ring-black/5" alt="Logo" />
        <span class="font-semibold">{{ appName }}</span>
        <span class="opacity-50">/</span>
        <span class="opacity-80">Install</span>

        <a href="/login"
           class="ml-auto inline-flex items-center px-4 py-2 rounded-lg bg-white/70 dark:bg-gray-800 border border-black/10 dark:border-white/10 hover:bg-white transition">
          Log in
        </a>
      </div>
    </header>

    <!-- Body -->
    <main class="relative overflow-hidden">
      <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-teal-200/30 blur-3xl dark:bg-teal-800/30"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-emerald-200/30 blur-3xl dark:bg-emerald-800/30"></div>

      <div class="max-w-3xl mx-auto px-4 py-12 relative">
        <div class="rounded-2xl border border-black/10 dark:border-white/10 bg-white/70 dark:bg-gray-900/70 backdrop-blur p-6 sm:p-8 shadow-xl">
          <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">Install the app to your Shopify store</h1>
          <p class="mt-2 text-slate-600 dark:text-slate-300">
            Enter your shop domain to continue. You will be redirected to Shopify to approve permissions.
          </p>

          <form class="mt-6 space-y-3" @submit.prevent="installApp">
            <label class="block text-sm font-medium opacity-80" for="shop">Shop domain</label>
            <input
              id="shop"
              v-model="shopInput"
              @blur="normalizeShop"
              placeholder="your-store.myshopify.com"
              class="w-full px-4 py-3 rounded-lg bg-slate-100/80 dark:bg-gray-800 text-slate-800 dark:text-slate-100 outline-none focus:ring-2 ring-teal-500 border border-transparent"
              type="text"
              autocomplete="off"
              inputmode="url"
            />

            <button
              type="submit"
              :disabled="!isShopValid || busy"
              class="w-full mt-2 inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-medium text-white bg-teal-600 hover:bg-teal-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="busy" class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="9" stroke-width="2" class="opacity-25"/>
                <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2" class="opacity-75"/>
              </svg>
              <span>{{ busy ? 'Redirecting…' : 'Install app' }}</span>
            </button>
          </form>

          <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            Domain must end with <code>.myshopify.com</code>. OAuth only — no password required.
          </p>
        </div>
      </div>
    </main>
  </div>
</template>
