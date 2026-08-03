<script setup lang="ts">
import { t } from '@/i18n'

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import type { FileEntry } from '@modules/AdminPanel/client/useAdminPanel'
import { editImageWithPintura, isPinturaEditableImage } from '@/media/pinturaImageEditor'

const MAX_UPLOAD_BYTES = 67_108_864

const props = defineProps<{
  path: string
  parent: string | null
  entries: FileEntry[]
  writable: boolean
  totalBytes: number
  selectedUpload: File | null
  uploading: boolean
  newDirectoryName: string
  loading: boolean
}>()

const emit = defineEmits<{
  navigate: [path: string]
  reload: []
  selectUpload: [file: File | null]
  upload: []
  'update:newDirectoryName': [value: string]
  createDirectory: []
  open: [entry: FileEntry]
  rename: [entry: FileEntry]
  remove: [entry: FileEntry]
}>()

const fileInput = ref<HTMLInputElement | null>(null)
const dragDepth = ref(0)
const searchQuery = ref('')
const failedPreviews = ref<Set<string>>(new Set())
const editingImage = ref(false)
const imageEditError = ref('')
const imageEditorHost = ref<HTMLElement | null>(null)
let imageEditorAbortController: AbortController | null = null
const previewableExtensions = new Set(['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'bmp', 'ico', 'svg'])
const activeExtensions = new Set(['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash', 'html', 'htm', 'shtml', 'xhtml', 'js', 'mjs', 'svg', 'xml', 'htaccess'])

const breadcrumbs = computed(() => {
  const parts = props.path.split('/').filter(Boolean)
  return [
    { label: 'uploads', path: '' },
    ...parts.map((part, index) => ({ label: part, path: parts.slice(0, index + 1).join('/') })),
  ]
})

const isDragging = computed(() => dragDepth.value > 0)
const selectedUploadTooLarge = computed(() => (props.selectedUpload?.size ?? 0) > MAX_UPLOAD_BYTES)
const selectedUploadEmpty = computed(() => props.selectedUpload !== null && props.selectedUpload.size < 1)
const selectedUploadValid = computed(() => props.selectedUpload !== null && !selectedUploadTooLarge.value && !selectedUploadEmpty.value)
const selectedUploadEditable = computed(() => props.selectedUpload ? isPinturaEditableImage(props.selectedUpload) : false)
const uploadBlockedReason = computed(() => {
  if (props.uploading) return t('theme.foxengine.admin.filemanager.051')
  if (editingImage.value) return t('theme.foxengine.admin.filemanager.052')
  if (props.loading) return t('theme.foxengine.admin.filemanager.053')
  if (!props.writable) return t('theme.foxengine.admin.filemanager.054')
  if (!props.selectedUpload) return t('theme.foxengine.admin.filemanager.055')
  if (selectedUploadTooLarge.value) return t('theme.foxengine.admin.filemanager.056')
  if (selectedUploadEmpty.value) return t('theme.foxengine.admin.filemanager.022')
  return ''
})
const selectedUploadType = computed(() => {
  const file = props.selectedUpload
  if (!file) return ''
  const extension = file.name.includes('.') ? file.name.split('.').pop()?.toUpperCase() : ''
  return file.type || extension || t('theme.foxengine.admin.filemanager.057')
})
const selectedUploadIsActive = computed(() => {
  const name = props.selectedUpload?.name.toLocaleLowerCase('ru') ?? ''
  const extension = name.includes('.') ? name.split('.').pop() ?? '' : ''
  return activeExtensions.has(extension)
})
const selectedUploadTarget = computed(() => {
  const name = props.selectedUpload?.name ?? ''
  return `/uploads${props.path ? `/${props.path}` : ''}${name ? `/${name}` : ''}`
})
const filteredEntries = computed(() => {
  const query = searchQuery.value.trim().toLocaleLowerCase('ru')
  if (!query) return props.entries
  return props.entries.filter((entry) => [entry.name, entry.path, entry.mime, entry.extension]
    .some((value) => value.toLocaleLowerCase('ru').includes(query)))
})

watch(() => props.selectedUpload, (file) => {
  if (!file && fileInput.value) fileInput.value.value = ''
})

function previewKey(entry: FileEntry): string {
  return `${entry.path}:${entry.modified}`
}

function isPreviewable(entry: FileEntry): boolean {
  if (entry.type !== 'file' || !entry.url || failedPreviews.value.has(previewKey(entry))) return false
  return entry.mime.startsWith('image/') || previewableExtensions.has(entry.extension)
}

function previewUrl(entry: FileEntry): string {
  const separator = entry.url.includes('?') ? '&' : '?'
  return `${entry.url}${separator}v=${entry.modified}`
}

function markPreviewFailed(entry: FileEntry): void {
  const failed = new Set(failedPreviews.value)
  failed.add(previewKey(entry))
  failedPreviews.value = failed
}

function chooseFile(): void {
  if (!props.writable || props.loading || editingImage.value) return
  fileInput.value?.click()
}

async function selectFile(file: File | null): Promise<void> {
  imageEditError.value = ''
  if (!file) {
    emit('selectUpload', null)
    return
  }
  if (!isPinturaEditableImage(file)) {
    emit('selectUpload', file)
    return
  }

  await editRasterImage(file)
}

async function editRasterImage(file: File): Promise<void> {
  editingImage.value = true
  await nextTick()
  const target = imageEditorHost.value
  if (!target) {
    editingImage.value = false
    imageEditError.value = t('theme.foxengine.admin.filemanager.058')
    return
  }

  const controller = new AbortController()
  imageEditorAbortController = controller
  try {
    const edited = await editImageWithPintura(file, {
      target,
      aspectRatio: false,
      quality: 0.9,
      maximumWidth: 8192,
      maximumHeight: 8192,
      targetFit: 'contain',
      upscale: false,
      signal: controller.signal,
    })
    if (edited) emit('selectUpload', edited)
  } catch (error) {
    imageEditError.value = error instanceof Error ? error.message : t('theme.foxengine.admin.filemanager.059')
  } finally {
    if (imageEditorAbortController === controller) imageEditorAbortController = null
    editingImage.value = false
  }
}

function cancelImageEditing(): void {
  imageEditorAbortController?.abort()
}

async function editSelectedUpload(): Promise<void> {
  if (!props.selectedUpload || !selectedUploadEditable.value || editingImage.value) return
  await editRasterImage(props.selectedUpload)
}

function onFileInput(event: Event): void {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] ?? null
  target.value = ''
  void selectFile(file)
}

function clearUpload(): void {
  void selectFile(null)
  if (fileInput.value) fileInput.value.value = ''
}

function onDragEnter(event: DragEvent): void {
  if (!props.writable || props.loading || !event.dataTransfer?.types.includes('Files')) return
  dragDepth.value++
}

function onDragLeave(): void {
  dragDepth.value = Math.max(0, dragDepth.value - 1)
}

function onDrop(event: DragEvent): void {
  dragDepth.value = 0
  if (!props.writable || props.loading || editingImage.value) return
  void selectFile(event.dataTransfer?.files?.[0] ?? null)
}

function updateDirectoryName(event: Event): void {
  emit('update:newDirectoryName', (event.target as HTMLInputElement).value)
}

function formatBytes(value: number): string {
  if (value < 1024) return t('theme.foxengine.admin.filemanager.060', [value])
  const units = [t('theme.foxengine.admin.filemanager.061'), t('theme.foxengine.admin.filemanager.062'), t('theme.foxengine.admin.filemanager.063'), t('theme.foxengine.admin.filemanager.064')]
  let size = value / 1024
  let unit = 0
  while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++ }
  return `${size.toFixed(size >= 10 ? 1 : 2)} ${units[unit]}`
}

function formatDate(value: number): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value * 1000))
}

onBeforeUnmount(() => {
  imageEditorAbortController?.abort()
  imageEditorAbortController = null
})
</script>

<template>
  <section class="admin-section admin-files">
    <header class="admin-files__header">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.filemanager.001') }}</span>
        <h2>/uploads</h2>
        <p>{{ t('theme.foxengine.admin.filemanager.002') }}</p>
      </div>
      <div class="admin-files__summary">
        <span>{{ entries.length }} {{ t('theme.foxengine.admin.filemanager.003') }}</span>
        <strong>{{ formatBytes(totalBytes) }}</strong>
      </div>
    </header>

    <nav class="admin-files__breadcrumbs" :aria-label="t('theme.foxengine.admin.filemanager.004')">
      <button
        v-for="crumb in breadcrumbs"
        :key="crumb.path || 'root'"
        type="button"
        :class="{ active: crumb.path === path }"
        @click="emit('navigate', crumb.path)"
      >{{ crumb.label }}</button>
    </nav>

    <div class="admin-files__tools">
      <section class="admin-upload-panel">
        <header class="admin-upload-panel__header">
          <span class="admin-upload-panel__icon"><i class="fa-solid fa-upload" aria-hidden="true" /></span>
          <div>
            <strong>{{ t('theme.foxengine.admin.filemanager.005') }}</strong>
            <small>{{ editingImage ? t('theme.foxengine.admin.filemanager.006') : t('theme.foxengine.admin.filemanager.007') }}</small>
          </div>
        </header>

        <section v-if="editingImage" class="admin-upload-editor">
          <header class="admin-upload-editor__header">
            <div>
              <strong>{{ t('theme.foxengine.admin.filemanager.008') }}</strong>
              <small>{{ t('theme.foxengine.admin.filemanager.009') }}</small>
            </div>
            <button class="button button--ghost" type="button" @click="cancelImageEditing">
              <i class="fa-solid fa-xmark" aria-hidden="true" />
              <span>{{ t('theme.foxengine.admin.filemanager.010') }}</span>
            </button>
          </header>
          <div ref="imageEditorHost" class="admin-upload-editor__mount" />
        </section>

        <div v-show="!editingImage" class="admin-upload-panel__content">
        <div
          class="admin-upload-dropzone"
          :class="{
            'is-dragging': isDragging,
            'is-disabled': !writable || loading || editingImage,
            'has-file': selectedUpload,
            'has-error': selectedUploadTooLarge || selectedUploadEmpty,
            'has-warning': selectedUploadIsActive,
          }"
          @dragenter.prevent="onDragEnter"
          @dragover.prevent
          @dragleave.prevent="onDragLeave"
          @drop.prevent="onDrop"
        >
          <input ref="fileInput" type="file" :disabled="editingImage" @change="onFileInput">
          <i class="fa-solid fa-upload" aria-hidden="true" />
          <div>
            <strong>{{ editingImage ? t('theme.foxengine.admin.filemanager.011') : selectedUpload ? t('theme.foxengine.admin.filemanager.012') : t('theme.foxengine.admin.filemanager.013') }}</strong>
            <span>{{ editingImage ? t('theme.foxengine.admin.filemanager.014') : selectedUpload ? t('theme.foxengine.admin.filemanager.015') : t('theme.foxengine.admin.filemanager.016') }}</span>
          </div>
          <button class="button button--ghost" type="button" :disabled="!writable || loading || editingImage" @click="chooseFile">
            <i class="fa-solid fa-folder-open" aria-hidden="true" />
            <span>{{ selectedUpload ? t('theme.foxengine.admin.filemanager.017') : t('theme.foxengine.admin.filemanager.018') }}</span>
          </button>
        </div>

        <div v-if="selectedUpload" class="admin-upload-selection" :class="{ 'has-error': selectedUploadTooLarge || selectedUploadEmpty }">
          <span class="admin-upload-selection__mark"><i class="fa-solid fa-rectangle-list" aria-hidden="true" /></span>
          <div class="admin-upload-selection__copy">
            <strong>{{ selectedUpload.name }}</strong>
            <span>{{ selectedUploadType }} · {{ formatBytes(selectedUpload.size) }}</span>
            <code :title="selectedUploadTarget">{{ selectedUploadTarget }}</code>
          </div>
          <div class="admin-upload-selection__actions">
            <button
              v-if="selectedUploadEditable"
              type="button"
              :title="t('theme.foxengine.admin.filemanager.019')"
              :disabled="loading || editingImage"
              @click="editSelectedUpload"
            >
              <i class="fa-solid fa-crop-simple" aria-hidden="true" />
            </button>
            <button type="button" :title="t('theme.foxengine.admin.filemanager.020')" :disabled="loading || editingImage" @click="clearUpload">
              <i class="fa-solid fa-xmark" aria-hidden="true" />
            </button>
          </div>
        </div>

        <p v-if="imageEditError" class="admin-upload-panel__error">{{ imageEditError }}</p>
        <p v-else-if="selectedUploadTooLarge" class="admin-upload-panel__error">{{ t('theme.foxengine.admin.filemanager.021') }}</p>
        <p v-else-if="selectedUploadEmpty" class="admin-upload-panel__error">{{ t('theme.foxengine.admin.filemanager.022') }}</p>
        <p v-else-if="selectedUploadIsActive" class="admin-upload-panel__warning">{{ t('theme.foxengine.admin.filemanager.023') }}</p>
        <p v-else class="admin-upload-panel__note">{{ t('theme.foxengine.admin.filemanager.024') }}</p>

        <button
          class="button button--primary admin-upload-submit"
          type="button"
          :disabled="Boolean(uploadBlockedReason)"
          :title="uploadBlockedReason || t('theme.foxengine.admin.filemanager.025')"
          :aria-describedby="uploadBlockedReason ? 'admin-upload-disabled-reason' : undefined"
          @click="emit('upload')"
        >
          <i class="fa-solid" :class="uploading ? 'fa-spinner' : 'fa-upload'" aria-hidden="true" />
          <span>{{ uploading ? t('theme.foxengine.admin.filemanager.026') : t('theme.foxengine.admin.filemanager.027') }}</span>
        </button>
        <span v-if="uploading && selectedUpload" class="admin-upload-progress" :aria-label="t('theme.foxengine.admin.filemanager.028')"><i /></span>
        <p
          v-if="uploadBlockedReason"
          id="admin-upload-disabled-reason"
          class="admin-upload-disabled-reason"
          role="status"
        >
          <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
          <span><strong>{{ t('theme.foxengine.admin.filemanager.029') }}</strong> {{ uploadBlockedReason }}</span>
        </p>
        </div>
      </section>

      <form class="admin-file-tool admin-file-tool--directory" @submit.prevent="emit('createDirectory')">
        <span class="admin-file-tool__icon"><i class="fa-solid fa-folder-open" aria-hidden="true" /></span>
        <div>
          <strong>{{ t('theme.foxengine.admin.filemanager.030') }}</strong>
          <p>{{ t('theme.foxengine.admin.filemanager.031') }}</p>
        </div>
        <input :value="newDirectoryName" type="text" maxlength="180" :placeholder="t('theme.foxengine.admin.filemanager.032')" @input="updateDirectoryName">
        <button class="button button--ghost" type="submit" :disabled="!newDirectoryName.trim() || !writable || loading">
          <i class="fa-solid fa-plus" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.filemanager.033') }}</span>
        </button>
      </form>
    </div>

    <div class="admin-toolbar admin-files__toolbar">
      <button class="button button--ghost" type="button" :disabled="parent === null || loading" @click="emit('navigate', parent || '')">{{ t('theme.foxengine.admin.filemanager.034') }}</button>
      <button class="button button--ghost" type="button" :disabled="loading" @click="emit('reload')">{{ t('theme.foxengine.admin.filemanager.035') }}</button>
      <label class="admin-files__search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
        <input v-model="searchQuery" type="search" :placeholder="t('theme.foxengine.admin.filemanager.036')">
        <span v-if="searchQuery">{{ filteredEntries.length }}/{{ entries.length }}</span>
      </label>
      <code>/uploads{{ path ? `/${path}` : '' }}</code>
    </div>

    <div class="admin-files__list" role="table" :aria-label="t('theme.foxengine.admin.filemanager.038')">
      <div class="admin-files__row admin-files__row--head" role="row">
        <span>{{ t('theme.foxengine.admin.filemanager.039') }}</span><span>{{ t('theme.foxengine.admin.filemanager.040') }}</span><span>{{ t('theme.foxengine.admin.filemanager.041') }}</span><span>{{ t('theme.foxengine.admin.filemanager.042') }}</span><span>{{ t('theme.foxengine.admin.filemanager.043') }}</span>
      </div>
      <p v-if="!entries.length" class="admin-files__empty">{{ t('theme.foxengine.admin.filemanager.044') }}</p>
      <p v-else-if="!filteredEntries.length" class="admin-files__empty">{{ t('theme.foxengine.admin.filemanager.045') }}</p>
      <div v-for="entry in filteredEntries" :key="entry.path" class="admin-files__row" role="row">
        <button class="admin-files__name" type="button" @click="emit('open', entry)">
          <span class="admin-files__preview" :class="{ 'admin-files__preview--image': isPreviewable(entry) }">
            <img
              v-if="isPreviewable(entry)"
              :src="previewUrl(entry)"
              :alt="t('theme.foxengine.admin.filemanager.046', [entry.name])"
              loading="lazy"
              decoding="async"
              @error="markPreviewFailed(entry)"
            >
            <i v-else class="fa-solid" :class="entry.type === 'directory' ? 'fa-folder-open' : 'fa-rectangle-list'" aria-hidden="true" />
          </span>
          <span><strong>{{ entry.name }}</strong><small>{{ entry.path }}</small></span>
        </button>
        <span class="admin-files__mime">{{ entry.type === 'directory' ? t('theme.foxengine.admin.filemanager.047') : entry.mime }}</span>
        <span>{{ entry.type === 'directory' ? '—' : formatBytes(entry.size) }}</span>
        <span>{{ formatDate(entry.modified) }}</span>
        <span class="admin-files__actions">
          <button type="button" :title="t('theme.foxengine.admin.filemanager.048')" @click="emit('open', entry)">↗</button>
          <button type="button" :title="t('theme.foxengine.admin.filemanager.049')" :disabled="!writable || loading" @click="emit('rename', entry)">✎</button>
          <button class="danger" type="button" :title="t('theme.foxengine.admin.filemanager.050')" :disabled="!writable || loading" @click="emit('remove', entry)">×</button>
        </span>
      </div>
    </div>
  </section>
</template>
