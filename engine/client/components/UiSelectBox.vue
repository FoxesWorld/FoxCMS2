<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, useId, watch } from 'vue'

import './ui-select-box.css'

interface UiSelectBoxOption {
  value: string
  label: string
  description?: string
  search?: string
  disabled?: boolean
  tone?: 'default' | 'warning'
}

const model = defineModel<string>({ default: '' })

const props = withDefaults(defineProps<{
  options: UiSelectBoxOption[]
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  clearSearchLabel?: string
  disabled?: boolean
  required?: boolean
  searchable?: boolean
  name?: string
  invalid?: boolean
}>(), {
  placeholder: '',
  searchPlaceholder: '',
  emptyText: '',
  clearSearchLabel: '',
  disabled: false,
  required: false,
  searchable: true,
  name: undefined,
  invalid: false,
})

const root = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const opened = ref(false)
const query = ref('')
const activeIndex = ref(-1)
const id = useId()
const triggerId = `ui-select-trigger-${id}`
const listboxId = `ui-select-listbox-${id}`

const normalizedQuery = computed(() => query.value.trim().toLocaleLowerCase())
const selectedOption = computed(() => props.options.find((option) => option.value === model.value) ?? null)
const filteredOptions = computed(() => {
  const needle = normalizedQuery.value
  if (!needle) return props.options
  return props.options.filter((option) => {
    const haystack = `${option.label} ${option.value} ${option.description ?? ''} ${option.search ?? ''}`
      .toLocaleLowerCase()
    return haystack.includes(needle)
  })
})
const activeOption = computed(() => filteredOptions.value[activeIndex.value] ?? null)
const activeOptionId = computed(() => activeOption.value ? optionId(activeOption.value) : undefined)

function optionId(option: UiSelectBoxOption): string {
  const safe = option.value.replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'empty'
  return `${listboxId}-option-${safe}`
}

function firstEnabledIndex(options = filteredOptions.value): number {
  return options.findIndex((option) => !option.disabled)
}

function lastEnabledIndex(options = filteredOptions.value): number {
  for (let index = options.length - 1; index >= 0; index -= 1) {
    if (!options[index]?.disabled) return index
  }
  return -1
}

function selectedIndex(options = filteredOptions.value): number {
  const index = options.findIndex((option) => option.value === model.value && !option.disabled)
  return index >= 0 ? index : firstEnabledIndex(options)
}

function scrollActiveOptionIntoView(): void {
  const option = filteredOptions.value[activeIndex.value]
  if (!option) return
  document.getElementById(optionId(option))?.scrollIntoView({ block: 'nearest' })
}

async function open(): Promise<void> {
  if (props.disabled || opened.value) return
  opened.value = true
  query.value = ''
  activeIndex.value = selectedIndex(props.options)
  await nextTick()
  scrollActiveOptionIntoView()
  if (props.searchable) searchInput.value?.focus()
}

function close({ restoreFocus = false } = {}): void {
  if (!opened.value) return
  opened.value = false
  query.value = ''
  activeIndex.value = -1
  if (restoreFocus) {
    nextTick(() => document.getElementById(triggerId)?.focus())
  }
}

function toggle(): void {
  if (opened.value) close()
  else void open()
}

function choose(option: UiSelectBoxOption): void {
  if (option.disabled) return
  model.value = option.value
  close({ restoreFocus: true })
}

function moveActive(direction: 1 | -1): void {
  const options = filteredOptions.value
  if (options.length === 0) {
    activeIndex.value = -1
    return
  }

  let index = activeIndex.value
  for (let attempt = 0; attempt < options.length; attempt += 1) {
    index = (index + direction + options.length) % options.length
    if (!options[index]?.disabled) {
      activeIndex.value = index
      nextTick(scrollActiveOptionIntoView)
      return
    }
  }
}

function handleTriggerKeydown(event: KeyboardEvent): void {
  if (props.disabled) return
  if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
    event.preventDefault()
    void open().then(() => {
      if (event.key === 'ArrowUp') activeIndex.value = lastEnabledIndex()
      else activeIndex.value = selectedIndex()
    })
  }
}

function handleListKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    moveActive(1)
    return
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    moveActive(-1)
    return
  }
  if (event.key === 'Home') {
    event.preventDefault()
    activeIndex.value = firstEnabledIndex()
    return
  }
  if (event.key === 'End') {
    event.preventDefault()
    activeIndex.value = lastEnabledIndex()
    return
  }
  if (event.key === 'Enter' && activeOption.value) {
    event.preventDefault()
    choose(activeOption.value)
    return
  }
  if (event.key === 'Escape') {
    event.preventDefault()
    close({ restoreFocus: true })
    return
  }
  if (event.key === 'Tab') close()
}

function handleDocumentPointerDown(event: PointerEvent): void {
  if (root.value && !root.value.contains(event.target as Node)) close()
}

watch(filteredOptions, (options) => {
  if (!opened.value) return
  activeIndex.value = selectedIndex(options)
})
watch(() => props.disabled, (disabled) => {
  if (disabled) close()
})
watch(() => props.options, () => {
  if (model.value !== '' && !selectedOption.value) {
    // The parent may intentionally inject a legacy option after this update.
    activeIndex.value = -1
  }
}, { deep: true })

onMounted(() => document.addEventListener('pointerdown', handleDocumentPointerDown))
onUnmounted(() => document.removeEventListener('pointerdown', handleDocumentPointerDown))
</script>

<template>
  <div
    ref="root"
    class="ui-select-box"
    :class="{
      'is-open': opened,
      'is-disabled': disabled,
      'is-invalid': invalid,
      'has-value': selectedOption,
    }"
  >
    <select
      v-model="model"
      class="ui-select-box__native"
      :name="name"
      :required="required"
      :disabled="disabled"
      tabindex="-1"
      aria-hidden="true"
    >
      <option value="" />
      <option v-for="option in options" :key="option.value" :value="option.value" :disabled="option.disabled">
        {{ option.label }}
      </option>
    </select>

    <button
      :id="triggerId"
      class="ui-select-box__trigger"
      type="button"
      role="combobox"
      :aria-expanded="opened"
      :aria-controls="listboxId"
      :aria-activedescendant="opened ? activeOptionId : undefined"
      aria-haspopup="listbox"
      :disabled="disabled"
      @click="toggle"
      @keydown="handleTriggerKeydown"
    >
      <span class="ui-select-box__selection">
        <strong :class="{ 'is-placeholder': !selectedOption }">
          {{ selectedOption?.label || placeholder }}
        </strong>
        <small v-if="selectedOption?.description">{{ selectedOption.description }}</small>
      </span>
      <span class="ui-select-box__indicator" aria-hidden="true">
        <i class="fa-solid fa-chevron-down" />
      </span>
    </button>

    <transition name="ui-select-box-popover">
      <section v-if="opened" class="ui-select-box__popover" @keydown="handleListKeydown">
        <label v-if="searchable" class="ui-select-box__search">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
          <input
            ref="searchInput"
            v-model="query"
            type="search"
            autocomplete="off"
            :placeholder="searchPlaceholder"
          >
          <button v-if="query" type="button" :aria-label="clearSearchLabel" @click="query = ''">
            <i class="fa-solid fa-xmark" aria-hidden="true" />
          </button>
        </label>

        <div
          :id="listboxId"
          class="ui-select-box__options"
          role="listbox"
          :aria-labelledby="triggerId"
          :aria-activedescendant="activeOptionId"
          tabindex="-1"
        >
          <button
            v-for="(option, index) in filteredOptions"
            :id="optionId(option)"
            :key="option.value"
            class="ui-select-box__option"
            :class="{
              'is-active': index === activeIndex,
              'is-selected': option.value === model,
              'is-warning': option.tone === 'warning',
            }"
            type="button"
            role="option"
            :aria-selected="option.value === model"
            :disabled="option.disabled"
            @mouseenter="activeIndex = index"
            @click="choose(option)"
          >
            <span class="ui-select-box__option-icon" aria-hidden="true">
              <i v-if="option.tone === 'warning'" class="fa-solid fa-triangle-exclamation" />
              <i v-else class="fa-solid fa-cube" />
            </span>
            <span class="ui-select-box__option-copy">
              <strong>{{ option.label }}</strong>
              <small v-if="option.description">{{ option.description }}</small>
            </span>
            <i v-if="option.value === model" class="fa-solid fa-check ui-select-box__check" aria-hidden="true" />
          </button>

          <div v-if="filteredOptions.length === 0" class="ui-select-box__empty">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
            <span>{{ emptyText }}</span>
          </div>
        </div>
      </section>
    </transition>
  </div>
</template>
