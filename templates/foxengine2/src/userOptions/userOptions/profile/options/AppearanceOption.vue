<script setup lang="ts">
import type { FeedbackMessage, ProfileSettingsFormModel } from '@engine/contracts/user-pages'

const props = defineProps<{
  form: ProfileSettingsFormModel
  avatarPreview: string
  avatarSelected: boolean
  uploading: boolean
  photoFeedback: FeedbackMessage | null
  accent: string
}>()
const emit = defineEmits<{
  selectAvatar: [event: Event]
  uploadAvatar: []
  openSkin: []
  'update:accent': [value: string]
}>()
function updateAccent(event: Event): void {
  emit('update:accent', (event.target as HTMLInputElement).value)
}
</script>

<template>
  <section class="settings-panel">
    <div class="avatar-editor">
      <img :src="avatarPreview" :alt="form.login">
      <div>
        <strong>Фото профиля</strong>
        <p>JPEG, PNG, WebP или GIF, от 64×64 до 4096×4096, максимум 5 МБ.</p>
        <label class="file-button">
          <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="emit('selectAvatar', $event)">
          <span>Выбрать изображение</span>
        </label>
        <button class="button button--primary" type="button" :disabled="!avatarSelected || uploading" @click="emit('uploadAvatar')">
          {{ uploading ? 'Загрузка…' : 'Загрузить' }}
        </button>
      </div>
    </div>
    <p v-if="photoFeedback" class="form-feedback" :class="{ 'form-feedback--success': photoFeedback.type === 'success' }">
      {{ photoFeedback.message }}
    </p>
    <div class="notice-panel settings-skin-link">
      <strong>Minecraft-образ</strong>
      <p>Скин и плащ управляются отдельно, с серверной проверкой размеров и предпросмотром.</p>
      <button class="button button--ghost" type="button" @click="emit('openSkin')">Открыть настройки скина</button>
    </div>
    <label class="accent-picker">
      <span>Акцент профиля</span>
      <div><input :value="accent" type="color" @input="updateAccent"><code>{{ accent }}</code></div>
    </label>
  </section>
</template>
