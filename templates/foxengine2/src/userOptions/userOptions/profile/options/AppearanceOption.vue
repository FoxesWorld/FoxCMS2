<script setup lang="ts">
import ImageUploadField from '@/components/ImageUploadField.vue'
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
  selectAvatar: [file: File]
  clearAvatar: []
  uploadAvatar: []
  selectMinecraft: [type: SkinResource, event: Event]
  uploadMinecraft: [type: SkinResource]
  removeMinecraft: [type: SkinResource]
  refreshMinecraft: []
  'update:accent': [value: string]
}>()
function updateAccent(event: Event): void { emit('update:accent', (event.target as HTMLInputElement).value) }
</script>

<template>
  <section class="settings-panel">
    <ImageUploadField
      class="avatar-image-upload"
      title="Фото профиля"
      description="Единая форма загрузки с drag-and-drop и предпросмотром"
      :preview="avatarPreview"
      :preview-alt="form.login"
      preview-mode="circle"
      accept="image/jpeg,image/png,image/webp,image/gif"
      :allowed-types="['image/jpeg', 'image/png', 'image/webp', 'image/gif']"
      :maximum-bytes="5_242_880"
      :minimum-width="64"
      :minimum-height="64"
      :maximum-width="4096"
      :maximum-height="4096"
      :uploading="uploading"
      :error="photoFeedback?.type === 'error' ? photoFeedback.message : ''"
      :allow-clear="false"
      hint="JPEG, PNG, WebP или GIF · 64×64–4096×4096 · до 5 МБ"
      choose-label="Выбрать фото"
      replace-label="Выбрать другое"
      @select="emit('selectAvatar', $event)"
      @clear="emit('clearAvatar')"
    >
      <template #actions>
        <button class="button button--primary" type="button" :disabled="!avatarSelected || uploading" @click="emit('uploadAvatar')">
          <i class="fa-solid" :class="uploading ? 'fa-spinner' : 'fa-upload'" aria-hidden="true" />
          <span>{{ uploading ? 'Загрузка…' : 'Загрузить фото' }}</span>
        </button>
      </template>
    </ImageUploadField>
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
