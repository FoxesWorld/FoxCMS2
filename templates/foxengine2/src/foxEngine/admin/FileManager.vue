<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { FileEntry } from '@modules/AdminPanel/client/useAdminPanel'

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
const uploadBlockedReason = computed(() => {
  if (props.uploading) return 'Файл уже загружается. Дождитесь завершения операции.'
  if (props.loading) return 'Содержимое каталога обновляется. Загрузка станет доступна после завершения перехода.'
  if (!props.writable) return 'Текущий каталог недоступен для записи процессу PHP. Проверьте владельца, группу и права каталога на сервере.'
  if (!props.selectedUpload) return 'Сначала выберите файл или перетащите его в область загрузки.'
  if (selectedUploadTooLarge.value) return 'Выбранный файл превышает допустимый размер 64 МиБ.'
  if (selectedUploadEmpty.value) return 'Пустой файл загрузить нельзя.'
  return ''
})
const selectedUploadType = computed(() => {
  const file = props.selectedUpload
  if (!file) return ''
  const extension = file.name.includes('.') ? file.name.split('.').pop()?.toUpperCase() : ''
  return file.type || extension || 'Неизвестный тип'
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
  if (!props.writable || props.loading) return
  fileInput.value?.click()
}

function selectFile(file: File | null): void {
  emit('selectUpload', file)
}

function onFileInput(event: Event): void {
  selectFile((event.target as HTMLInputElement).files?.[0] ?? null)
}

function clearUpload(): void {
  selectFile(null)
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
  if (!props.writable || props.loading) return
  selectFile(event.dataTransfer?.files?.[0] ?? null)
}

function updateDirectoryName(event: Event): void {
  emit('update:newDirectoryName', (event.target as HTMLInputElement).value)
}

function formatBytes(value: number): string {
  if (value < 1024) return `${value} Б`
  const units = ['КБ', 'МБ', 'ГБ', 'ТБ']
  let size = value / 1024
  let unit = 0
  while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++ }
  return `${size.toFixed(size >= 10 ? 1 : 2)} ${units[unit]}`
}

function formatDate(value: number): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value * 1000))
}
</script>

<template>
  <section class="admin-section admin-files">
    <header class="admin-files__header">
      <div>
        <span class="eyebrow">File Manager</span>
        <h2>/uploads</h2>
        <p>Полный административный доступ к файлам и каталогам внутри uploads: загрузка, переименование, открытие, создание и удаление.</p>
      </div>
      <div class="admin-files__summary">
        <span>{{ entries.length }} объектов</span>
        <strong>{{ formatBytes(totalBytes) }}</strong>
      </div>
    </header>

    <nav class="admin-files__breadcrumbs" aria-label="Путь к каталогу">
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
            <strong>Загрузка файла</strong>
            <small>Любой тип и расширение · до 64 МиБ</small>
          </div>
        </header>

        <div
          class="admin-upload-dropzone"
          :class="{
            'is-dragging': isDragging,
            'is-disabled': !writable || loading,
            'has-file': selectedUpload,
            'has-error': selectedUploadTooLarge || selectedUploadEmpty,
            'has-warning': selectedUploadIsActive,
          }"
          @dragenter.prevent="onDragEnter"
          @dragover.prevent
          @dragleave.prevent="onDragLeave"
          @drop.prevent="onDrop"
        >
          <input ref="fileInput" type="file" @change="onFileInput">
          <i class="fa-solid fa-upload" aria-hidden="true" />
          <div>
            <strong>{{ selectedUpload ? 'Файл выбран' : 'Перетащите файл сюда' }}</strong>
            <span>{{ selectedUpload ? 'Можно заменить его другим файлом' : 'или выберите его через системный диалог' }}</span>
          </div>
          <button class="button button--ghost" type="button" :disabled="!writable || loading" @click="chooseFile">
            <i class="fa-solid fa-folder-open" aria-hidden="true" />
            <span>{{ selectedUpload ? 'Выбрать другой' : 'Выбрать файл' }}</span>
          </button>
        </div>

        <div v-if="selectedUpload" class="admin-upload-selection" :class="{ 'has-error': selectedUploadTooLarge || selectedUploadEmpty }">
          <span class="admin-upload-selection__mark"><i class="fa-solid fa-rectangle-list" aria-hidden="true" /></span>
          <div class="admin-upload-selection__copy">
            <strong>{{ selectedUpload.name }}</strong>
            <span>{{ selectedUploadType }} · {{ formatBytes(selectedUpload.size) }}</span>
            <code :title="selectedUploadTarget">{{ selectedUploadTarget }}</code>
          </div>
          <button type="button" title="Очистить выбор" :disabled="loading" @click="clearUpload">
            <i class="fa-solid fa-xmark" aria-hidden="true" />
          </button>
        </div>

        <p v-if="selectedUploadTooLarge" class="admin-upload-panel__error">Файл превышает лимит 64 МиБ.</p>
        <p v-else-if="selectedUploadEmpty" class="admin-upload-panel__error">Пустой файл загрузить нельзя.</p>
        <p v-else-if="selectedUploadIsActive" class="admin-upload-panel__warning">Активный формат разрешён. Файл будет публично доступен в /uploads; исполняемость и Content-Type определяются конфигурацией веб-сервера.</p>
        <p v-else class="admin-upload-panel__note">Имя, расширение и содержимое файла сохраняются без преобразования. Существующий файл не перезаписывается автоматически.</p>

        <button
          class="button button--primary admin-upload-submit"
          type="button"
          :disabled="Boolean(uploadBlockedReason)"
          :title="uploadBlockedReason || 'Загрузить выбранный файл'"
          :aria-describedby="uploadBlockedReason ? 'admin-upload-disabled-reason' : undefined"
          @click="emit('upload')"
        >
          <i class="fa-solid" :class="uploading ? 'fa-spinner' : 'fa-upload'" aria-hidden="true" />
          <span>{{ uploading ? 'Загрузка…' : 'Загрузить в текущий каталог' }}</span>
        </button>
        <span v-if="uploading && selectedUpload" class="admin-upload-progress" aria-label="Файл загружается"><i /></span>
        <p
          v-if="uploadBlockedReason"
          id="admin-upload-disabled-reason"
          class="admin-upload-disabled-reason"
          role="status"
        >
          <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
          <span><strong>Загрузка недоступна.</strong> {{ uploadBlockedReason }}</span>
        </p>
      </section>

      <form class="admin-file-tool admin-file-tool--directory" @submit.prevent="emit('createDirectory')">
        <span class="admin-file-tool__icon"><i class="fa-solid fa-folder-open" aria-hidden="true" /></span>
        <div>
          <strong>Создать каталог</strong>
          <p>Новый каталог появится внутри текущего пути.</p>
        </div>
        <input :value="newDirectoryName" type="text" maxlength="180" placeholder="Название каталога" @input="updateDirectoryName">
        <button class="button button--ghost" type="submit" :disabled="!newDirectoryName.trim() || !writable || loading">
          <i class="fa-solid fa-plus" aria-hidden="true" /><span>Создать</span>
        </button>
      </form>
    </div>

    <div class="admin-toolbar admin-files__toolbar">
      <button class="button button--ghost" type="button" :disabled="parent === null || loading" @click="emit('navigate', parent || '')">← Выше</button>
      <button class="button button--ghost" type="button" :disabled="loading" @click="emit('reload')">Обновить</button>
      <label class="admin-files__search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
        <input v-model="searchQuery" type="search" placeholder="Найти файл или каталог">
        <span v-if="searchQuery">{{ filteredEntries.length }}/{{ entries.length }}</span>
      </label>
      <code>/uploads{{ path ? `/${path}` : '' }}</code>
    </div>

    <div class="admin-files__list" role="table" aria-label="Файлы каталога">
      <div class="admin-files__row admin-files__row--head" role="row">
        <span>Имя</span><span>Тип</span><span>Размер</span><span>Изменён</span><span>Действия</span>
      </div>
      <p v-if="!entries.length" class="admin-files__empty">Каталог пуст.</p>
      <p v-else-if="!filteredEntries.length" class="admin-files__empty">По запросу ничего не найдено.</p>
      <div v-for="entry in filteredEntries" :key="entry.path" class="admin-files__row" role="row">
        <button class="admin-files__name" type="button" @click="emit('open', entry)">
          <span class="admin-files__preview" :class="{ 'admin-files__preview--image': isPreviewable(entry) }">
            <img
              v-if="isPreviewable(entry)"
              :src="previewUrl(entry)"
              :alt="`Превью ${entry.name}`"
              loading="lazy"
              decoding="async"
              @error="markPreviewFailed(entry)"
            >
            <i v-else class="fa-solid" :class="entry.type === 'directory' ? 'fa-folder-open' : 'fa-rectangle-list'" aria-hidden="true" />
          </span>
          <span><strong>{{ entry.name }}</strong><small>{{ entry.path }}</small></span>
        </button>
        <span class="admin-files__mime">{{ entry.type === 'directory' ? 'Каталог' : entry.mime }}</span>
        <span>{{ entry.type === 'directory' ? '—' : formatBytes(entry.size) }}</span>
        <span>{{ formatDate(entry.modified) }}</span>
        <span class="admin-files__actions">
          <button type="button" title="Открыть" @click="emit('open', entry)">↗</button>
          <button type="button" title="Переименовать" :disabled="!writable || loading" @click="emit('rename', entry)">✎</button>
          <button class="danger" type="button" title="Удалить" :disabled="!writable || loading" @click="emit('remove', entry)">×</button>
        </span>
      </div>
    </div>
  </section>
</template>
