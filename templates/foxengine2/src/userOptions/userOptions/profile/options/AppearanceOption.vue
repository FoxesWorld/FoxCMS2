<script setup lang="ts">
import { t } from '@/i18n'

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
      :title="t('theme.useroptions.useroptions.profile.options.appearanceoption.001')"
      :description="t('theme.useroptions.useroptions.profile.options.appearanceoption.002')"
      :preview="avatarPreview"
      :preview-alt="form.login"
      preview-mode="circle"
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
      :uploading="uploading"
      :error="photoFeedback?.type === 'error' ? photoFeedback.message : ''"
      :allow-clear="false"
      :hint="t('theme.useroptions.useroptions.profile.options.appearanceoption.003')"
      :choose-label="t('theme.useroptions.useroptions.profile.options.appearanceoption.004')"
      :replace-label="t('theme.useroptions.useroptions.profile.options.appearanceoption.005')"
      @select="emit('selectAvatar', $event)"
      @clear="emit('clearAvatar')"
    >
      <template #actions>
        <button class="button button--primary" type="button" :disabled="!avatarSelected || uploading" @click="emit('uploadAvatar')">
          <i class="fa-solid" :class="uploading ? 'fa-spinner' : 'fa-upload'" aria-hidden="true" />
          <span>{{ uploading ? t('theme.useroptions.useroptions.profile.options.appearanceoption.006') : t('theme.useroptions.useroptions.profile.options.appearanceoption.007') }}</span>
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
      <span>{{ t('theme.useroptions.useroptions.profile.options.appearanceoption.008') }}</span>
      <div><input :value="accent" type="color" @input="updateAccent"><code>{{ accent }}</code></div>
    </label>
  </section>
</template>
