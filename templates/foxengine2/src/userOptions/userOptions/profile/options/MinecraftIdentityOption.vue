<script setup lang="ts">
import type { FeedbackMessage, SkinResource } from '@engine/contracts/user-pages'
import CloakOption from './CloakOption.vue'
import SkinOption from './SkinOption.vue'
import SkinPreview from './SkinPreview.vue'

const props = defineProps<{
  uuid: string
  viewerGroupTag: string
  frontPreview: string
  backPreview: string
  previewLoading: boolean
  selectedSkinName: string
  selectedSkinSize: number
  selectedCloakName: string
  selectedCloakSize: number
  skinInputVersion: number
  cloakInputVersion: number
  busy: SkinResource | null
  feedback: FeedbackMessage | null
}>()

const emit = defineEmits<{
  select: [type: SkinResource, event: Event]
  upload: [type: SkinResource]
  remove: [type: SkinResource]
  refresh: []
}>()

function compactUuid(value: string): string {
  return value.replaceAll('-', '')
}

function groupLabel(groupTag: string): string {
  if (groupTag === 'admin') return 'Административный доступ'
  if (groupTag === 'tester') return 'Тестовая группа'
  return 'Игровая идентичность'
}
</script>

<template>
  <section id="minecraft-identity" class="minecraft-identity" aria-labelledby="minecraft-identity-title">
    <header class="minecraft-identity__header">
      <div>
        <span class="eyebrow">Minecraft identity</span>
        <h2 id="minecraft-identity-title">Скин и плащ</h2>
        <p>Игровой образ привязан к UUID аккаунта. PNG проходит серверную проверку размеров и публикуется через Authlib-профиль.</p>
      </div>
      <div class="minecraft-identity__identity">
        <span>{{ groupLabel(viewerGroupTag) }}</span>
        <code :title="uuid">{{ compactUuid(uuid) }}</code>
        <button class="button button--ghost" type="button" :disabled="previewLoading || busy !== null" @click="emit('refresh')">
          <i class="fa-solid fa-rotate" aria-hidden="true" />
          <span>{{ previewLoading ? 'Обновление…' : 'Обновить preview' }}</span>
        </button>
      </div>
    </header>

    <div class="minecraft-identity__status">
      <span><i class="fa-solid fa-shield-halved" aria-hidden="true" /> UUID-based storage</span>
      <span><i class="fa-solid fa-image" aria-hidden="true" /> Только PNG</span>
      <span><i class="fa-solid fa-cube" aria-hidden="true" /> Front / Back preview</span>
    </div>

    <SkinPreview :front="frontPreview" :back="backPreview" :loading="previewLoading" />

    <div class="skin-upload-grid">
      <SkinOption
        :key="`skin-${skinInputVersion}`"
        :selected-name="selectedSkinName"
        :selected-size="selectedSkinSize"
        :busy="busy === 'skin'"
        :locked="busy !== null && busy !== 'skin'"
        @select="emit('select', 'skin', $event)"
        @upload="emit('upload', 'skin')"
        @remove="emit('remove', 'skin')"
      />
      <CloakOption
        :key="`cloak-${cloakInputVersion}`"
        :selected-name="selectedCloakName"
        :selected-size="selectedCloakSize"
        :busy="busy === 'cloak'"
        :locked="busy !== null && busy !== 'cloak'"
        @select="emit('select', 'cloak', $event)"
        @upload="emit('upload', 'cloak')"
        @remove="emit('remove', 'cloak')"
      />
    </div>

    <p v-if="feedback" class="form-feedback minecraft-identity__feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">
      {{ feedback.message || 'Операция завершена.' }}
    </p>
  </section>
</template>
