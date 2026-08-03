<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import ImageUploadField from '@/components/ImageUploadField.vue'

const props = defineProps<{
  targetLogin: string
  preview?: string
  uploading: boolean
  error: string
}>()
const emit = defineEmits<{ close: []; upload: [file: File] }>()

const dialog = ref<HTMLDialogElement | null>(null)
const editorTarget = ref<HTMLElement | null>(null)
const editorActive = ref(false)
const selectedFile = ref<File | null>(null)
const selectedPreview = ref('')
const localError = ref('')

function revokePreview(): void {
  if (selectedPreview.value.startsWith('blob:')) URL.revokeObjectURL(selectedPreview.value)
  selectedPreview.value = ''
}

function selectFile(file: File): void {
  localError.value = ''
  revokePreview()
  selectedFile.value = file
  selectedPreview.value = URL.createObjectURL(file)
}

function clearSelectedFile(): void {
  revokePreview()
  selectedFile.value = null
  localError.value = ''
}

function upload(): void {
  if (!selectedFile.value || props.uploading) return
  emit('upload', selectedFile.value)
}

function close(): void {
  if (!props.uploading) dialog.value?.close()
}

onMounted(() => dialog.value?.showModal())
onBeforeUnmount(revokePreview)
</script>

<template>
  <dialog
    ref="dialog"
    class="profile-photo-dialog"
    aria-labelledby="profile-photo-dialog-title"
    @close="emit('close')"
    @cancel="uploading && $event.preventDefault()"
  >
    <header class="profile-photo-dialog__header">
      <h2 id="profile-photo-dialog-title">Фото профиля · {{ targetLogin }}</h2>
      <button class="profile-photo-dialog__close" type="button" aria-label="Закрыть окно" :disabled="uploading" @click="close">×</button>
    </header>

    <div class="profile-photo-dialog__body" :class="{ 'is-editor-active': editorActive }">
      <div v-show="!editorActive" class="profile-photo-dialog__body-content">
        <div class="profile-photo-dialog__preview">
          <img v-if="selectedPreview || preview" :src="selectedPreview || preview" :alt="`Фото ${targetLogin}`">
          <span v-else>{{ targetLogin.slice(0, 1).toUpperCase() || '?' }}</span>
          <div v-if="uploading" class="profile-photo-dialog__busy" aria-label="Загрузка изображения"><span class="profile-photo-spinner" /></div>
        </div>

        <div class="profile-photo-dialog__controls">
          <ImageUploadField
            title="Фото профиля"
            description="После выбора редактор откроется в этом окне"
            preview-mode="none"
            :editor-target="editorTarget"
            :editor-aspect-ratio="1"
            :editor-target-width="512"
            :editor-target-height="512"
            editor-target-fit="cover"
            :editor-upscale="true"
            editor-mime-type="image/webp"
            editor-output-name="profilePhoto.webp"
            accept="image/jpeg,image/png,image/webp,image/gif"
            :allowed-types="['image/jpeg', 'image/png', 'image/webp', 'image/gif']"
            :maximum-bytes="5_242_880"
            :minimum-width="64"
            :minimum-height="64"
            :maximum-width="4096"
            :maximum-height="4096"
            :disabled="uploading"
            :uploading="uploading"
            :error="localError || error"
            :allow-clear="false"
            hint="Итог: WebP 512×512 · исходник 64×64–4096×4096 · до 5 МБ"
            :choose-label="selectedFile ? 'Выбрать другое изображение' : 'Выбрать изображение'"
            replace-label="Выбрать другое"
            @select="selectFile"
            @clear="clearSelectedFile"
            @invalid="localError = $event"
            @editing-change="editorActive = $event"
          />
        </div>
      </div>

      <div
        v-show="editorActive"
        ref="editorTarget"
        class="profile-photo-dialog__editor-target"
        aria-label="Встроенный редактор фотографии"
      />
    </div>

    <footer class="profile-photo-dialog__footer">
      <button class="button button--ghost" type="button" :disabled="uploading" @click="close">Закрыть</button>
      <button class="button button--primary" type="button" :disabled="!selectedFile || uploading || editorActive" @click="upload">
        <span v-if="uploading" class="profile-photo-spinner profile-photo-spinner--small" aria-hidden="true" />{{ uploading ? 'Загрузка…' : 'Загрузить фото' }}
      </button>
    </footer>
  </dialog>
</template>
