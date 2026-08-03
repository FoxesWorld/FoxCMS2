<script setup lang="ts">
import { computed } from 'vue'
import JsonValueEditor from './JsonValueEditor.vue'
import { normalizeJsonValue } from './jsonValue'
import type { JsonFieldControls, JsonFieldOptions, JsonRootKind, JsonValue } from './types'
import './json-form.css'

const props = withDefaults(defineProps<{
  modelValue: JsonValue
  samples?: unknown[]
  label?: string
  rootKind?: JsonRootKind
  disabled?: boolean
  fieldOptions?: JsonFieldOptions
  fieldControls?: JsonFieldControls
}>(), {
  samples: () => [],
  label: '',
  rootKind: 'auto',
  disabled: false,
  fieldOptions: () => ({}),
  fieldControls: () => ({}),
})

const emit = defineEmits<{ 'update:modelValue': [value: JsonValue] }>()
const normalizedSamples = computed(() => props.samples.map((sample) => normalizeJsonValue(sample)))
</script>

<template>
  <div class="json-form-editor">
    <JsonValueEditor
      :model-value="modelValue"
      :samples="normalizedSamples"
      :label="label"
      :locked-kind="rootKind === 'auto' ? null : rootKind"
      :disabled="disabled"
      :field-options="fieldOptions"
      :field-controls="fieldControls"
      :depth="0"
      @update:model-value="emit('update:modelValue', $event)"
    />
  </div>
</template>
