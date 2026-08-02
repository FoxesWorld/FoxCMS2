<script setup lang="ts">
import type { FeedbackMessage, ProfileSettingsFormModel, SettingsTab, SkinResource } from '@engine/contracts/user-pages'
import AppearanceOption from './profile/options/AppearanceOption.vue'
import ProfileOption from './profile/options/ProfileOption.vue'
import SecurityOption from './profile/options/SecurityOption.vue'

defineProps<{
  canManageUsers: boolean
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
  activeTab: SettingsTab
  form: ProfileSettingsFormModel
  avatarPreview: string
  avatarSelected: boolean
  uploading: boolean
  photoFeedback: FeedbackMessage | null
  feedback: FeedbackMessage | null
  accent: string
  submitting: boolean
}>()
const emit = defineEmits<{
  'update:activeTab': [value: SettingsTab]
  'update:accent': [value: string]
  submit: []
  selectAvatar: [file: File]
  clearAvatar: []
  uploadAvatar: []
  selectMinecraft: [type: SkinResource, event: Event]
  uploadMinecraft: [type: SkinResource]
  removeMinecraft: [type: SkinResource]
  refreshMinecraft: []
  navigate: [route: string]
}>()
</script>

<template>
  <article class="content-surface settings-page" :style="{ '--settings-accent': accent }">
    <header>
      <span class="eyebrow">Личный кабинет</span>
      <h1>Настройки профиля</h1>
      <p class="lead">Основные данные, внешний вид и безопасность аккаунта.</p>
    </header>
    <nav class="settings-tabs" aria-label="Разделы настроек">
      <button class="button button--primary" type="button" :class="{ active: activeTab === 'profile' }" @click="emit('update:activeTab', 'profile')">Профиль</button>
      <button class="button button--primary" type="button" :class="{ active: activeTab === 'appearance' }" @click="emit('update:activeTab', 'appearance')">Оформление</button>
      <button class="button button--primary" type="button" :class="{ active: activeTab === 'security' }" @click="emit('update:activeTab', 'security')">Безопасность</button>
    </nav>
    <form class="settings-form" @submit.prevent="emit('submit')">
      <ProfileOption v-show="activeTab === 'profile'" :form="form" />
      <AppearanceOption
        v-show="activeTab === 'appearance'"
        :key="avatarPreview"
        :form="form"
        :avatar-preview="avatarPreview"
        :avatar-selected="avatarSelected"
        :uploading="uploading"
        :photo-feedback="photoFeedback"
        :accent="accent"
        :show-skin-settings="showSkinSettings"
        :viewer-group-tag="viewerGroupTag"
        :minecraft-uuid="minecraftUuid"
        :minecraft-front-preview="minecraftFrontPreview"
        :minecraft-back-preview="minecraftBackPreview"
        :minecraft-preview-loading="minecraftPreviewLoading"
        :minecraft-selected-skin-name="minecraftSelectedSkinName"
        :minecraft-selected-skin-size="minecraftSelectedSkinSize"
        :minecraft-selected-cloak-name="minecraftSelectedCloakName"
        :minecraft-selected-cloak-size="minecraftSelectedCloakSize"
        :minecraft-skin-input-version="minecraftSkinInputVersion"
        :minecraft-cloak-input-version="minecraftCloakInputVersion"
        :minecraft-busy="minecraftBusy"
        :minecraft-feedback="minecraftFeedback"
        @select-avatar="emit('selectAvatar', $event)"
        @clear-avatar="emit('clearAvatar')"
        @upload-avatar="emit('uploadAvatar')"
        @select-minecraft="(type, event) => emit('selectMinecraft', type, event)"
        @upload-minecraft="emit('uploadMinecraft', $event)"
        @remove-minecraft="emit('removeMinecraft', $event)"
        @refresh-minecraft="emit('refreshMinecraft')"
        @update:accent="emit('update:accent', $event)"
      />
      <SecurityOption v-show="activeTab === 'security'" :form="form" :require-current-password="!canManageUsers" />
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <div class="settings-actions">
        <button class="button button--ghost" type="button" @click="emit('navigate', 'profile')">Открыть профиль</button>
        <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? 'Сохраняем…' : 'Сохранить изменения' }}</button>
      </div>
    </form>
  </article>
</template>
