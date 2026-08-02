<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useSlots, watch } from 'vue'

export type ImageUploadPreview = 'wide' | 'square' | 'circle' | 'none'
export type ImageUploadFit = 'cover' | 'contain'

const props = withDefaults(defineProps<{
  title: string
  description?: string
  preview?: string
  previewAlt?: string
  previewMode?: ImageUploadPreview
  previewFit?: ImageUploadFit
  accept?: string
  allowedTypes?: string[]
  maximumBytes?: number
  minimumWidth?: number
  minimumHeight?: number
  maximumWidth?: number
  maximumHeight?: number
  disabled?: boolean
  uploading?: boolean
  error?: string
  hint?: string
  chooseLabel?: string
  replaceLabel?: string
  dropLabel?: string
  clearLabel?: string
  allowClear?: boolean
  showFileDetails?: boolean
}>(), {
  description: '',
  preview: '',
  previewAlt: 'Предпросмотр изображения',
  previewMode: 'wide',
  previewFit: 'cover',
  accept: 'image/jpeg,image/png,image/webp',
  allowedTypes: () => ['image/jpeg', 'image/png', 'image/webp'],
  maximumBytes: 12_582_912,
  minimumWidth: 0,
  minimumHeight: 0,
  maximumWidth: 0,
  maximumHeight: 0,
  disabled: false,
  uploading: false,
  error: '',
  hint: '',
  chooseLabel: 'Выбрать изображение',
  replaceLabel: 'Выбрать другое',
  dropLabel: 'Перетащите изображение сюда',
  clearLabel: 'Очистить',
  allowClear: true,
  showFileDetails: true,
})

const emit = defineEmits<{
  select: [file: File]
  clear: []
  invalid: [message: string]
}>()

const slots = useSlots()
const input = ref<HTMLInputElement | null>(null)
const dragDepth = ref(0)
const localPreview = ref('')
const localFile = ref<File | null>(null)
const localError = ref('')
const previewFailed = ref(false)
const validating = ref(false)

const isDisabled = computed(() => props.disabled || props.uploading || validating.value)
const isDragging = computed(() => dragDepth.value > 0)
const activePreview = computed(() => localPreview.value || props.preview.trim())
const hasPreview = computed(() => props.previewMode !== 'none' && activePreview.value !== '' && !previewFailed.value)
const visibleError = computed(() => localError.value || props.error)
const hasActions = computed(() => Boolean(slots.actions))
const selectionName = computed(() => localFile.value?.name ?? '')
const selectionSize = computed(() => localFile.value ? formatBytes(localFile.value.size) : '')

function releasePreview(): void {
  if (localPreview.value.startsWith('blob:')) URL.revokeObjectURL(localPreview.value)
  localPreview.value = ''
}

function resetLocalSelection(): void {
  releasePreview()
  localFile.value = null
  localError.value = ''
  previewFailed.value = false
  dragDepth.value = 0
  if (input.value) input.value.value = ''
}

function choose(): void {
  if (!isDisabled.value) input.value?.click()
}

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] ?? null
  target.value = ''
  if (file) void acceptFile(file)
}

function onDragEnter(event: DragEvent): void {
  if (isDisabled.value || !event.dataTransfer?.types.includes('Files')) return
  dragDepth.value++
}

function onDragLeave(): void {
  dragDepth.value = Math.max(0, dragDepth.value - 1)
}

function onDrop(event: DragEvent): void {
  dragDepth.value = 0
  if (isDisabled.value) return
  const file = event.dataTransfer?.files?.[0] ?? null
  if (file) void acceptFile(file)
}

async function acceptFile(file: File): Promise<void> {
  localError.value = ''
  previewFailed.value = false

  const message = await validateFile(file)
  if (message) {
    localError.value = message
    emit('invalid', message)
    return
  }

  releasePreview()
  localFile.value = file
  if (props.previewMode !== 'none') localPreview.value = URL.createObjectURL(file)
  emit('select', file)
}

async function validateFile(file: File): Promise<string> {
  if (file.size < 1) return 'Пустой файл загрузить нельзя.'
  if (file.size > props.maximumBytes) return `Файл превышает допустимый размер ${formatBytes(props.maximumBytes)}.`
  if (props.allowedTypes.length > 0 && !props.allowedTypes.includes(file.type)) {
    return `Тип файла ${file.type || 'не определён'} не поддерживается.`
  }
  if (props.minimumWidth <= 0 && props.minimumHeight <= 0 && props.maximumWidth <= 0 && props.maximumHeight <= 0) return ''

  validating.value = true
  try {
    const dimensions = await imageDimensions(file)
    if (!dimensions) return 'Не удалось прочитать изображение.'
    if (props.minimumWidth > 0 && dimensions.width < props.minimumWidth) {
      return `Ширина изображения должна быть не меньше ${props.minimumWidth}px.`
    }
    if (props.minimumHeight > 0 && dimensions.height < props.minimumHeight) {
      return `Высота изображения должна быть не меньше ${props.minimumHeight}px.`
    }
    if (props.maximumWidth > 0 && dimensions.width > props.maximumWidth) {
      return `Ширина изображения не должна превышать ${props.maximumWidth}px.`
    }
    if (props.maximumHeight > 0 && dimensions.height > props.maximumHeight) {
      return `Высота изображения не должна превышать ${props.maximumHeight}px.`
    }
    return ''
  } finally {
    validating.value = false
  }
}

function imageDimensions(file: File): Promise<{ width: number; height: number } | null> {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file)
    const image = new Image()
    image.onload = () => {
      resolve({ width: image.naturalWidth, height: image.naturalHeight })
      URL.revokeObjectURL(url)
    }
    image.onerror = () => {
      resolve(null)
      URL.revokeObjectURL(url)
    }
    image.src = url
  })
}

function clear(): void {
  resetLocalSelection()
  emit('clear')
}

function formatBytes(value: number): string {
  if (value < 1024) return `${value} Б`
  const units = ['КиБ', 'МиБ', 'ГиБ']
  let size = value / 1024
  let unit = 0
  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit++
  }
  return `${size.toFixed(size >= 10 ? 1 : 2)} ${units[unit]}`
}

watch(() => props.preview, async (value, previous) => {
  previewFailed.value = false
  if (value !== previous && value.trim() !== '' && localPreview.value) {
    await nextTick()
    releasePreview()
    localFile.value = null
  }
})
watch(() => props.uploading, (uploading) => {
  if (uploading) localError.value = ''
})
onBeforeUnmount(releasePreview)
</script>

<template>
  <section
    class="image-upload-field"
    :class="[
      `image-upload-field--${previewMode}`,
      `image-upload-field--fit-${previewFit}`,
      {
        'is-dragging': isDragging,
        'is-disabled': isDisabled,
        'is-uploading': uploading,
        'has-preview': hasPreview,
        'has-error': visibleError || previewFailed,
      },
    ]"
  >
    <header class="image-upload-field__header">
      <div>
        <strong>{{ title }}</strong>
        <small v-if="description">{{ description }}</small>
      </div>
      <span v-if="uploading" class="image-upload-field__state">
        <i class="fa-solid fa-spinner" aria-hidden="true" /> Загрузка…
      </span>
      <span v-else-if="localFile || preview" class="image-upload-field__state is-ready">
        <i class="fa-solid fa-circle-check" aria-hidden="true" /> Выбрано
      </span>
    </header>

    <div
      v-if="previewMode !== 'none'"
      class="image-upload-field__preview"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <img
        v-if="hasPreview"
        :src="activePreview"
        :alt="previewAlt"
        @load="previewFailed = false"
        @error="previewFailed = true"
      >
      <div v-else class="image-upload-field__preview-empty">
        <i class="fa-solid" :class="previewFailed ? 'fa-circle-exclamation' : 'fa-image'" aria-hidden="true" />
        <strong>{{ previewFailed ? 'Предпросмотр недоступен' : 'Изображение не выбрано' }}</strong>
        <small>{{ previewFailed ? 'Проверьте источник или выберите другой файл.' : dropLabel }}</small>
      </div>
      <slot name="preview-overlay" :preview="activePreview" :file="localFile" />
    </div>

    <div
      class="image-upload-field__dropzone"
      :class="{ 'is-dragging': isDragging, 'has-file': localFile }"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <input ref="input" type="file" :accept="accept" :disabled="isDisabled" @change="onInput">
      <i class="fa-solid" :class="uploading || validating ? 'fa-spinner' : 'fa-upload'" aria-hidden="true" />
      <div>
        <strong>{{ localFile ? localFile.name : dropLabel }}</strong>
        <span>{{ localFile ? `${selectionSize} · можно заменить другим файлом` : 'или откройте системный диалог выбора' }}</span>
      </div>
      <button class="button button--ghost" type="button" :disabled="isDisabled" @click="choose">
        <i class="fa-solid fa-folder-open" aria-hidden="true" />
        <span>{{ localFile || preview ? replaceLabel : chooseLabel }}</span>
      </button>
    </div>

    <div v-if="showFileDetails && localFile" class="image-upload-field__selection">
      <span><i class="fa-solid fa-image" aria-hidden="true" /></span>
      <div><strong>{{ selectionName }}</strong><small>{{ localFile.type || 'image/*' }} · {{ selectionSize }}</small></div>
      <button type="button" :title="clearLabel" :disabled="isDisabled" @click="clear"><i class="fa-solid fa-xmark" aria-hidden="true" /></button>
    </div>

    <p v-if="visibleError || previewFailed" class="image-upload-field__feedback" role="alert">
      <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
      <span>{{ visibleError || 'Изображение не удалось открыть.' }}</span>
    </p>
    <p v-else-if="hint" class="image-upload-field__hint">{{ hint }}</p>

    <footer v-if="hasActions || (allowClear && (preview || localFile))" class="image-upload-field__actions">
      <slot name="actions" :file="localFile" :disabled="isDisabled" :clear="clear" />
      <button
        v-if="allowClear && (preview || localFile)"
        class="button button--ghost image-upload-field__clear"
        type="button"
        :disabled="isDisabled"
        @click="clear"
      >
        <i class="fa-solid fa-xmark" aria-hidden="true" />
        <span>{{ clearLabel }}</span>
      </button>
    </footer>
  </section>
</template>
