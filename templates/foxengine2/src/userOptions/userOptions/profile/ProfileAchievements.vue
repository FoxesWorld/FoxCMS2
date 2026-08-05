<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@/i18n'
import {
  loadPlayerAchievements,
  type PlayerAchievement,
  type PlayerAchievementSummary,
} from '@engine/achievements/playerAchievements'

const props = defineProps<{ playerUuid: string }>()

const loading = ref(false)
const error = ref('')
const items = ref<PlayerAchievement[]>([])
const summary = ref<PlayerAchievementSummary>({ trackedCount: 0, completedCount: 0, points: 0 })
let controller: AbortController | null = null

const completionPercent = computed(() => {
  if (summary.value.trackedCount <= 0) return 0
  return Math.min(100, Math.round((summary.value.completedCount / summary.value.trackedCount) * 100))
})

watch(
  () => props.playerUuid,
  (uuid) => void refresh(uuid),
  { immediate: true },
)

onBeforeUnmount(() => controller?.abort())

async function refresh(uuid = props.playerUuid): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  const normalizedUuid = uuid.trim()
  items.value = []
  summary.value = { trackedCount: 0, completedCount: 0, points: 0 }
  error.value = ''
  if (!normalizedUuid) return
  loading.value = true
  try {
    const result = await loadPlayerAchievements(normalizedUuid, request.signal)
    items.value = result.items
    summary.value = result.summary
  } catch (reason) {
    if (reason instanceof DOMException && reason.name === 'AbortError') return
    error.value = reason instanceof Error ? reason.message : t('theme.profileachievements.010')
  } finally {
    if (controller === request) {
      loading.value = false
      controller = null
    }
  }
}

function timestamp(value: number | null): string {
  if (!value) return t('theme.profileachievements.011')
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(value * 1000))
}

function progressPercent(item: PlayerAchievement): number {
  if (item.completed) return 100
  return Math.min(100, Math.round((item.progress / Math.max(1, item.target)) * 100))
}
</script>

<template>
  <section class="profile-panel profile-achievements" :aria-label="t('theme.profileachievements.001')">
    <header class="profile-achievements__header">
      <span class="profile-achievements__heading-icon" aria-hidden="true">
        <i class="fa-solid fa-trophy" />
      </span>
      <span class="profile-achievements__heading-copy">
        <small>{{ t('theme.profileachievements.002') }}</small>
        <strong>{{ t('theme.profileachievements.001') }}</strong>
        <span>{{ t('theme.profileachievements.003') }}</span>
      </span>
      <button
        class="profile-achievements__refresh"
        type="button"
        :disabled="loading"
        :aria-label="t('theme.profileachievements.004')"
        @click="refresh()"
      >
        <i class="fa-solid fa-rotate" :class="{ 'profile-achievements__spin': loading }" aria-hidden="true" />
      </button>
    </header>

    <div v-if="loading && items.length === 0" class="profile-achievements__state">
      <i class="fa-solid fa-spinner profile-achievements__spin" aria-hidden="true" />
      <span>{{ t('theme.profileachievements.005') }}</span>
    </div>

    <div v-else-if="error" class="profile-achievements__state profile-achievements__state--error" role="alert">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
      <strong>{{ t('theme.profileachievements.010') }}</strong>
      <span>{{ error }}</span>
    </div>

    <div v-else-if="items.length === 0" class="profile-achievements__state">
      <i class="fa-solid fa-medal" aria-hidden="true" />
      <strong>{{ t('theme.profileachievements.006') }}</strong>
      <span>{{ t('theme.profileachievements.007') }}</span>
    </div>

    <template v-else>
      <div class="profile-achievements__summary">
        <article>
          <small>{{ t('theme.profileachievements.012') }}</small>
          <strong>{{ summary.completedCount }} / {{ summary.trackedCount }}</strong>
          <span>{{ completionPercent }}%</span>
        </article>
        <article>
          <small>{{ t('theme.profileachievements.013') }}</small>
          <strong>{{ summary.points }}</strong>
          <span>{{ t('theme.profileachievements.014') }}</span>
        </article>
      </div>

      <div class="profile-achievements__list">
        <article
          v-for="item in items"
          :key="`${item.serverId}:${item.achievementKey}`"
          class="profile-achievement-card"
          :class="{
            'is-completed': item.completed,
            'is-challenge': item.frameType === 'challenge',
          }"
        >
          <img
            class="profile-achievement-card__icon"
            :src="item.iconDataUrl"
            :alt="''"
            loading="lazy"
            decoding="async"
          >
          <span class="profile-achievement-card__content">
            <small>{{ item.category }} · {{ item.serverId }}</small>
            <strong>{{ item.title }}</strong>
            <span>{{ item.description || t('theme.profileachievements.015') }}</span>
            <span class="profile-achievement-card__progress" aria-hidden="true">
              <span :style="{ width: `${progressPercent(item)}%` }" />
            </span>
          </span>
          <span class="profile-achievement-card__meta">
            <strong>+{{ item.points }}</strong>
            <span>{{ t('theme.profileachievements.014') }}</span>
            <small>{{ timestamp(item.completedAt || item.updatedAt) }}</small>
          </span>
        </article>
      </div>
    </template>
  </section>
</template>
