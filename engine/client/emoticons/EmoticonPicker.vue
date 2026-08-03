<script setup lang="ts">
import { t } from '@/i18n'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { emoticonCatalog } from './catalog'
import type { EmoticonCatalog } from './types'

withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })
const emit = defineEmits<{ select: [shortcode: string] }>()

const root = ref<HTMLElement | null>(null)
const catalog = ref<EmoticonCatalog | null>(null)
const open = ref(false)
const loading = ref(false)
const failed = ref(false)

async function load(): Promise<void> {
  if (catalog.value || loading.value) return
  loading.value = true
  failed.value = false
  try {
    catalog.value = await emoticonCatalog()
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

function toggle(): void {
  open.value = !open.value
  if (open.value) void load()
}

function select(shortcode: string): void {
  emit('select', shortcode)
  open.value = false
}

function closeOutside(event: PointerEvent): void {
  if (open.value && event.target instanceof Node && !root.value?.contains(event.target)) open.value = false
}

onMounted(() => document.addEventListener('pointerdown', closeOutside))
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOutside))
</script>

<template>
  <div ref="root" class="fox-emoticon-picker" data-emoticons="off">
    <button
      class="fox-emoticon-picker__trigger"
      type="button"
      :disabled="disabled"
      :title="t('engine.emoticons.picker.001')"
      :aria-label="t('engine.emoticons.picker.001')"
      :aria-expanded="open"
      @click="toggle"
    >
      <i class="fa-solid fa-face-smile" aria-hidden="true" />
    </button>

    <div v-if="open" class="fox-emoticon-picker__panel">
      <p v-if="loading" class="fox-emoticon-picker__state">{{ t('engine.emoticons.picker.002') }}</p>
      <p v-else-if="failed" class="fox-emoticon-picker__state fox-emoticon-picker__state--error">
        {{ t('engine.emoticons.picker.003') }}
      </p>
      <template v-else-if="catalog">
        <section v-for="category in catalog.categories" :key="category.id" class="fox-emoticon-picker__category">
          <strong>{{ category.label }}</strong>
          <div class="fox-emoticon-picker__grid">
            <button
              v-for="item in category.items"
              :key="item.name"
              type="button"
              :title="item.shortcode"
              :aria-label="item.shortcode"
              @click="select(item.shortcode)"
            >
              <img
                :src="item.url"
                :alt="item.shortcode"
                :width="item.width"
                :height="item.height"
                loading="lazy"
                decoding="async"
              >
            </button>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>
