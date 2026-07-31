<script setup lang="ts">
import type { FeedbackMessage } from '@engine/contracts/user-pages'
import CloakOption from './profile/options/CloakOption.vue'
import SkinOption from './profile/options/SkinOption.vue'
import SkinPreview from './profile/options/SkinPreview.vue'

defineProps<{
  isGuest: boolean
  viewerGroupTag: string
  frontPreview: string
  backPreview: string
  loadingPreview: boolean
  selectedSkinName: string
  selectedCloakName: string
  busy: 'skin' | 'cloak' | null
  feedback: FeedbackMessage | null
}>()
const emit = defineEmits<{
  select: [type: 'skin' | 'cloak', event: Event]
  upload: [type: 'skin' | 'cloak']
  remove: [type: 'skin' | 'cloak']
  navigate: [route: string]
}>()
</script>

<template>
  <div v-if="isGuest" class="system-message system-message--error">
    <strong>Нужна авторизация</strong>
    <p>Скин и плащ привязаны к игровому аккаунту.</p>
    <button class="button button--primary" type="button" @click="emit('navigate', 'auth')">Войти</button>
  </div>
  <article v-else class="content-surface skin-settings">
    <header>
      <span class="eyebrow">Minecraft identity</span>
      <h1>Скин и плащ</h1>
      <p class="lead">PNG-файлы проходят серверную проверку размеров и сохраняются в каталоге текущего пользователя.</p>
    </header>

    <aside v-if="viewerGroupTag === 'admin'" class="notice-panel skin-group-notice">
      <strong>Административный режим</strong>
      <p>Доступен полный набор пользовательских визуальных ресурсов.</p>
    </aside>
    <aside v-else-if="viewerGroupTag === 'tester'" class="notice-panel skin-group-notice">
      <strong>Тестовая группа</strong>
      <p>Визуальные ресурсы доступны для проверки клиентских изменений.</p>
    </aside>

    <SkinPreview :front="frontPreview" :back="backPreview" :loading="loadingPreview" />
    <div class="skin-upload-grid">
      <SkinOption
        :selected-name="selectedSkinName"
        :busy="busy === 'skin'"
        @select="emit('select', 'skin', $event)"
        @upload="emit('upload', 'skin')"
        @remove="emit('remove', 'skin')"
      />
      <CloakOption
        :selected-name="selectedCloakName"
        :busy="busy === 'cloak'"
        @select="emit('select', 'cloak', $event)"
        @upload="emit('upload', 'cloak')"
        @remove="emit('remove', 'cloak')"
      />
    </div>
    <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message || 'Операция завершена.' }}</p>
    <button class="text-button" type="button" @click="emit('navigate', 'profile-settings')">Вернуться к профилю</button>
  </article>
</template>
