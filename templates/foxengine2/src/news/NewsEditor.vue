<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import type { NewsDraft } from '@modules/News/client/types'
import { newsDraft, uploadNewsCover } from '@modules/News/client/newsApi'

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
watch(() => props.initial, (value) => Object.assign(draft, newsDraft(value)), { deep: true, immediate: true })

async function selectCover(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  coverError.value = ''
  uploadingCover.value = true
  try {
    const response = await uploadNewsCover(file)
    draft.coverImage = response.coverImage
  } catch (error) {
    coverError.value = error instanceof Error ? error.message : 'Не удалось загрузить обложку.'
  } finally {
    uploadingCover.value = false
  }
}
</script>

<template>
  <form class="news-editor" @submit.prevent="emit('save', { ...draft })">
    <p class="news-editor__hint">Автор публикации — текущий администратор.</p>

    <div class="news-editor__cover-field">
      <div class="news-editor__cover-preview">
        <img v-if="draft.coverImage" :src="draft.coverImage" alt="Превью обложки публикации">
        <i v-else class="fa-solid fa-image" aria-hidden="true" />
      </div>
      <div class="news-editor__cover-controls">
        <strong>Обложка публикации</strong>
        <p>Квадратное изображение отображается слева в круглой рамке. До 8 МБ.</p>
        <label class="button button--ghost news-editor__cover-upload">
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp,image/gif,image/avif"
            :disabled="saving || uploadingCover"
            @change="selectCover"
          >
          <i class="fa-solid fa-upload" aria-hidden="true" />
          <span>{{ uploadingCover ? 'Загрузка…' : draft.coverImage ? 'Заменить обложку' : 'Загрузить обложку' }}</span>
        </label>
        <button
          v-if="draft.coverImage"
          class="news-editor__cover-remove"
          type="button"
          :disabled="saving || uploadingCover"
          @click="draft.coverImage = ''"
        ><i class="fa-solid fa-trash-can" aria-hidden="true" /><span>Удалить обложку</span></button>
        <small v-if="coverError" class="news-editor__cover-error">{{ coverError }}</small>
      </div>
    </div>

    <label class="news-editor__field">
      <span>Заголовок</span>
      <input v-model.trim="draft.title" maxlength="160" required>
    </label>
    <label class="news-editor__field">
      <span>Короткий текст</span>
      <textarea v-model.trim="draft.summary" rows="3" maxlength="600" required />
      <small>{{ draft.summary.length }} / 600</small>
    </label>
    <label class="news-editor__field">
      <span>Полный текст</span>
      <textarea v-model.trim="draft.content" rows="12" maxlength="100000" required />
    </label>
    <label class="news-editor__field news-editor__publish">
      <input v-model="draft.isPublished" type="checkbox">
      <span>Опубликовать и показывать на главной</span>
    </label>
    <div class="news-editor__actions">
      <button class="button button--primary" type="submit" :disabled="saving || uploadingCover">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
        <span>{{ saving ? 'Сохранение…' : 'Сохранить' }}</span>
      </button>
      <button class="button button--ghost" type="button" :disabled="saving || uploadingCover" @click="emit('cancel')"><i class="fa-solid fa-xmark" aria-hidden="true" /><span>Отмена</span></button>
      <button v-if="allowDelete" class="button news-editor__delete" type="button" :disabled="saving || uploadingCover" @click="emit('remove')"><i class="fa-solid fa-trash-can" aria-hidden="true" /><span>Удалить</span></button>
    </div>
  </form>
</template>
