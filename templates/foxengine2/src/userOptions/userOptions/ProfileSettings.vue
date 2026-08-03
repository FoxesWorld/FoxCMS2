<script setup lang="ts">
import { t } from '@/i18n'

import { defineAsyncComponent } from 'vue'
import type { FeedbackMessage, ProfileSettingsFormModel, SettingsTab, SkinResource } from '@engine/contracts/user-pages'

const optionLoaders = {
  profile: () => import('./profile/options/ProfileOption.vue'),
  appearance: () => import('./profile/options/AppearanceOption.vue'),
  security: () => import('./profile/options/SecurityOption.vue'),
} satisfies Record<SettingsTab, () => Promise<unknown>>

const ProfileOption = defineAsyncComponent(() => optionLoaders.profile())
const AppearanceOption = defineAsyncComponent(() => optionLoaders.appearance())
const SecurityOption = defineAsyncComponent(() => optionLoaders.security())

function preloadOption(tab: SettingsTab): void {
  void optionLoaders[tab]()
}

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
      <span class="eyebrow">{{ t('theme.useroptions.useroptions.profilesettings.001') }}</span>
      <h1>{{ t('theme.useroptions.useroptions.profilesettings.002') }}</h1>
      <p class="lead">{{ t('theme.useroptions.useroptions.profilesettings.003') }}</p>
    </header>
    <nav class="settings-tabs" :aria-label="t('theme.useroptions.useroptions.profilesettings.004')">
      <button class="button button--primary" type="button" :class="{ active: activeTab === 'profile' }" @pointerenter="preloadOption('profile')" @focus="preloadOption('profile')" @click="emit('update:activeTab', 'profile')">{{ t('theme.useroptions.useroptions.profilesettings.005') }}</button>
      <button class="button button--primary" type="button" :class="{ active: activeTab === 'appearance' }" @pointerenter="preloadOption('appearance')" @focus="preloadOption('appearance')" @click="emit('update:activeTab', 'appearance')">{{ t('theme.useroptions.useroptions.profilesettings.006') }}</button>
      <button class="button button--primary" type="button" :class="{ active: activeTab === 'security' }" @pointerenter="preloadOption('security')" @focus="preloadOption('security')" @click="emit('update:activeTab', 'security')">{{ t('theme.useroptions.useroptions.profilesettings.007') }}</button>
    </nav>
    <form class="settings-form" @submit.prevent="emit('submit')">
      <Suspense timeout="0">
        <template #default>
          <ProfileOption v-if="activeTab === 'profile'" :form="form" />
          <AppearanceOption
            v-else-if="activeTab === 'appearance'"
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
          <SecurityOption v-else :form="form" :require-current-password="!canManageUsers" />
        </template>
        <template #fallback>
          <div class="runtime-panel-skeleton" aria-hidden="true">
            <span /><span /><span />
          </div>
        </template>
      </Suspense>
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <div class="settings-actions">
        <button class="button button--ghost" type="button" @click="emit('navigate', 'profile')">{{ t('theme.useroptions.useroptions.profilesettings.008') }}</button>
        <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? t('theme.useroptions.useroptions.profilesettings.009') : t('theme.useroptions.useroptions.profilesettings.010') }}</button>
      </div>
    </form>
  </article>
</template>
