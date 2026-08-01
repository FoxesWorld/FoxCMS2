<script setup lang="ts">
import { computed, ref } from 'vue'
import type { FileEntry } from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  path: string
  parent: string | null
  entries: FileEntry[]
  writable: boolean
  totalBytes: number
  selectedUpload: File | null
  newDirectoryName: string
  loading: boolean
}>()

const emit = defineEmits<{
  navigate: [path: string]
  reload: []
  selectUpload: [event: Event]
  upload: []
  'update:newDirectoryName': [value: string]
  createDirectory: []
  open: [entry: FileEntry]
  rename: [entry: FileEntry]
  remove: [entry: FileEntry]
}>()

const breadcrumbs = computed(() => {
  const parts = props.path.split('/').filter(Boolean)
  return [
    { label: 'uploads', path: '' },
    ...parts.map((part, index) => ({ label: part, path: parts.slice(0, index + 1).join('/') })),
  ]
})

const failedPreviews = ref<Set<string>>(new Set())
const previewableExtensions = new Set(['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif', 'bmp', 'ico', 'svg'])

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
        <p>Безопасное управление пользовательскими текстурами, изображениями, сборками и другими загруженными файлами.</p>
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
      <div class="admin-file-tool">
        <strong>Загрузить файл</strong>
        <p>Максимальный размер одного файла — 64 МБ. Серверные скрипты запрещены.</p>
        <label class="file-button">
          <input :key="selectedUpload?.name || 'empty'" type="file" @change="emit('selectUpload', $event)">
          <span>{{ selectedUpload?.name || 'Выбрать файл' }}</span>
        </label>
        <button class="button button--primary" type="button" :disabled="!selectedUpload || !writable || loading" @click="emit('upload')">
          Загрузить
        </button>
      </div>

      <form class="admin-file-tool" @submit.prevent="emit('createDirectory')">
        <strong>Создать каталог</strong>
        <p>Каталог будет создан внутри текущего пути.</p>
        <input :value="newDirectoryName" type="text" maxlength="180" placeholder="Название каталога" @input="updateDirectoryName">
        <button class="button button--ghost" type="submit" :disabled="!newDirectoryName.trim() || !writable || loading">Создать</button>
      </form>
    </div>

    <div class="admin-toolbar admin-files__toolbar">
      <button class="button button--ghost" type="button" :disabled="parent === null || loading" @click="emit('navigate', parent || '')">← Выше</button>
      <button class="button button--ghost" type="button" :disabled="loading" @click="emit('reload')">Обновить</button>
      <code>/uploads{{ path ? `/${path}` : '' }}</code>
    </div>

    <div class="admin-files__list" role="table" aria-label="Файлы каталога">
      <div class="admin-files__row admin-files__row--head" role="row">
        <span>Имя</span><span>Тип</span><span>Размер</span><span>Изменён</span><span>Действия</span>
      </div>
      <p v-if="!entries.length" class="admin-files__empty">Каталог пуст.</p>
      <div v-for="entry in entries" :key="entry.path" class="admin-files__row" role="row">
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
            <span v-else>{{ entry.type === 'directory' ? '▣' : '▤' }}</span>
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
