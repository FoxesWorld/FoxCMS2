<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import UiCheckbox from '@/components/UiCheckbox.vue'
import ImageUploadField from '@/components/ImageUploadField.vue'
import type { NewsDraft } from '@modules/News/client/types'
import { newsDraft, uploadNewsCover } from '@modules/News/client/newsApi'

interface EditorController {
  setValue(value: string): void
  setDisabled(disabled: boolean): void
  destroy(): void
}

interface EditorBridge {
  mount(element: HTMLElement, settings: Record<string, unknown>): Promise<EditorController>
}

declare global {
  interface Window {
    FoxNewsFroala?: EditorBridge
  }
}

const FROALA_ADAPTER_URL = '/templates/foxengine2/Froala/js/fox-news-adapter.js'
let adapterPromise: Promise<EditorBridge> | null = null

function loadFroalaAdapter(): Promise<EditorBridge> {
  if (window.FoxNewsFroala) return Promise.resolve(window.FoxNewsFroala)
  if (adapterPromise) return adapterPromise

  adapterPromise = new Promise<EditorBridge>((resolve, reject) => {
    const finish = (): void => {
      if (window.FoxNewsFroala) resolve(window.FoxNewsFroala)
      else reject(new Error('Адаптер Froala загрузился, но редактор не зарегистрирован.'))
    }

    const fail = (): void => reject(new Error('Не удалось загрузить локальный Froala Editor.'))
    const existing = document.querySelector<HTMLScriptElement>('script[data-fox-news-froala="true"]')

    if (existing) {
      existing.addEventListener('load', finish, { once: true })
      existing.addEventListener('error', fail, { once: true })
      queueMicrotask(() => {
        if (window.FoxNewsFroala) finish()
      })
      return
    }

    const script = document.createElement('script')
    script.src = FROALA_ADAPTER_URL
    script.async = true
    script.dataset.foxNewsFroala = 'true'
    script.addEventListener('load', finish, { once: true })
    script.addEventListener('error', fail, { once: true })
    document.head.appendChild(script)
  }).catch((error: unknown) => {
    adapterPromise = null
    throw error
  })

  return adapterPromise
}

const props = withDefaults(defineProps<{
  initial?: Partial<NewsDraft>
  saving?: boolean
  allowDelete?: boolean
}>(), {
  initial: () => ({}),
  saving: false,
  allowDelete: false,
})

const emit = defineEmits<{
  save: [draft: NewsDraft]
  cancel: []
  remove: []
}>()

const draft = reactive<NewsDraft>(newsDraft())
const uploadingCover = ref(false)
const coverError = ref('')
const contentError = ref('')
const editorHost = ref<HTMLElement | null>(null)
const editorLoading = ref(true)
const editorFailed = ref(false)
let editor: EditorController | null = null
let syncing = false

watch(() => props.initial, (value) => {
  Object.assign(draft, newsDraft(value))
  coverError.value = ''
  contentError.value = ''
}, { deep: true, immediate: true })

watch(() => props.saving, (value) => editor?.setDisabled(value))

watch(() => draft.content, (value) => {
  if (!syncing) editor?.setValue(value)
})

onMounted(async () => {
  try {
    if (!editorHost.value) throw new Error('Контейнер редактора не найден.')

    const bridge = await loadFroalaAdapter()
    editor = await bridge.mount(editorHost.value, {
      value: draft.content,
      disabled: props.saving,
      placeholder: 'Расскажите о событии, обновлении или объявлении…',
      maximumLength: 100_000,
      onChange: (value: string) => {
        syncing = true
        draft.content = value
        contentError.value = ''
        queueMicrotask(() => { syncing = false })
      },
    })
  } catch (error) {
    editorFailed.value = true
    contentError.value = error instanceof Error
      ? error.message
      : 'Не удалось запустить Froala Editor.'
  } finally {
    editorLoading.value = false
  }
})

onBeforeUnmount(() => {
  editor?.destroy()
  editor = null
})

async function selectCover(file: File): Promise<void> {
  coverError.value = ''
  uploadingCover.value = true
  try {
    draft.coverImage = (await uploadNewsCover(file)).coverImage
  } catch (error) {
    coverError.value = error instanceof Error
      ? error.message
      : 'Не удалось загрузить обложку.'
  } finally {
    uploadingCover.value = false
  }
}

function submit(): void {
  const content = draft.content.trim()
  const plain = content
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/gi, ' ')
    .trim()

  if (!plain) {
    contentError.value = 'Добавьте текст публикации.'
    return
  }

  emit('save', {
    title: draft.title.trim(),
    summary: draft.summary.trim(),
    content,
    coverImage: draft.coverImage.trim(),
    isPublished: draft.isPublished,
  })
}
</script>

<template>
  <form class="news-editor" @submit.prevent="submit">
    <header class="news-editor__header">
      <h2>{{ allowDelete ? 'Редактирование новости' : 'Новая публикация' }}</h2>
    </header>

    <div class="news-editor__overview">
      <aside class="news-editor__cover-panel">
        <ImageUploadField
          class="news-editor__image-upload"
          title="Фото новости"
          description="Круглая обложка в ленте и в полной новости"
          :preview="draft.coverImage"
          preview-mode="circle"
          :disabled="saving"
          :uploading="uploadingCover"
          :error="coverError"
          @select="selectCover"
          @clear="draft.coverImage = ''"
        />
      </aside>

      <section class="news-editor__basics">
        <label class="news-editor__field news-editor__field--title">
          <span>Заголовок</span>
          <input v-model="draft.title" maxlength="160" required :disabled="saving">
        </label>

        <label class="news-editor__field">
          <span>Краткое описание</span>
          <textarea v-model="draft.summary" rows="5" maxlength="600" required :disabled="saving" />
          <small>{{ draft.summary.length }} / 600</small>
        </label>
      </section>
    </div>

    <section class="news-editor__body">
      <div class="froala-field">
        <div v-if="editorLoading" class="froala-field__loading" aria-live="polite">
          <i class="fa-solid fa-spinner" aria-hidden="true" />
          <span>Загрузка Froala Editor…</span>
        </div>

        <textarea
          v-if="!editorLoading && editorFailed"
          v-model="draft.content"
          class="froala-field__fallback"
          maxlength="100000"
          rows="16"
          :disabled="saving"
        />

        <div
          ref="editorHost"
          v-show="!editorFailed"
          class="froala-field__host"
        />
      </div>

      <p v-if="contentError" class="news-editor__error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
        <span>{{ contentError }}</span>
      </p>
    </section>

    <UiCheckbox
      v-model="draft.isPublished"
      class="news-editor__publish"
      variant="switch"
      label="Опубликовать новость"
      description="Показывать материал в ленте и на главной странице"
    />

    <footer class="news-editor__actions">
      <button class="button button--primary" type="submit" :disabled="saving || uploadingCover">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
        <span>{{ saving ? 'Сохранение…' : 'Сохранить' }}</span>
      </button>

      <button class="button button--ghost" type="button" :disabled="saving || uploadingCover" @click="emit('cancel')">
        <i class="fa-solid fa-xmark" aria-hidden="true" />
        <span>Отмена</span>
      </button>

      <button
        v-if="allowDelete"
        class="button news-editor__delete"
        type="button"
        :disabled="saving || uploadingCover"
        @click="emit('remove')"
      >
        <i class="fa-solid fa-trash-can" aria-hidden="true" />
        <span>Удалить</span>
      </button>
    </footer>
  </form>
</template>
