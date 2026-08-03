<script setup lang="ts">
import { t } from '@/i18n'

import { reactive, ref, watch } from 'vue'
import UiCheckbox from '@/components/UiCheckbox.vue'
import ImageUploadField from '@/components/ImageUploadField.vue'
import type { NewsDraft } from '@modules/News/client/types'
import { newsDraft, uploadNewsCover } from '@modules/News/client/newsApi'

import TiptapEditor from './TiptapEditor.vue'

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

watch(() => props.initial, (value) => {
  Object.assign(draft, newsDraft(value))
  coverError.value = ''
  contentError.value = ''
}, { deep: true, immediate: true })



async function selectCover(file: File): Promise<void> {
  coverError.value = ''
  uploadingCover.value = true
  try {
    draft.coverImage = (await uploadNewsCover(file)).coverImage
  } catch (error) {
    coverError.value = error instanceof Error
      ? error.message
      : t('theme.news.newseditor.014')
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
    contentError.value = t('theme.news.newseditor.015')
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
      <h2>{{ allowDelete ? t('theme.news.newseditor.001') : t('theme.news.newseditor.002') }}</h2>
    </header>

    <div class="news-editor__overview">
      <aside class="news-editor__cover-panel">
        <ImageUploadField
          class="news-editor__image-upload"
          :title="t('theme.news.newseditor.003')"
          :description="t('theme.news.newseditor.004')"
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
          <span>{{ t('theme.news.newseditor.005') }}</span>
          <input v-model="draft.title" maxlength="160" required :disabled="saving">
        </label>

        <label class="news-editor__field">
          <span>{{ t('theme.news.newseditor.006') }}</span>
          <textarea v-model="draft.summary" rows="5" maxlength="600" required :disabled="saving" />
          <small>{{ draft.summary.length }} / 600</small>
        </label>
      </section>
    </div>

    <section class="news-editor__body">
      <TiptapEditor
        v-model="draft.content"
        :disabled="saving"
        :placeholder="t('theme.news.newseditor.007')"
        :maximum-length="100000"
        @update:model-value="contentError = ''"
      />

      <p v-if="contentError" class="news-editor__error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
        <span>{{ contentError }}</span>
      </p>
    </section>

    <UiCheckbox
      v-model="draft.isPublished"
      class="news-editor__publish"
      variant="switch"
      :label="t('theme.news.newseditor.008')"
      :description="t('theme.news.newseditor.009')"
    />

    <footer class="news-editor__actions">
      <button class="button button--primary" type="submit" :disabled="saving || uploadingCover">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
        <span>{{ saving ? t('theme.news.newseditor.010') : t('theme.news.newseditor.011') }}</span>
      </button>

      <button class="button button--ghost" type="button" :disabled="saving || uploadingCover" @click="emit('cancel')">
        <i class="fa-solid fa-xmark" aria-hidden="true" />
        <span>{{ t('theme.news.newseditor.012') }}</span>
      </button>

      <button
        v-if="allowDelete"
        class="button news-editor__delete"
        type="button"
        :disabled="saving || uploadingCover"
        @click="emit('remove')"
      >
        <i class="fa-solid fa-trash-can" aria-hidden="true" />
        <span>{{ t('theme.news.newseditor.013') }}</span>
      </button>
    </footer>
  </form>
</template>
