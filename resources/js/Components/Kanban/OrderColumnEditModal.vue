// Модалка колонки Kanban (Vue 3 + Tailwind):
// Поля: name/desc и цвет (color input, HEX, мини-палитра webSafePalette). Валидация HEX.
// Пропсы: modelValue (v-model), mode ('create'|'edit'), form, canDelete.
// События: update:modelValue(false), save, delete. Закрытие по Esc и клику на оверлей.


<script setup>
import { webSafePalette, normalizeHex } from '../../utils/colors'
import { ref, computed, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  mode: { type: String, default: 'create' }, // create | edit
  form: { type: Object, required: true },    // { id, name, desc, hex }
  canDelete: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'save', 'delete'])

function closeMain() { emit('update:modelValue', false) }

// ---- color state ----
const showPalette = ref(false)
const currentHex  = computed(() => (props.form.hex || '').toUpperCase())
const isHexValid  = computed(() => /^#[0-9A-F]{6}$/.test(normalizeHex(props.form.hex)))
function onHexBlur () { props.form.hex = normalizeHex(props.form.hex) }
function openPalette () {
  showPalette.value = true
  nextTick(() => document.querySelector('[data-color-cell]')?.focus?.())
}
function closePalette () { showPalette.value = false }
function pick (c) { props.form.hex = c; closePalette() }
function onPaletteKeydown (e) { if (e.key === 'Escape') { e.stopPropagation(); closePalette() } }
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- overlay основной модалки -->
    <div class="absolute inset-0 bg-black/40" @click="closeMain"></div>

    <div class="relative z-10 w-full max-w-lg mx-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ mode === 'create' ? 'Add column' : 'Edit column' }}
        </h3>
      </div>

      <div class="px-5 py-4 space-y-4">
        <!-- Title -->
        <div>
          <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">Title</label>
          <input v-model="form.name" type="text"
                 class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 outline-none focus:ring-2 ring-blue-500"
                 placeholder="Column title" />
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">Description</label>
          <textarea v-model="form.desc" rows="3"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 outline-none focus:ring-2 ring-blue-500"
                    placeholder="Optional description" />
        </div>

        <!-- Color -->
        <div>
          <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">Color</label>

          <!-- всё в ОДНОЙ строке -->
          <div class="flex items-center gap-3 flex-wrap">
            <!-- 1) кнопка-пипетка (system color input) -->
            <input
              type="color"
              v-model="form.hex"
              class="w-9 h-9 p-0 border border-gray-300 dark:border-gray-700 rounded cursor-pointer bg-transparent"
              aria-label="Pick color"
            />

            <!-- 2) текстовое поле HEX -->
            <input
              v-model="form.hex"
              @blur="onHexBlur"
              type="text"
              placeholder="#RRGGBB"
              class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800"
            />


            <!-- 4) кнопка открыть мини-палитру -->
            <button
              type="button"
              class="px-2.5 py-2 rounded-lg text-sm bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700"
              @click.stop="openPalette"
            >
              Palette
            </button>
          </div>

          <p v-if="!isHexValid" class="text-xs text-rose-500 mt-1">HEX must be #RRGGBB</p>
        </div>
      </div>

      <div class="px-5 py-4 flex items-center justify-between border-t border-gray-100 dark:border-gray-800">
        <button v-if="mode === 'edit' && canDelete"
                class="px-3 py-2 rounded-lg text-white bg-rose-600 hover:bg-rose-700 text-sm"
                @click="$emit('delete')">
          Delete
        </button>

        <div class="ml-auto flex gap-2">
          <button class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm"
                  @click="closeMain">
            Cancel
          </button>
          <button class="px-3 py-2 rounded-lg text-white bg-blue-600 hover:bg-blue-700 text-sm"
                  @click="$emit('save')">
            Save
          </button>
        </div>
      </div>
    </div>

    <!-- МИНИ-МОДАЛКА ПАЛИТРЫ с затемнением -->
    <div v-if="showPalette"
         class="fixed inset-0 z-[60] flex items-center justify-center"
         @keydown.capture="onPaletteKeydown">
      <div class="absolute inset-0 bg-black/30" @click.stop="closePalette"></div>

      <div class="relative z-10 w-[22rem] max-w-[92vw] rounded-xl
                  bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 shadow-xl"
           @click.stop>
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-gray-900 dark:text-white">Choose color</h4>
          <button class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800"
                  @click="closePalette" aria-label="Close">✕</button>
        </div>

        <div class="grid grid-cols-12 gap-1.5 overflow-y-auto pr-1" style="max-height: 45vh;">
          <button
            v-for="c in webSafePalette"
            :key="c"
            :title="c"
            @click="pick(c)"
            class="h-5 w-5 rounded border border-black/10 focus:outline-none focus:ring-2 focus:ring-offset-1"
            :class="currentHex === c ? 'ring-2 ring-blue-500 ring-offset-1' : ''"
            :style="{ backgroundColor: c }"
            aria-label="Pick preset color"
            type="button"
            data-color-cell
          />
        </div>
      </div>
    </div>
  </div>
</template>
