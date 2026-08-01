<script setup lang="ts">
import { ref } from 'vue'
import type { FeedbackMessage, ProfileSettingsFormModel, SkinResource } from '@engine/contracts/user-pages'
import MinecraftIdentityOption from './MinecraftIdentityOption.vue'

const props = defineProps<{
  form: ProfileSettingsFormModel
  avatarPreview: string
  avatarSelected: boolean
  uploading: boolean
  photoFeedback: FeedbackMessage | null
  accent: string
  showSkinSettings: boolean
  viewerGroupTag: string
  minecraftUuid: string
  minecraftFrontPreview: string
  minecraftBackPreview: string
  minecraftPreviewLoading: boolean
  minecraftSelectedSkinName: string
  minecraftSelectedSkinSize: number
  minecraftSelectedCloakName: string
  minecraftSelectedCloakSize: number
  minecraftSkinInputVersion: number
  minecraftCloakInputVersion: number
  minecraftBusy: SkinResource | null
  minecraftFeedback: FeedbackMessage | null
}>()
const emit = defineEmits<{
  selectAvatar: [event: Event]
  uploadAvatar: []
  selectMinecraft: [type: SkinResource, event: Event]
  uploadMinecraft: [type: SkinResource]
  removeMinecraft: [type: SkinResource]
  refreshMinecraft: []
  'update:accent': [value: string]
}>()
const previewFailed = ref(false)
function updateAccent(event: Event): void { emit('update:accent', (event.target as HTMLInputElement).value) }
</script>

<template>
  <section class="settings-panel">
    <div class="avatar-editor">
      <img v-if="avatarPreview && !previewFailed" :src="avatarPreview" :alt="form.login" @load="previewFailed = false" @error="previewFailed = true">
      <span v-else class="avatar-editor__fallback">{{ form.login.slice(0, 1).toUpperCase() || '?' }}</span>
      <div>
        <strong>Фото профиля</strong>
        <p>JPEG, PNG, WebP или GIF, от 64×64 до 4096×4096, максимум 5 МБ.</p>
        <label class="button button--ghost file-button">
          <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="emit('selectAvatar', $event)">
          <span>Выбрать изображение</span>
        </label>
        <button class="button button--primary" type="button" :disabled="!avatarSelected || uploading" @click="emit('uploadAvatar')">
          {{ uploading ? 'Загрузка…' : 'Загрузить' }}
        </button>
      </div>
    </div>
    <p v-if="photoFeedback" class="form-feedback" :class="{ 'form-feedback--success': photoFeedback.type === 'success' }">{{ photoFeedback.message }}</p>
    <MinecraftIdentityOption
      v-if="showSkinSettings"
      :uuid="minecraftUuid"
      :viewer-group-tag="viewerGroupTag"
      :front-preview="minecraftFrontPreview"
      :back-preview="minecraftBackPreview"
      :preview-loading="minecraftPreviewLoading"
      :selected-skin-name="minecraftSelectedSkinName"
      :selected-skin-size="minecraftSelectedSkinSize"
      :selected-cloak-name="minecraftSelectedCloakName"
      :selected-cloak-size="minecraftSelectedCloakSize"
      :skin-input-version="minecraftSkinInputVersion"
      :cloak-input-version="minecraftCloakInputVersion"
      :busy="minecraftBusy"
      :feedback="minecraftFeedback"
      @select="(type, event) => emit('selectMinecraft', type, event)"
      @upload="emit('uploadMinecraft', $event)"
      @remove="emit('removeMinecraft', $event)"
      @refresh="emit('refreshMinecraft')"
    />
    <label class="accent-picker">
      <span>Акцент профиля</span>
      <div><input :value="accent" type="color" @input="updateAccent"><code>{{ accent }}</code></div>
    </label>
  </section>
</template>
