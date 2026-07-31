<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  targetLogin: string
  preview?: string
  uploading: boolean
  error: string
}>()
const emit = defineEmits<{ close: []; upload: [file: File] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const canvas = ref<HTMLCanvasElement | null>(null)
const source = ref<HTMLImageElement | null>(null)
const selected = ref(false)
const zoom = ref(1)
const centerX = ref(.5)
const centerY = ref(.5)
const localError = ref('')
let objectUrl = ''
let dragging = false
let lastX = 0
let lastY = 0

const clamp = (value: number): number => Math.max(0, Math.min(1, value))

function revokeUrl(): void {
  if (objectUrl) URL.revokeObjectURL(objectUrl)
  objectUrl = ''
}

function draw(): void {
  const image = source.value
  const target = canvas.value
  if (!image || !target) return
  const context = target.getContext('2d')
  if (!context) return
  const crop = Math.min(image.naturalWidth, image.naturalHeight) / zoom.value
  const sx = (image.naturalWidth - crop) * centerX.value
  const sy = (image.naturalHeight - crop) * centerY.value
  context.drawImage(image, sx, sy, crop, crop, 0, 0, target.width, target.height)
}

async function selectFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  localError.value = ''
  if (!file) return
  if (!file.type.startsWith('image/') || file.size > 5 * 1024 * 1024) {
    localError.value = 'Нужен JPEG, PNG, WebP или GIF до 5 МБ.'
    input.value = ''
    return
  }

  revokeUrl()
  const image = new Image()
  image.onload = async () => {
    if (image.naturalWidth < 64 || image.naturalHeight < 64) {
      localError.value = 'Минимальный размер — 64×64.'
      return
    }
    source.value = image
    selected.value = true
    zoom.value = 1
    centerX.value = .5
    centerY.value = .5
    await nextTick()
    draw()
  }
  image.onerror = () => { localError.value = 'Не удалось прочитать изображение.' }
  objectUrl = URL.createObjectURL(file)
  image.src = objectUrl
}

function pointerDown(event: PointerEvent): void {
  if (!source.value || props.uploading) return
  dragging = true
  lastX = event.clientX
  lastY = event.clientY
  canvas.value?.setPointerCapture(event.pointerId)
}

function pointerMove(event: PointerEvent): void {
  const image = source.value
  const target = canvas.value
  if (!dragging || !image || !target) return
  const rect = target.getBoundingClientRect()
  const crop = Math.min(image.naturalWidth, image.naturalHeight) / zoom.value
  const maxX = image.naturalWidth - crop
  const maxY = image.naturalHeight - crop
  if (maxX > 0) centerX.value = clamp(centerX.value - (event.clientX - lastX) * crop / (rect.width * maxX))
  if (maxY > 0) centerY.value = clamp(centerY.value - (event.clientY - lastY) * crop / (rect.height * maxY))
  lastX = event.clientX
  lastY = event.clientY
  draw()
}

function pointerUp(event: PointerEvent): void {
  dragging = false
  canvas.value?.releasePointerCapture(event.pointerId)
}


async function upload(): Promise<void> {
  const target = canvas.value
  if (!selected.value || !target || props.uploading) return
  const blob = await new Promise<Blob | null>((resolve) => target.toBlob(resolve, 'image/webp', .9))
  if (!blob) {
    localError.value = 'Не удалось подготовить изображение.'
    return
  }
  emit('upload', new File([blob], 'profilePhoto.webp', { type: 'image/webp' }))
}

function close(): void {
  if (!props.uploading) dialog.value?.close()
}

watch(zoom, draw)
onMounted(() => dialog.value?.showModal())
onBeforeUnmount(revokeUrl)
</script>

<template>
  <dialog ref="dialog" class="profile-photo-dialog" aria-labelledby="profile-photo-dialog-title" @close="emit('close')" @cancel="uploading && $event.preventDefault()">
    <header class="profile-photo-dialog__header">
      <h2 id="profile-photo-dialog-title">Фото профиля · {{ targetLogin }}</h2>
      <button class="profile-photo-dialog__close" type="button" aria-label="Закрыть окно" :disabled="uploading" @click="close">×</button>
    </header>

    <div class="profile-photo-dialog__body">
      <div class="profile-photo-dialog__preview">
          <canvas
            v-show="selected"
            ref="canvas"
            width="512"
            height="512"
            @pointerdown="pointerDown"
            @pointermove="pointerMove"
            @pointerup="pointerUp"
            @pointercancel="pointerUp"
          />
          <img v-if="!selected && preview" :src="preview" :alt="`Фото ${targetLogin}`">
          <span v-else-if="!selected">{{ targetLogin.slice(0, 1).toUpperCase() }}</span>
          <div v-if="uploading" class="profile-photo-dialog__busy" aria-label="Загрузка изображения"><span class="profile-photo-spinner" /></div>
      </div>

      <div class="profile-photo-dialog__controls">
        <label class="profile-photo-picker" for="profile-photo-file">
          <b class="profile-photo-picker__icon" aria-hidden="true">＋</b>
          <span><strong>{{ selected ? 'Выбрать другое изображение' : 'Выбрать изображение' }}</strong><small>JPEG, PNG, WebP или GIF · до 5 МБ</small></span>
        </label>
        <input id="profile-photo-file" class="profile-photo-picker__input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" :disabled="uploading" @change="selectFile">

        <div v-if="selected" class="profile-photo-crop-controls">
          <label for="profile-photo-zoom"><span>Масштаб</span><strong>{{ zoom.toFixed(1) }}×</strong></label>
          <input id="profile-photo-zoom" v-model.number="zoom" type="range" min="1" max="3" step="0.05" :disabled="uploading">
        </div>

        <div v-if="localError || error" class="profile-photo-dialog__feedback profile-photo-dialog__feedback--error" role="alert">{{ localError || error }}</div>
      </div>
    </div>

    <footer class="profile-photo-dialog__footer">
      <button class="button button--ghost" type="button" :disabled="uploading" @click="close">Закрыть</button>
      <button class="button button--primary" type="button" :disabled="!selected || uploading" @click="upload">
        <span v-if="uploading" class="profile-photo-spinner profile-photo-spinner--small" aria-hidden="true" />{{ uploading ? 'Загрузка…' : 'Загрузить фото' }}
      </button>
    </footer>
  </dialog>
</template>
