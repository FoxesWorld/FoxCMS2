<script setup lang="ts">
import { t } from '@/i18n'

import './ui-checkbox.css'

type CheckboxVariant = 'checkbox' | 'switch'

const model = defineModel<boolean>({ default: false })

withDefaults(defineProps<{
  label?: string
  description?: string
  variant?: CheckboxVariant
  disabled?: boolean
  required?: boolean
  name?: string
  compact?: boolean
}>(), {
  label: '',
  description: '',
  variant: 'checkbox',
  disabled: false,
  required: false,
  name: undefined,
  compact: false,
})
</script>

<template>
  <label
    class="ui-checkbox"
    :class="[
      `ui-checkbox--${variant}`,
      {
        'is-checked': model,
        'is-disabled': disabled,
        'ui-checkbox--compact': compact,
      },
    ]"
  >
    <input
      v-model="model"
      class="ui-checkbox__input"
      type="checkbox"
      :name="name"
      :disabled="disabled"
      :required="required"
    >
    <span class="ui-checkbox__control" aria-hidden="true">
      <span class="ui-checkbox__mark" />
    </span>
    <span v-if="label || description || $slots.default" class="ui-checkbox__copy">
      <slot>
        <strong v-if="label">{{ label }}</strong>
        <small v-if="description">{{ description }}</small>
      </slot>
    </span>
  </label>
</template>
