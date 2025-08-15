<script setup>
import { ref, computed, watch } from 'vue'
import { KanbanApi } from '../../api/kanban'

const props = defineProps({
  // task: { id, name, note?, order? }
  task: { type: Object, required: true },
})

const emit = defineEmits(['select-change'])

/* ---------- выбор карточки (чекбокс под шестерёнкой) ---------- */
const selected = ref(false)
function toggleSelected (e) {
  e?.stopPropagation?.()
  selected.value = !selected.value
  emit('select-change', { id: props.task.id, selected: selected.value })
}

/* ---------- комментарий ---------- */
const editing = ref(false)
const saving  = ref(false)
const note    = ref(props.task.note ?? '')
watch(() => props.task.note, v => { if (!editing.value) note.value = v ?? '' })
function startEdit () { editing.value = true }
function cancel () { note.value = props.task.note ?? ''; editing.value = false }
async function save () {
  try {
    saving.value = true
    const payload = (note.value ?? '').trim()
    await KanbanApi.updateOrderNote(props.task.id, payload === '' ? null : payload)
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

/* ---------- данные заказа ---------- */
const order = computed(() => props.task.order || null)
const orderNumberRaw = computed(() => {
  const num = order.value?.order_number
  if (num != null) return String(num)
  const n = order.value?.name ?? props.task.name ?? ''
  return String(n).replace(/^#/, '')
})
const orderDateISO = computed(() =>
  order.value?.processed_at || order.value?.created_at || null
)
const orderDate = computed(() => {
  if (!orderDateISO.value) return ''
  const d = new Date(orderDateISO.value)
  try {
    return d.toLocaleString(undefined, {
      month: 'short', day: 'numeric',
      hour: 'numeric', minute: '2-digit',
      hour12: true,
    })
  } catch { return d.toISOString() }
})
const fulfillmentLabel = computed(() => {
  const fs = order.value?.fulfillment_status
  return fs ? (fs[0].toUpperCase() + fs.slice(1)) : 'Unfulfilled'
})

/* ---------- позиции (Compact / Detailed) ---------- */
const items = computed(() => order.value?.line_items ?? [])
const isCompactList = ref(false)
function toggleListMode(e){ e?.stopPropagation?.(); isCompactList.value = !isCompactList.value }

function compactLine(li){
  const t = (li?.title ?? '').trim() || (li?.name ?? '').trim()
  const v = (li?.variant_title || '').trim()
  const base = v ? `${t} — ${v}` : t
  return `${base} (Qty: ${li?.quantity ?? 0})`
}
function detailedBits(li){
  return {
    title: (li?.title ?? li?.name ?? '').trim(),
    variant: (li?.variant_title || '').trim(),
    qty: li?.quantity ?? 0,
  }
}

/* ---------- панель под иконками: client | note | null ---------- */
const activePanel = ref(null) // 'client' | 'note' | null
function togglePanel(name, e){
  e?.stopPropagation?.()
  activePanel.value = (activePanel.value === name) ? null : name
  if (activePanel.value === 'note') startEdit()
}

/* ---------- клиент ---------- */
const ship = computed(() => order.value?.shipping_address || null)
const bill = computed(() => order.value?.billing_address || null)
const primaryAddr = computed(() => ship.value || bill.value)
const customerLines = computed(() => {
  const lines = []
  if (!order.value) return lines
  const email = order.value.email || order.value.contact_email || null
  const phone = primaryAddr.value?.phone || order.value.phone || null
  const name =
    primaryAddr.value?.name ||
    [primaryAddr.value?.first_name, primaryAddr.value?.last_name].filter(Boolean).join(' ') ||
    null
  if (name) lines.push(name)
  const zip = primaryAddr.value?.zip
  if (zip) lines.push(`Post Code: ${zip}`)
  if (email) lines.push(email)
  if (phone) lines.push(phone)
  const address = [
    primaryAddr.value?.company,
    primaryAddr.value?.address1,
    primaryAddr.value?.address2,
    primaryAddr.value?.city,
    primaryAddr.value?.province,
    primaryAddr.value?.country,
  ].filter(Boolean).join(', ')
  if (address) lines.push(address)
  if (!lines.length) lines.push('No customer data.')
  return lines
})

/* ---------- no-op ---------- */
function stopDnD(e){ e?.stopPropagation?.(); e?.preventDefault?.() }
</script>

<template>
  <div
    :data-task-id="task.id"
    class="relative group p-3 rounded-xl border bg-white dark:bg-gray-800 shadow-sm"
    :class="[
      selected
        ? 'ring-2 ring-blue-500 border-transparent'
        : 'border-gray-200 dark:border-gray-700'
    ]"
    role="listitem"
    :aria-label="`Order ${orderNumberRaw}`"
  >
    <!-- top-right: checkbox on hover + gear -->
    <div class="absolute top-2 right-2 flex items-center gap-1">
      <label
        class="hidden group-hover:inline-flex items-center justify-center w-5 h-5 rounded border border-gray-300 bg-white cursor-pointer"
        :class="selected ? 'inline-flex' : ''"
        title="Select card"
        @mousedown.stop
        @click.stop
      >
        <input type="checkbox" class="sr-only" :checked="selected" @change="toggleSelected" />
        <svg v-if="selected" viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
      </label>

      <button
        class="inline-flex items-center justify-center w-6 h-6 rounded-full border border-gray-300 text-gray-500 bg-white hover:bg-gray-50"
        title="Settings"
        @mousedown.stop
        @click.stop
      >
        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1 1 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a2 2 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1Z"/>
        </svg>
      </button>
    </div>

    <!-- badge -->
    <div class="mb-2">
      <span class="inline-flex items-center gap-2 text-[12px] px-2 py-1 rounded-md bg-yellow-200 text-gray-900">
        <span class="inline-block w-2 h-2 rounded-full bg-yellow-600"></span>
        {{ fulfillmentLabel }}
      </span>
    </div>

    <!-- header: номер + дата + переключатель -->
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-baseline gap-2 min-w-0">
        <a
          class="font-semibold text-blue-600 hover:underline truncate"
          href="#"
          @click.prevent.stop
          :title="orderNumberRaw"
        >{{ orderNumberRaw }}</a>
        <span class="text-[11px] text-gray-500 dark:text-gray-400 whitespace-nowrap">| {{ orderDate }}</span>
      </div>

      <button
        class="text-[11px] px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600"
        @click="toggleListMode"
        @mousedown.stop
      >
        {{ isCompactList ? 'Expand View' : 'Compact View' }}
      </button>
    </div>

    <!-- ссылки как на примере -->
    <div class="mt-2">
      <a href="#" class="text-blue-700 text-[12px] hover:underline" @click.prevent.stop>Change location (all)</a>
      <div class="mt-1 text-[11px] text-gray-500">Added items</div>
    </div>

    <!-- items -->
    <div class="mt-2 space-y-2">
      <template v-if="items.length">
        <div v-if="!isCompactList" class="space-y-3">
          <div v-for="li in items" :key="li.id" class="text-[13px] text-gray-800 dark:text-gray-200">
            <div>
              {{ detailedBits(li).title }}
              <template v-if="detailedBits(li).variant">
                —
                <span class="font-semibold">{{ detailedBits(li).variant }}</span>
              </template>
              <span class="ml-1 font-semibold">(Qty: {{ detailedBits(li).qty }})</span>
            </div>
            <a href="#" class="text-blue-700 text-[12px] hover:underline" @click.prevent.stop>Change location</a>
          </div>
        </div>
        <ul v-else class="space-y-1">
          <li v-for="li in items" :key="li.id" class="text-[12px] text-gray-800 dark:text-gray-200 truncate">
            {{ compactLine(li) }}
          </li>
        </ul>
      </template>
      <div v-else class="text-xs text-gray-500 dark:text-gray-400">No items</div>
    </div>

    <!-- CTA -->
    <div class="mt-3">
      <button
        class="w-full h-10 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-[14px] font-medium shadow"
        @mousedown.stop
        @click.prevent="stopDnD"
      >
        Send fulfillment request
      </button>
    </div>

    <!-- ИКОНКИ БЕЗ КРУЖКОВ (только две) -->
    <div class="mt-3 flex items-center gap-3">
      <!-- Клиент -->
      <button
        class="p-1 text-gray-600 hover:text-blue-600"
        title="Customer"
        @mousedown.stop
        @click="(e)=>togglePanel('client', e)"
      >
        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21a8 8 0 0 0-16 0"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </button>

      <!-- Примечание -->
      <button
        class="p-1 text-gray-600 hover:text-blue-600"
        title="Comment"
        @mousedown.stop
        @click="(e)=>togglePanel('note', e)"
      >
        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"/>
          <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
        </svg>
      </button>
    </div>

    <!-- ПАНЕЛЬ НИЖЕ ИКОНОК -->
    <div class="mt-2">
      <!-- Клиент -->
      <div v-if="activePanel==='client'" class="rounded-md bg-gray-50 dark:bg-gray-900/60 p-2 border border-gray-200 dark:border-gray-700">
        <div class="text-[12px] text-gray-700 dark:text-gray-300 leading-5">
          <div class="font-semibold mb-1">Customer</div>
          <div v-for="(line, i) in customerLines" :key="i">{{ line }}</div>
          <div class="mt-2 text-[11px] text-gray-500">Tags:</div>
        </div>
      </div>

      <!-- Примечание -->
      <div v-else-if="activePanel==='note'">
        <div class="space-y-2">
          <textarea
            v-model="note"
            rows="3"
            class="w-full px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm outline-none focus:ring-2 ring-blue-500"
            placeholder="Comment… (Ctrl/Cmd+Enter — save, Esc — cancel)"
            @keydown="onKey"
            @mousedown.stop
            @click.stop
            @dragstart.prevent
          ></textarea>
          <div class="flex gap-2 justify-end">
            <button class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-sm" type="button" @mousedown.stop @click="cancel">Cancel</button>
            <button class="px-2 py-1 rounded text-white bg-blue-600 hover:bg-blue-700 text-sm disabled:opacity-50" :disabled="saving" type="button" @mousedown.stop @click="save">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>

      <!-- если панель не активна — ничего не показываем -->
    </div>
  </div>
</template>
