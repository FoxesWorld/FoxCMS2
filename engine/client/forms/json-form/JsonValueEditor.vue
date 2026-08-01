<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import UiCheckbox from '@/components/UiCheckbox.vue'
import {
  createJsonTemplate,
  inferJsonKind,
  isJsonObject,
} from './jsonValue'
import type { JsonFieldOptions, JsonKind, JsonObject, JsonValue } from './types'

defineOptions({ name: 'JsonValueEditor' })

const CodeEditor = defineAsyncComponent(() => import('@theme/foxEngine/editor/CodeEditor.vue'))
type CodeLanguage = 'html' | 'xml' | 'json' | 'css' | 'javascript' | 'typescript' | 'sql' | 'markdown' | 'plaintext'

const props = withDefaults(defineProps<{
  modelValue: JsonValue
  samples?: JsonValue[]
  label?: string
  fieldName?: string
  lockedKind?: JsonKind | null
  disabled?: boolean
  depth?: number
  fieldOptions?: JsonFieldOptions
}>(), {
  samples: () => [],
  label: '',
  fieldName: '',
  lockedKind: null,
  disabled: false,
  depth: 0,
  fieldOptions: () => ({}),
})

const emit = defineEmits<{ 'update:modelValue': [value: JsonValue] }>()

const kind = computed(() => props.lockedKind ?? inferJsonKind(props.modelValue, props.samples))
const objectValue = computed<JsonObject>(() => isJsonObject(props.modelValue) ? props.modelValue : {})
const arrayValue = computed<JsonValue[]>(() => Array.isArray(props.modelValue) ? props.modelValue : [])
const objectSamples = computed(() => props.samples.filter(isJsonObject))
const arraySamples = computed(() => props.samples.filter(Array.isArray))
const fieldKeys = computed(() => {
  const keys = new Set(Object.keys(objectValue.value))
  for (const sample of objectSamples.value) Object.keys(sample).forEach((key) => keys.add(key))
  return [...keys]
})
const arrayItemSamples = computed<JsonValue[]>(() => [
  ...arrayValue.value,
  ...arraySamples.value.flatMap((sample) => sample),
])
const stringValue = computed(() => typeof props.modelValue === 'string' ? props.modelValue : '')
const numberValue = computed(() => typeof props.modelValue === 'number' ? props.modelValue : Number(props.modelValue) || 0)
const booleanValue = computed(() => typeof props.modelValue === 'boolean' ? props.modelValue : false)
const isMultiline = computed(() => stringValue.value.includes('\n') || stringValue.value.length > 120 || /(?:text|description|message|content|body|info)$/i.test(props.fieldName))
const codeLanguage = computed<CodeLanguage | null>(() => {
  const field = props.fieldName.trim().toLowerCase().replaceAll('-', '_')
  if (/(?:^|_)(?:html|markup|template)(?:$|_)/.test(field)) return 'html'
  if (/(?:^|_)xml(?:$|_)/.test(field)) return 'xml'
  if (/(?:^|_)(?:json|manifest|schema)(?:$|_)/.test(field)) return 'json'
  if (/(?:^|_)(?:css|stylesheet|style_source)(?:$|_)/.test(field)) return 'css'
  if (/(?:^|_)(?:typescript|ts_source)(?:$|_)/.test(field)) return 'typescript'
  if (/(?:^|_)(?:javascript|js_source|script)(?:$|_)/.test(field)) return 'javascript'
  if (/(?:^|_)(?:sql|query)(?:$|_)/.test(field)) return 'sql'
  if (/(?:^|_)(?:markdown|md)(?:$|_)/.test(field)) return 'markdown'

  const value = stringValue.value.trim()
  if (/^(?:<!doctype\s+html\b|<html\b|<[a-z][\w:-]*(?:\s[^<>]*)?>)/i.test(value)) return 'html'
  if (/^<\?xml\b/i.test(value)) return 'xml'
  if (/^[{[]/.test(value)) {
    try { JSON.parse(value); return 'json' } catch { /* Not JSON. */ }
  }
  if (/^(?:const|let|var|function|class|import|export)\b/m.test(value) || /=>/.test(value)) return 'javascript'
  if (/^(?:select|insert|update|delete|create|alter|drop)\b/i.test(value)) return 'sql'
  if (/^(?:```|#{1,6}\s|>\s|[-*+]\s)/m.test(value)) return 'markdown'
  if (/(?:^|_)(?:code|source)(?:$|_)/.test(field)) return 'plaintext'
  return null
})
const fieldOptionList = computed(() => props.fieldName ? props.fieldOptions[props.fieldName] ?? [] : [])


function fieldSamples(key: string): JsonValue[] {
  return objectSamples.value.flatMap((sample) => key in sample ? [sample[key]] : [])
}

function fieldValue(key: string): JsonValue {
  return key in objectValue.value ? objectValue.value[key] : createJsonTemplate(fieldSamples(key))
}

function updateObjectField(key: string, value: JsonValue): void {
  emit('update:modelValue', { ...objectValue.value, [key]: value })
}

function itemSamples(index: number): JsonValue[] {
  const indexed = arraySamples.value.flatMap((sample) => index in sample ? [sample[index]] : [])
  return [...indexed, ...arrayItemSamples.value]
}

function updateArrayItem(index: number, value: JsonValue): void {
  const next = [...arrayValue.value]
  next[index] = value
  emit('update:modelValue', next)
}

function removeArrayItem(index: number): void {
  const next = [...arrayValue.value]
  next.splice(index, 1)
  emit('update:modelValue', next)
}

function addArrayItem(): void {
  const optionFields = Object.keys(props.fieldOptions)
  const template = arrayItemSamples.value.length > 0
    ? createJsonTemplate(arrayItemSamples.value)
    : optionFields.length > 0
      ? Object.fromEntries(optionFields.map((key) => [key, ''])) as JsonObject
      : ''
  emit('update:modelValue', [...arrayValue.value, template])
}

function updateString(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement | HTMLTextAreaElement).value)
}

function updateCodeString(value: string): void {
  emit('update:modelValue', value)
}

function updateNumber(event: Event): void {
  const raw = (event.target as HTMLInputElement).value
  emit('update:modelValue', raw === '' ? 0 : Number(raw))
}

function updateBoolean(value: boolean): void {
  emit('update:modelValue', value)
}
</script>

<template>
  <div class="json-structure" :class="{ 'json-structure--nested': depth > 0 && (kind === 'object' || kind === 'array') }">
    <template v-if="kind === 'object'">
      <div v-if="label" class="json-form-title">{{ label }}</div>
      <div class="json-object-fields">
        <div v-for="key in fieldKeys" :key="key" class="json-field-row">
          <label class="json-field-row__label">{{ key }}</label>
          <div class="json-field-row__value">
            <JsonValueEditor
              :model-value="fieldValue(key)"
              :samples="fieldSamples(key)"
              :field-name="key"
              :depth="depth + 1"
              :disabled="disabled"
              :field-options="fieldOptions"
              @update:model-value="updateObjectField(key, $event)"
            />
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="kind === 'array'">
      <div class="json-array-header">
        <strong>{{ label || 'Список' }}</strong>
        <span>{{ arrayValue.length }}</span>
      </div>

      <div v-if="arrayValue.length" class="json-array-items">
        <div v-for="(item, index) in arrayValue" :key="index" class="json-array-item">
          <div class="json-array-item__header">
            <span>Элемент {{ index + 1 }}</span>
            <button type="button" :disabled="disabled" aria-label="Удалить элемент" @click="removeArrayItem(index)">×</button>
          </div>
          <JsonValueEditor
            :model-value="item"
            :samples="itemSamples(index)"
            :depth="depth + 1"
            :disabled="disabled"
            :field-options="fieldOptions"
            @update:model-value="updateArrayItem(index, $event)"
          />
        </div>
      </div>
      <div v-else class="json-array-empty">Пока нет элементов.</div>

      <button class="button button--ghost json-array-add" type="button" :disabled="disabled" @click="addArrayItem">
        Добавить
      </button>
    </template>

    <template v-else-if="kind === 'string' || kind === 'null'">
      <label v-if="label" class="json-primitive-label">{{ label }}</label>
      <select v-if="fieldOptionList.length" :value="stringValue" :disabled="disabled" @change="updateString">
        <option value="" disabled>Выберите значение</option>
        <option v-for="option in fieldOptionList" :key="option" :value="option">{{ option }}</option>
      </select>
      <CodeEditor
        v-else-if="codeLanguage"
        :model-value="stringValue"
        :language="codeLanguage"
        :disabled="disabled"
        :aria-label="label || fieldName || 'Редактор кода'"
        min-height="180px"
        @update:model-value="updateCodeString"
      />
      <textarea v-else-if="isMultiline" :value="stringValue" rows="4" :disabled="disabled" @input="updateString" />
      <input v-else type="text" :value="stringValue" :disabled="disabled" @input="updateString">
    </template>

    <template v-else-if="kind === 'number'">
      <label v-if="label" class="json-primitive-label">{{ label }}</label>
      <input type="number" step="any" :value="numberValue" :disabled="disabled" @input="updateNumber">
    </template>

    <UiCheckbox
      v-else
      class="json-boolean-input"
      :model-value="booleanValue"
      :disabled="disabled"
      :label="label || (booleanValue ? 'Да' : 'Нет')"
      @update:model-value="updateBoolean"
    />
  </div>
</template>
