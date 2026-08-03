<script setup lang="ts">
import { t } from '@/i18n'

import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Tagify from '@yaireo/tagify'
import '@yaireo/tagify/dist/tagify.css'

const props = withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
}>(), {
  placeholder: t('theme.foxengine.admin.seotagifyinput.001'),
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const input = ref<HTMLInputElement | null>(null)
let tagify: Tagify | null = null
let syncing = false

function parseKeywords(value: string): string[] {
  const unique = new Map<string, string>()
  for (const raw of value.split(/[,;，\r\n]+/u)) {
    const tag = raw.trim().replace(/\s+/gu, ' ')
    if (!tag) continue
    unique.set(tag.toLocaleLowerCase('ru'), tag)
  }
  return [...unique.values()]
}

function serializeKeywords(tags: Array<{ value?: unknown }>): string {
  const unique = new Map<string, string>()
  for (const entry of tags) {
    const value = String(entry.value ?? '').trim().replace(/\s+/gu, ' ')
    if (!value) continue
    unique.set(value.toLocaleLowerCase('ru'), value)
  }
  return [...unique.values()].join(', ')
}

function syncFromModel(value: string): void {
  if (!tagify) return
  const normalized = parseKeywords(value)
  const current = serializeKeywords(tagify.value as Array<{ value?: unknown }>)
  const next = normalized.join(', ')
  if (current === next) return

  syncing = true
  tagify.removeAllTags()
  if (normalized.length > 0) tagify.addTags(normalized, true, true)
  syncing = false
}

onMounted(async () => {
  await nextTick()
  if (!input.value) return

  tagify = new Tagify(input.value, {
    // Tagify expects a regular-expression pattern. The previous value ',;\n'
    // matched that whole sequence, so a comma by itself did not finish a tag.
    delimiters: ',|;|，|\\r?\\n',
    pasteAsTags: true,
    addTagOnBlur: true,
    duplicates: false,
    editTags: { clicks: 2, keepInvalid: false },
    dropdown: { enabled: 0, maxItems: 10, closeOnSelect: false },
    placeholder: props.placeholder,
    trim: true,
    transformTag(tagData) {
      tagData.value = String(tagData.value ?? '').trim().replace(/\s+/gu, ' ')
    },
    validate(tagData) {
      const value = String(tagData.value ?? '').trim()
      if (!value) return t('theme.foxengine.admin.seotagifyinput.002')
      return true
    },
  })

  tagify.on('change', () => {
    if (syncing || !tagify) return
    emit('update:modelValue', serializeKeywords(tagify.value as Array<{ value?: unknown }>))
  })
  syncFromModel(props.modelValue)
})

watch(() => props.modelValue, syncFromModel)

onBeforeUnmount(() => {
  tagify?.destroy()
  tagify = null
})
</script>

<template>
  <div class="admin-tagify-field">
    <input
      ref="input"
      class="admin-tagify-field__input seo-tagify-input"
      type="text"
      :value="modelValue"
      :placeholder="placeholder"
      autocomplete="off"
      spellcheck="false"
    >
  </div>
</template>
