<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  appName: { type: String, default: 'Shopify App' },
  appLogo: { type: String, default: null },
})

// If you use Ziggy, replace with: const installUrl = route('shopify.install')
const installUrl = '/shopify/install'
const year = computed(() => new Date().getFullYear())

const features = [
  { title: 'Stage Board', desc: 'Move orders between stages with drag & drop to keep work flowing.' },
  { title: 'Bulk Actions', desc: 'Capture, fulfill, print, tag and assign in one pass.' },
  { title: 'Smart Filters', desc: 'Search by customer, tags, amounts and risk; save views for the team.' },
  { title: 'Live Sync', desc: 'Webhooks keep the board up to date without page refreshes.' },
  { title: 'Roles & Access', desc: 'Granular permissions for operations and support teams.' },
  { title: 'Analytics', desc: 'See bottlenecks, SLA breaches and stage throughput.' },
]
</script>

<template>
  <Head>
    <title>{{ appName }} · Order management for Shopify</title>
    <meta name="description" content="Drag-and-drop stages, bulk actions and live updates. A fast workspace for Shopify stores." />
  </Head>

  <div class="min-h-screen bg-gradient-to-br from-teal-50 via-white to-emerald-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-900 text-slate-800 dark:text-slate-100">
    <!-- Header -->
    <header class="sticky top-0 z-40 backdrop-blur bg-white/70 dark:bg-gray-950/60 border-b border-black/5 dark:border-white/10">
      <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-3">
        <img v-if="appLogo" :src="appLogo" class="h-8 w-auto rounded-md ring-1 ring-black/5" alt="Logo" />
        <span class="font-semibold">{{ appName }}</span>
        <span class="opacity-50">/</span>
        <nav class="hidden md:flex items-center gap-6 text-sm opacity-80">
          <a href="#features" class="hover:text-teal-600 dark:hover:text-teal-400">Features</a>
          <a href="#how" class="hover:text-teal-600 dark:hover:text-teal-400">How it works</a>
          <a href="#faq" class="hover:text-teal-600 dark:hover:text-teal-400">FAQ</a>
        </nav>

        <div class="ml-auto flex items-center gap-2">
          <a :href="installUrl"
             class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700 transition"
             aria-label="Install the app">
            Install app
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
          </a>
          <a href="/login"
             class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/70 dark:bg-gray-800 border border-black/10 dark:border-white/10 hover:bg-white transition"
             aria-label="Log in">
            Log in
          </a>
        </div>
      </div>
    </header>

    <!-- Hero -->
    <section class="relative overflow-hidden">
      <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-teal-200/30 blur-3xl dark:bg-teal-800/30"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-emerald-200/30 blur-3xl dark:bg-emerald-800/30"></div>

      <div class="max-w-7xl mx-auto px-4 py-16 md:py-24 grid md:grid-cols-2 gap-10 items-center">
        <div>
          <h1 class="text-4xl md:text-5xl font-bold leading-tight">
            Order management with <span class="bg-clip-text text-transparent bg-gradient-to-r from-teal-600 to-emerald-500 dark:from-teal-400 dark:to-emerald-300">drag-and-drop stages</span> for Shopify
          </h1>
          <p class="mt-4 text-lg opacity-80">
            Organize your fulfillment flow, speed up processing and keep the whole team in one workspace.
          </p>
          <div class="mt-8 flex flex-wrap gap-3">
            <a :href="installUrl"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-teal-600 text-white hover:bg-teal-700 transition">
              Install app
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </a>
            <a href="#features"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-white/70 dark:bg-gray-800 border border-black/10 dark:border-white/10 hover:bg-white transition">
              Explore features
            </a>
          </div>
          <p class="mt-3 text-sm opacity-60">Laravel + Inertia + Vue 3. Fast UI, no bloat.</p>
        </div>

        <!-- Mock preview -->
        <div class="relative">
          <div class="rounded-2xl bg-white/80 dark:bg-gray-900/70 border border-black/10 dark:border-white/10 shadow-2xl overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-2 border-b border-black/5 dark:border-white/10 bg-slate-100/60 dark:bg-gray-800/60">
              <span class="w-3 h-3 rounded-full bg-red-400/90"></span>
              <span class="w-3 h-3 rounded-full bg-yellow-400/90"></span>
              <span class="w-3 h-3 rounded-full bg-green-400/90"></span>
              <span class="ml-2 text-xs opacity-60">Orders · Board</span>
            </div>
            <img src="/images/board-preview.png" alt="Orders board preview"
                 class="block w-full h-[360px] object-cover object-left-top">
          </div>
          <div class="absolute -bottom-6 -right-6 rounded-xl px-4 py-2 text-sm bg-emerald-600 text-white shadow-lg">
            Drag & Drop ✓
          </div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="py-14">
      <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-2xl md:text-3xl font-bold mb-6">What you get</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
          <article v-for="f in features" :key="f.title"
                   class="rounded-xl p-5 bg-white/70 dark:bg-gray-900/70 border border-black/10 dark:border-white/10 hover:border-teal-500/40 transition">
            <div class="flex items-start gap-3">
              <span class="mt-1 w-2 h-2 rounded-full bg-teal-500"></span>
              <div>
                <h3 class="font-semibold">{{ f.title }}</h3>
                <p class="mt-1 text-sm opacity-80">{{ f.desc }}</p>
              </div>
            </div>
          </article>
        </div>
        <div class="mt-8">
          <a :href="installUrl" class="inline-flex items-center px-6 py-3 rounded-lg bg-teal-600 text-white hover:bg-teal-700 transition">
            Install app
          </a>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section id="how" class="py-14">
      <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-2xl md:text-3xl font-bold mb-6">How it works</h2>
        <ol class="grid md:grid-cols-3 gap-5 text-sm">
          <li class="rounded-xl p-5 bg-white/70 dark:bg-gray-900/70 border border-black/10 dark:border-white/10">
            <div class="text-xs uppercase tracking-wide opacity-60">Step 1</div>
            <div class="font-semibold mt-1">Connect your Shopify store</div>
            <p class="opacity-80 mt-1">Secure OAuth. Permissions scoped to what you choose.</p>
          </li>
          <li class="rounded-xl p-5 bg-white/70 dark:bg-gray-900/70 border border-black/10 dark:border-white/10">
            <div class="text-xs uppercase tracking-wide opacity-60">Step 2</div>
            <div class="font-semibold mt-1">Customize stages & rules</div>
            <p class="opacity-80 mt-1">Statuses, SLAs and automations to match your flow.</p>
          </li>
          <li class="rounded-xl p-5 bg-white/70 dark:bg-gray-900/70 border border-black/10 dark:border-white/10">
            <div class="text-xs uppercase tracking-wide opacity-60">Step 3</div>
            <div class="font-semibold mt-1">Work the board</div>
            <p class="opacity-80 mt-1">Drag orders between stages and run bulk ops.</p>
          </li>
        </ol>
        <div class="mt-8">
          <a :href="installUrl" class="inline-flex items-center px-6 py-3 rounded-lg bg-teal-600 text-white hover:bg-teal-700 transition">
            Install app
          </a>
        </div>
      </div>
    </section>

    <!-- FAQ (short) -->
    <section id="faq" class="py-14">
      <div class="max-w-5xl mx-auto px-4 grid md:grid-cols-2 gap-5 text-sm">
        <div class="rounded-xl p-5 bg-white/70 dark:bg-gray-900/70 border border-black/10 dark:border-white/10">
          <div class="font-semibold">Is it secure?</div>
          <p class="opacity-80 mt-1">Yes. OAuth with Shopify, HTTPS, and scoped permissions.</p>
        </div>
        <div class="rounded-xl p-5 bg-white/70 dark:bg-gray-900/70 border border-black/10 dark:border-white/10">
          <div class="font-semibold">Will it change my data?</div>
          <p class="opacity-80 mt-1">Only when you trigger actions (capture, fulfill, tag). Everything is logged.</p>
        </div>
      </div>
    </section>

    <footer class="border-t border-black/10 dark:border-white/10 py-8">
      <div class="max-w-7xl mx-auto px-4 text-sm opacity-70 flex items-center justify-between">
        <span>© {{ year }} {{ appName }}</span>
        <div class="flex items-center gap-4">
          <a :href="installUrl" class="hover:text-teal-600 dark:hover:text-teal-400">Install</a>
          <a href="/login" class="hover:text-teal-600 dark:hover:text-teal-400">Log in</a>
        </div>
      </div>
    </footer>
  </div>
</template>
