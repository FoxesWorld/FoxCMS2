<script setup lang="ts">
import { computed } from 'vue'
import type { FeedbackMessage, ProfileSettingsFormModel, SettingsTab } from '@engine/contracts/user-pages'
import AppearanceOption from './profile/options/AppearanceOption.vue'
import ProfileOption from './profile/options/ProfileOption.vue'
import SecurityOption from './profile/options/SecurityOption.vue'

const props = defineProps<{
  isGuest: boolean
  viewerGroup: number
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
  selectAvatar: [event: Event]
  uploadAvatar: []
  navigate: [route: string]
}>()
const requireCurrentPassword = computed(() => props.viewerGroup !== 1)
function setTab(tab: SettingsTab): void { emit('update:activeTab', tab) }
</script>

<template>
  <div v-if="isGuest" class="system-message system-message--error">
    <strong>Нужна авторизация</strong>
    <p>Настройки доступны только владельцу аккаунта.</p>
    <button class="button button--primary" type="button" @click="emit('navigate', 'auth')">Войти</button>
  </div>
  <article v-else class="content-surface settings-page" :style="{ '--settings-accent': accent }">
    <header>
      <span class="eyebrow">Личный кабинет</span>
      <h1>Настройки профиля</h1>
      <p class="lead">Основные данные, внешний вид и безопасность аккаунта.</p>
    </header>

    <aside v-if="viewerGroup === 1" class="notice-panel settings-group-notice">
      <strong>Административная группа</strong>
      <p>Backend применяет административные полномочия. Шаблон только отображает разрешённые операции.</p>
    </aside>
    <aside v-else-if="viewerGroup === 3" class="notice-panel settings-group-notice">
      <strong>Профиль команды проекта</strong>
      <p>Доступны настройки публичного представления участника команды.</p>
    </aside>
    <aside v-else-if="viewerGroup === 4" class="notice-panel settings-group-notice">
      <strong>Профиль модератора</strong>
      <p>Оформление страницы адаптировано для участника команды модерации.</p>
    </aside>

    <nav class="settings-tabs" aria-label="Разделы настроек">
      <button
          class="button button--primary"
          type="button"
          :class="{ active: activeTab === 'profile' }"
          @click="setTab('profile')"
      >
        Профиль
      </button>

      <button
          class="button button--primary"
          type="button"
          :class="{ active: activeTab === 'appearance' }"
          @click="setTab('appearance')"
      >
        Оформление
      </button>

      <button
          class="button button--primary"
          type="button"
          :class="{ active: activeTab === 'security' }"
          @click="setTab('security')"
      >
        Безопасность
      </button>
    </nav>

    <form class="settings-form" @submit.prevent="emit('submit')">
      <ProfileOption v-show="activeTab === 'profile'" :form="form" />
      <AppearanceOption
        v-show="activeTab === 'appearance'"
        :form="form"
        :avatar-preview="avatarPreview"
        :avatar-selected="avatarSelected"
        :uploading="uploading"
        :photo-feedback="photoFeedback"
        :accent="accent"
        @select-avatar="emit('selectAvatar', $event)"
        @upload-avatar="emit('uploadAvatar')"
        @open-skin="emit('navigate', 'skin-settings')"
        @update:accent="emit('update:accent', $event)"
      />
      <SecurityOption v-show="activeTab === 'security'" :form="form" :require-current-password="requireCurrentPassword" />
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <div class="settings-actions">
        <button class="button button--ghost" type="button" @click="emit('navigate', 'profile')">Открыть профиль</button>
        <button class="button button--primary button--large" type="submit" :disabled="submitting">
          {{ submitting ? 'Сохраняем…' : 'Сохранить изменения' }}
        </button>
      </div>
    </form>
  </article>
</template>
