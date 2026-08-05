<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { appBootstrap } from '@/app/context'
import { bootstrapBoolean, bootstrapString } from '@/domain/bootstrap'
import { t } from '@/i18n'
import {
  achievementIdentity,
  loadPlayerAchievementsByIdentity,
  type PlayerAchievement,
  type PlayerAchievementSummary,
} from '@/achievements/playerAchievements'

type StatusFilter = 'all' | 'completed' | 'locked' | 'challenge'

const AchievementStatisticsTree = defineAsyncComponent(
  () => import('@/achievements/AchievementStatisticsTree.vue'),
)

const route = useRoute()
const router = useRouter()
const items = ref<PlayerAchievement[]>([])
const summary = ref<PlayerAchievementSummary>({ trackedCount: 0, completedCount: 0, points: 0 })
const loading = ref(false)
const error = ref('')
const search = ref('')
const playerInput = ref('')
const status = ref<StatusFilter>('all')
const server = ref('all')
const category = ref('all')
let controller: AbortController | null = null

const isLogged = computed(() => bootstrapBoolean(appBootstrap, 'isLogged'))
const currentLogin = computed(() => bootstrapString(appBootstrap, 'login'))
const currentUuid = computed(() => bootstrapString(appBootstrap, 'uuid'))
const requestedIdentity = computed(() => {
  const raw = route.params.value
  return Array.isArray(raw) ? String(raw[0] ?? '').trim() : String(raw ?? '').trim()
})
const statisticsMode = computed(() => {
  const raw = route.query.view
  const requestedView = Array.isArray(raw) ? String(raw[0] ?? '') : String(raw ?? '')
  return requestedView === 'statistics' || (!requestedIdentity.value && !isLogged.value)
})
const playerIdentity = computed(() => statisticsMode.value
  ? ''
  : requestedIdentity.value || currentUuid.value || currentLogin.value)
const playerName = computed(() =>
  items.value.find((item) => item.playerName.trim())?.playerName
  || (playerIdentity.value && playerIdentity.value !== currentUuid.value ? playerIdentity.value : currentLogin.value)
  || t('engine.views.achievements.034'))
const completionPercent = computed(() => {
  if (summary.value.trackedCount <= 0) return 0
  return Math.min(100, Math.round((summary.value.completedCount / summary.value.trackedCount) * 100))
})
const remainingCount = computed(() => Math.max(0, summary.value.trackedCount - summary.value.completedCount))
const challengeCount = computed(() => items.value.filter((item) => item.frameType === 'challenge').length)
const completedChallengeCount = computed(() =>
  items.value.filter((item) => item.frameType === 'challenge' && item.completed).length)
const servers = computed(() => [...new Set(items.value.map((item) => item.serverId).filter(Boolean))].sort())
const categories = computed(() => [...new Set(items.value.map((item) => item.category).filter(Boolean))].sort())
const filteredItems = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase('ru-RU')
  return items.value.filter((item) => {
    if (status.value === 'completed' && !item.completed) return false
    if (status.value === 'locked' && item.completed) return false
    if (status.value === 'challenge' && item.frameType !== 'challenge') return false
    if (server.value !== 'all' && item.serverId !== server.value) return false
    if (category.value !== 'all' && item.category !== category.value) return false
    if (!needle) return true
    return [item.title, item.description, item.achievementKey, item.iconItem, item.category, item.serverId]
      .some((value) => value.toLocaleLowerCase('ru-RU').includes(needle))
  })
})
const recentAchievements = computed(() => items.value
  .filter((item) => item.completed)
  .sort((left, right) => (right.completedAt ?? right.updatedAt) - (left.completedAt ?? left.updatedAt))
  .slice(0, 5))

watch(playerIdentity, (identity) => void refresh(identity), { immediate: true })
onBeforeUnmount(() => controller?.abort())

async function refresh(identity = playerIdentity.value): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  items.value = []
  summary.value = { trackedCount: 0, completedCount: 0, points: 0 }
  error.value = ''
  if (!identity) {
    loading.value = false
    return
  }
  loading.value = true
  try {
    const response = await loadPlayerAchievementsByIdentity(achievementIdentity(identity), request.signal)
    items.value = response.items
    summary.value = response.summary
  } catch (reason) {
    if (reason instanceof DOMException && reason.name === 'AbortError') return
    error.value = reason instanceof Error ? reason.message : t('engine.views.achievements.018')
  } finally {
    if (controller === request) {
      loading.value = false
      controller = null
    }
  }
}

async function openPlayer(): Promise<void> {
  const identity = playerInput.value.trim()
  if (!identity) return
  await router.push({ name: 'achievements', params: { value: identity }, query: {} })
  playerInput.value = ''
}

async function openMyAchievements(): Promise<void> {
  const identity = currentLogin.value || currentUuid.value
  if (!identity) return
  await router.push({ name: 'achievements', params: { value: identity }, query: {} })
}

async function openStatistics(): Promise<void> {
  await router.push({ path: '/achievements', query: { view: 'statistics' } })
}

function resetFilters(): void {
  search.value = ''
  status.value = 'all'
  server.value = 'all'
  category.value = 'all'
}

function progressPercent(item: PlayerAchievement): number {
  if (item.completed) return 100
  return Math.min(100, Math.round((item.progress / Math.max(1, item.target)) * 100))
}

function achievementDate(item: PlayerAchievement): string {
  const timestamp = item.completedAt || item.updatedAt
  if (!timestamp) return t('engine.views.achievements.031')
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(timestamp * 1000))
}
</script>

<template>
  <section class="achievements-page" aria-labelledby="achievements-page-title">
    <header class="achievements-hero" :class="{ 'achievements-hero--global': statisticsMode }">
      <div class="achievements-hero__content">
        <span class="achievements-hero__eyebrow">
          <i class="fa-solid fa-trophy" aria-hidden="true" />
          {{ t('engine.views.achievements.001') }}
        </span>
        <h1 id="achievements-page-title">{{ t('engine.views.achievements.002') }}</h1>
        <p>{{ t('engine.views.achievements.003') }}</p>

        <form class="achievements-player-search" @submit.prevent="openPlayer">
          <label for="achievement-player-search">{{ t('engine.views.achievements.004') }}</label>
          <span class="achievements-player-search__field">
            <i class="fa-solid fa-user-magnifying-glass" aria-hidden="true" />
            <input
              id="achievement-player-search"
              v-model="playerInput"
              type="search"
              autocomplete="off"
              :placeholder="t('engine.views.achievements.005')"
            >
          </span>
          <button class="button button--primary" type="submit" :disabled="!playerInput.trim()">
            <i class="fa-solid fa-arrow-right" aria-hidden="true" />
            {{ t('engine.views.achievements.006') }}
          </button>
          <button
            v-if="isLogged && (statisticsMode || playerIdentity !== currentLogin && playerIdentity !== currentUuid)"
            class="button button--ghost"
            type="button"
            @click="openMyAchievements"
          >
            {{ t('engine.views.achievements.007') }}
          </button>
          <button
            v-if="!statisticsMode"
            class="button button--ghost"
            type="button"
            @click="openStatistics"
          >
            <i class="fa-solid fa-chart-simple" aria-hidden="true" />
            {{ t('engine.views.achievements.039') }}
          </button>
        </form>
      </div>

      <div v-if="!statisticsMode" class="achievements-hero__progress" aria-hidden="true">
        <svg viewBox="0 0 160 160">
          <circle class="achievements-hero__progress-track" cx="80" cy="80" r="66" />
          <circle
            class="achievements-hero__progress-value"
            cx="80"
            cy="80"
            r="66"
            :style="{ '--achievement-progress': `${completionPercent}` }"
          />
        </svg>
        <span>
          <strong>{{ completionPercent }}%</strong>
          <small>{{ playerName }}</small>
        </span>
      </div>
    </header>

    <div v-if="!statisticsMode" class="achievements-metrics" :aria-label="t('engine.views.achievements.008')">
      <article class="achievement-metric achievement-metric--completed">
        <i class="fa-solid fa-circle-check" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.009') }}</small><strong>{{ summary.completedCount }}</strong></span>
      </article>
      <article class="achievement-metric achievement-metric--remaining">
        <i class="fa-solid fa-lock" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.010') }}</small><strong>{{ remainingCount }}</strong></span>
      </article>
      <article class="achievement-metric achievement-metric--points">
        <i class="fa-solid fa-star" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.011') }}</small><strong>{{ summary.points }}</strong></span>
      </article>
      <article class="achievement-metric achievement-metric--challenge">
        <i class="fa-solid fa-crown" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.012') }}</small><strong>{{ completedChallengeCount }} / {{ challengeCount }}</strong></span>
      </article>
    </div>

    <section
      v-if="!statisticsMode"
      class="achievements-overall-progress"
      :aria-label="t('engine.views.achievements.008')"
    >
      <div class="achievements-overall-progress__copy">
        <span>
          <small>{{ t('engine.views.achievements.009') }}</small>
          <strong>{{ summary.completedCount }} / {{ summary.trackedCount }}</strong>
        </span>
        <b>{{ completionPercent }}%</b>
      </div>
      <span class="achievements-overall-progress__track" aria-hidden="true">
        <i :style="{ width: `${completionPercent}%` }" />
      </span>
    </section>

    <div v-if="!statisticsMode" class="achievements-workspace">
      <main class="achievements-catalog">
        <div class="achievements-toolbar">
          <div class="achievements-toolbar__heading">
            <span>
              <small>{{ playerName }}</small>
              <strong>{{ t('engine.views.achievements.013') }}</strong>
            </span>
            <em>{{ t('engine.views.achievements.014', [filteredItems.length, items.length]) }}</em>
          </div>

          <div class="achievements-toolbar__controls">
            <label class="achievements-search">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
              <input v-model="search" type="search" :placeholder="t('engine.views.achievements.015')">
            </label>
            <select v-model="server" :aria-label="t('engine.views.achievements.016')">
              <option value="all">{{ t('engine.views.achievements.016') }}</option>
              <option v-for="value in servers" :key="value" :value="value">{{ value }}</option>
            </select>
            <select v-model="category" :aria-label="t('engine.views.achievements.017')">
              <option value="all">{{ t('engine.views.achievements.017') }}</option>
              <option v-for="value in categories" :key="value" :value="value">{{ value }}</option>
            </select>
          </div>

          <div class="achievements-status-tabs" role="group" :aria-label="t('engine.views.achievements.019')">
            <button type="button" :class="{ 'is-active': status === 'all' }" @click="status = 'all'">{{ t('engine.views.achievements.020') }}</button>
            <button type="button" :class="{ 'is-active': status === 'completed' }" @click="status = 'completed'">{{ t('engine.views.achievements.021') }}</button>
            <button type="button" :class="{ 'is-active': status === 'locked' }" @click="status = 'locked'">{{ t('engine.views.achievements.022') }}</button>
            <button type="button" :class="{ 'is-active': status === 'challenge' }" @click="status = 'challenge'">{{ t('engine.views.achievements.023') }}</button>
          </div>
        </div>

        <div v-if="loading" class="achievements-state" aria-live="polite">
          <i class="fa-solid fa-spinner achievements-spin" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.024') }}</strong>
          <span>{{ t('engine.views.achievements.025') }}</span>
        </div>

        <div v-else-if="error" class="achievements-state achievements-state--error" role="alert">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.018') }}</strong>
          <span>{{ error }}</span>
          <button class="button button--ghost" type="button" @click="refresh()">{{ t('engine.views.achievements.026') }}</button>
        </div>

        <div v-else-if="items.length === 0" class="achievements-state">
          <i class="fa-solid fa-medal" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.027') }}</strong>
          <span>{{ t('engine.views.achievements.028') }}</span>
        </div>

        <div v-else-if="filteredItems.length === 0" class="achievements-state">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.029') }}</strong>
          <span>{{ t('engine.views.achievements.030') }}</span>
          <button class="button button--ghost" type="button" @click="resetFilters">{{ t('engine.views.achievements.032') }}</button>
        </div>

        <div v-else class="achievements-grid">
          <article
            v-for="item in filteredItems"
            :key="`${item.serverId}:${item.achievementKey}`"
            class="achievement-tile"
            :class="{
              'is-completed': item.completed,
              'is-challenge': item.frameType === 'challenge',
              'is-locked': !item.completed,
            }"
          >
            <div class="achievement-tile__top">
              <span class="achievement-tile__icon-wrap">
                <img :src="item.iconDataUrl" alt="" loading="lazy" decoding="async">
                <i :class="item.completed ? 'fa-solid fa-check' : 'fa-solid fa-lock'" aria-hidden="true" />
              </span>
              <div class="achievement-tile__badges">
                <span class="achievement-tile__state">
                  <i :class="item.completed ? 'fa-solid fa-circle-check' : 'fa-solid fa-lock'" aria-hidden="true" />
                  {{ item.completed ? t('engine.views.achievements.021') : t('engine.views.achievements.022') }}
                </span>
                <span class="achievement-tile__points">+{{ item.points }}</span>
              </div>
            </div>
            <div class="achievement-tile__body">
              <small>{{ item.category }} · {{ item.serverId }}</small>
              <h2>{{ item.title }}</h2>
              <p>{{ item.description || t('engine.views.achievements.033') }}</p>
            </div>
            <div class="achievement-tile__progress">
              <span><i :style="{ width: `${progressPercent(item)}%` }" /></span>
              <small>{{ item.progress }} / {{ item.target }}</small>
            </div>
            <footer>
              <span>
                <i :class="item.frameType === 'challenge' ? 'fa-solid fa-crown' : 'fa-solid fa-medal'" aria-hidden="true" />
                {{ item.frameType === 'challenge' ? t('engine.views.achievements.023') : t('engine.views.achievements.035') }}
              </span>
              <time>{{ achievementDate(item) }}</time>
            </footer>
          </article>
        </div>
      </main>

      <aside class="achievements-sidebar">
        <section class="achievements-sidebar__panel">
          <header>
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true" />
            <span><small>{{ t('engine.views.achievements.036') }}</small><strong>{{ t('engine.views.achievements.037') }}</strong></span>
          </header>
          <p v-if="recentAchievements.length === 0" class="achievements-sidebar__empty">{{ t('engine.views.achievements.038') }}</p>
          <ol v-else class="achievements-recent">
            <li v-for="item in recentAchievements" :key="`recent:${item.serverId}:${item.achievementKey}`">
              <img :src="item.iconDataUrl" alt="" loading="lazy">
              <span><strong>{{ item.title }}</strong><small>{{ achievementDate(item) }}</small></span>
              <b>+{{ item.points }}</b>
            </li>
          </ol>
        </section>

      </aside>
    </div>

    <Suspense v-else>
      <AchievementStatisticsTree />
      <template #fallback>
        <div class="achievements-state" aria-live="polite">
          <i class="fa-solid fa-spinner achievements-spin" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.050') }}</strong>
          <span>{{ t('engine.views.achievements.051') }}</span>
        </div>
      </template>
    </Suspense>
  </section>
</template>

<style scoped>
.achievements-page {
  --achievement-accent: #347757;
  --achievement-accent-bright: #56a77b;
  --achievement-success: #3f9468;
  --achievement-gold: #bd8d39;
  --achievement-violet: #8965a4;
  position: relative;
  display: grid;
  gap: 18px;
  min-width: 0;
  padding: 8px;
}

.achievements-hero {
  position: relative;
  min-height: 330px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 248px;
  align-items: center;
  gap: 42px;
  overflow: hidden;
  padding: 46px;
  border: 1px solid color-mix(in srgb, var(--achievement-accent-bright) 28%, transparent);
  border-radius: 28px;
  background:
    radial-gradient(circle at 82% 22%, color-mix(in srgb, var(--achievement-gold) 22%, transparent), transparent 27%),
    radial-gradient(circle at 70% 100%, color-mix(in srgb, var(--achievement-accent-bright) 20%, transparent), transparent 35%),
    linear-gradient(135deg, #18251f 0%, #101713 56%, #0b100d 100%);
  box-shadow: 0 24px 65px color-mix(in srgb, #07100b 42%, transparent);
  color: #fff;
  isolation: isolate;
}

.achievements-hero::before {
  position: absolute;
  inset: 0;
  z-index: -1;
  content: '';
  background:
    linear-gradient(color-mix(in srgb, #fff 6%, transparent) 1px, transparent 1px),
    linear-gradient(90deg, color-mix(in srgb, #fff 6%, transparent) 1px, transparent 1px);
  background-size: 42px 42px;
  opacity: .48;
  mask-image: linear-gradient(90deg, #000 5%, transparent 94%);
}

.achievements-hero::after {
  position: absolute;
  right: -116px;
  bottom: -194px;
  width: 478px;
  height: 478px;
  z-index: -1;
  border: 1px solid color-mix(in srgb, var(--achievement-gold) 32%, transparent);
  border-radius: 50%;
  content: '';
  box-shadow:
    0 0 0 46px color-mix(in srgb, var(--achievement-gold) 4%, transparent),
    0 0 0 92px color-mix(in srgb, var(--achievement-gold) 2%, transparent);
}

.achievements-hero__content { min-width: 0; }
.achievements-hero--global { grid-template-columns: minmax(0, 1fr); min-height: 295px; }
.achievements-hero--global .achievements-hero__content { max-width: 980px; }
.achievements-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  padding: 7px 10px;
  border: 1px solid color-mix(in srgb, var(--achievement-gold) 34%, transparent);
  border-radius: 999px;
  color: #e1bd78;
  background: color-mix(in srgb, #000 18%, transparent);
  font-size: .66rem;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
}
.achievements-hero h1 {
  max-width: 760px;
  margin: 18px 0 0;
  font-family: var(--font-display);
  font-size: clamp(2.55rem, 5vw, 4.7rem);
  line-height: .94;
  letter-spacing: -.04em;
  text-wrap: balance;
}
.achievements-hero__content > p {
  max-width: 720px;
  margin: 19px 0 0;
  color: color-mix(in srgb, #fff 70%, transparent);
  line-height: 1.72;
}

.achievements-player-search {
  max-width: 790px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: end;
  gap: 10px;
  margin-top: 27px;
}
.achievements-player-search > label {
  grid-column: 1 / -1;
  color: color-mix(in srgb, #fff 66%, transparent);
  font-size: .66rem;
  font-weight: 850;
  letter-spacing: .055em;
  text-transform: uppercase;
}
.achievements-player-search__field {
  min-width: 0;
  height: 46px;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 10px;
  padding: 0 15px;
  border: 1px solid color-mix(in srgb, #fff 18%, transparent);
  border-radius: 14px;
  background: color-mix(in srgb, #020604 36%, transparent);
  box-shadow: inset 0 1px color-mix(in srgb, #fff 5%, transparent);
  backdrop-filter: blur(12px);
  transition: border-color .18s ease, background .18s ease;
}
.achievements-player-search__field:focus-within {
  border-color: color-mix(in srgb, var(--achievement-accent-bright) 72%, #fff);
  background: color-mix(in srgb, #020604 48%, transparent);
}
.achievements-player-search__field i { color: #e1bd78; }
.achievements-player-search__field input {
  min-width: 0;
  border: 0;
  outline: 0;
  color: #fff;
  background: transparent;
}
.achievements-player-search__field input::placeholder { color: color-mix(in srgb, #fff 42%, transparent); }

.achievements-hero__progress {
  position: relative;
  width: 224px;
  height: 224px;
  display: grid;
  place-items: center;
  justify-self: center;
  filter: drop-shadow(0 22px 38px color-mix(in srgb, #000 36%, transparent));
}
.achievements-hero__progress svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.achievements-hero__progress circle { fill: none; stroke-width: 8; }
.achievements-hero__progress-track { stroke: color-mix(in srgb, #fff 11%, transparent); }
.achievements-hero__progress-value {
  stroke: #dfb868;
  stroke-linecap: round;
  stroke-dasharray: 414.69;
  stroke-dashoffset: calc(414.69 - (414.69 * var(--achievement-progress)) / 100);
  transition: stroke-dashoffset .55s ease;
  filter: drop-shadow(0 0 9px color-mix(in srgb, #dfb868 44%, transparent));
}
.achievements-hero__progress > span {
  position: absolute;
  inset: 37px;
  display: grid;
  place-items: center;
  align-content: center;
  border: 1px solid color-mix(in srgb, #fff 13%, transparent);
  border-radius: 50%;
  background: color-mix(in srgb, #08100c 82%, transparent);
  text-align: center;
  backdrop-filter: blur(14px);
}
.achievements-hero__progress strong {
  color: #fff;
  font-family: var(--font-display);
  font-size: 2.75rem;
  line-height: 1;
}
.achievements-hero__progress small {
  max-width: 108px;
  overflow: hidden;
  margin-top: 7px;
  color: #dfb868;
  font-size: .67rem;
  font-weight: 850;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.achievements-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}
.achievement-metric {
  --metric-color: var(--achievement-accent);
  position: relative;
  min-width: 0;
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr);
  align-items: center;
  gap: 13px;
  overflow: hidden;
  padding: 18px;
  border: 1px solid color-mix(in srgb, var(--metric-color) 14%, var(--color-border));
  border-radius: 18px;
  background:
    radial-gradient(circle at 100% 0, color-mix(in srgb, var(--metric-color) 10%, transparent), transparent 42%),
    var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}
.achievement-metric::after {
  position: absolute;
  right: -18px;
  bottom: -24px;
  width: 76px;
  height: 76px;
  border: 1px solid color-mix(in srgb, var(--metric-color) 16%, transparent);
  border-radius: 50%;
  content: '';
}
.achievement-metric > i {
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--metric-color) 14%, transparent);
  border-radius: 15px;
  color: var(--metric-color);
  background: color-mix(in srgb, var(--metric-color) 10%, var(--color-surface-soft));
}
.achievement-metric span { min-width: 0; display: grid; gap: 3px; }
.achievement-metric small {
  color: var(--color-text-muted);
  font-size: .62rem;
  font-weight: 850;
  letter-spacing: .05em;
  text-transform: uppercase;
}
.achievement-metric strong {
  overflow: hidden;
  color: var(--color-text);
  font-family: var(--font-display);
  font-size: 1.5rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.achievement-metric--completed { --metric-color: var(--achievement-success); }
.achievement-metric--remaining { --metric-color: #6f7772; }
.achievement-metric--points { --metric-color: var(--achievement-gold); }
.achievement-metric--challenge { --metric-color: var(--achievement-violet); }

.achievements-overall-progress {
  display: grid;
  gap: 10px;
  padding: 16px 18px;
  border: 1px solid color-mix(in srgb, var(--achievement-accent) 18%, var(--color-border));
  border-radius: 17px;
  background: var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}
.achievements-overall-progress__copy {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 18px;
}
.achievements-overall-progress__copy span { display: grid; gap: 2px; }
.achievements-overall-progress__copy small {
  color: var(--color-text-muted);
  font-size: .61rem;
  font-weight: 850;
  letter-spacing: .05em;
  text-transform: uppercase;
}
.achievements-overall-progress__copy strong { color: var(--color-text); font-size: .84rem; }
.achievements-overall-progress__copy b {
  color: var(--achievement-accent);
  font-family: var(--font-display);
  font-size: 1rem;
}
.achievements-overall-progress__track {
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-border) 72%, transparent);
}
.achievements-overall-progress__track i {
  height: 100%;
  display: block;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--achievement-accent), var(--achievement-accent-bright), var(--achievement-gold));
  box-shadow: 0 0 12px color-mix(in srgb, var(--achievement-accent-bright) 26%, transparent);
  transition: width .45s ease;
}

.achievements-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 294px;
  align-items: start;
  gap: 18px;
}
.achievements-catalog { min-width: 0; display: grid; gap: 15px; }
.achievements-toolbar {
  display: grid;
  gap: 15px;
  padding: 20px;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background: var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}
.achievements-toolbar__heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 20px;
}
.achievements-toolbar__heading span { min-width: 0; display: grid; gap: 2px; }
.achievements-toolbar__heading small {
  overflow: hidden;
  color: var(--achievement-accent);
  font-size: .64rem;
  font-weight: 900;
  letter-spacing: .05em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}
.achievements-toolbar__heading strong {
  color: var(--color-text);
  font-family: var(--font-display);
  font-size: 1.3rem;
}
.achievements-toolbar__heading em {
  color: var(--color-text-muted);
  font-size: .71rem;
  font-style: normal;
  font-weight: 750;
}
.achievements-toolbar__controls {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) minmax(150px, .38fr) minmax(150px, .38fr);
  gap: 9px;
}
.achievements-search {
  min-width: 0;
  height: 43px;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 9px;
  padding: 0 13px;
  border: 1px solid var(--color-border);
  border-radius: 13px;
  background: var(--color-surface-soft);
  transition: border-color .18s ease, box-shadow .18s ease;
}
.achievements-search:focus-within {
  border-color: color-mix(in srgb, var(--achievement-accent) 52%, var(--color-border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--achievement-accent) 8%, transparent);
}
.achievements-search i { color: var(--achievement-accent); }
.achievements-search input {
  min-width: 0;
  border: 0;
  outline: 0;
  color: var(--color-text);
  background: transparent;
}
.achievements-toolbar select {
  min-width: 0;
  height: 43px;
  padding: 0 34px 0 12px;
  border: 1px solid var(--color-border);
  border-radius: 13px;
  color: var(--color-text);
  background: var(--color-surface-soft);
}
.achievements-status-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  padding-top: 2px;
}
.achievements-status-tabs button {
  padding: 8px 13px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  color: var(--color-text-muted);
  background: var(--color-surface-soft);
  cursor: pointer;
  font-size: .69rem;
  font-weight: 800;
  transition: transform .18s ease, border-color .18s ease, color .18s ease, background .18s ease;
}
.achievements-status-tabs button:hover,
.achievements-status-tabs button:focus-visible {
  transform: translateY(-1px);
  border-color: color-mix(in srgb, var(--achievement-accent) 42%, var(--color-border));
  color: var(--achievement-accent);
  outline: none;
}
.achievements-status-tabs button.is-active {
  border-color: var(--achievement-accent);
  color: #fff;
  background: linear-gradient(135deg, var(--achievement-accent), color-mix(in srgb, var(--achievement-accent) 76%, #102018));
  box-shadow: 0 7px 18px color-mix(in srgb, var(--achievement-accent) 18%, transparent);
}

.achievements-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 13px;
}
.achievement-tile {
  --tile-color: #707873;
  position: relative;
  min-width: 0;
  min-height: 265px;
  display: grid;
  grid-template-rows: auto 1fr auto auto;
  gap: 14px;
  overflow: hidden;
  padding: 19px;
  border: 1px solid color-mix(in srgb, var(--tile-color) 20%, var(--color-border));
  border-radius: 20px;
  background:
    radial-gradient(circle at 100% 0, color-mix(in srgb, var(--tile-color) 9%, transparent), transparent 38%),
    linear-gradient(145deg, color-mix(in srgb, var(--tile-color) 3%, var(--color-surface-strong)), var(--color-surface-strong));
  box-shadow: var(--shadow-soft);
  transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
}
.achievement-tile::before {
  position: absolute;
  inset: 0 0 auto;
  height: 3px;
  content: '';
  background: linear-gradient(90deg, transparent, var(--tile-color), transparent);
  opacity: .72;
}
.achievement-tile:hover {
  transform: translateY(-3px);
  border-color: color-mix(in srgb, var(--tile-color) 46%, var(--color-border));
  box-shadow: 0 18px 38px color-mix(in srgb, #000 13%, transparent);
}
.achievement-tile.is-completed { --tile-color: var(--achievement-success); }
.achievement-tile.is-challenge { --tile-color: var(--achievement-gold); }
.achievement-tile.is-locked { --tile-color: #717873; }
.achievement-tile.is-locked .achievement-tile__icon-wrap img {
  filter: grayscale(.86) saturate(.5) opacity(.68);
}
.achievement-tile__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.achievement-tile__icon-wrap {
  position: relative;
  width: 68px;
  height: 68px;
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  padding: 8px;
  border: 1px solid color-mix(in srgb, var(--tile-color) 38%, var(--color-border));
  border-radius: 19px;
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--tile-color) 13%, var(--color-surface-soft)), var(--color-surface-soft));
  box-shadow: inset 0 1px color-mix(in srgb, #fff 12%, transparent);
}
.achievement-tile__icon-wrap img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  image-rendering: pixelated;
  transition: filter .2s ease, transform .2s ease;
}
.achievement-tile:hover .achievement-tile__icon-wrap img { transform: scale(1.05); }
.achievement-tile__icon-wrap > i {
  position: absolute;
  right: -5px;
  bottom: -5px;
  width: 26px;
  height: 26px;
  display: grid;
  place-items: center;
  border: 3px solid var(--color-surface-strong);
  border-radius: 50%;
  color: #fff;
  background: var(--tile-color);
  font-size: .58rem;
}
.achievement-tile__badges {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}
.achievement-tile__state,
.achievement-tile__points {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 9px;
  border: 1px solid color-mix(in srgb, var(--tile-color) 18%, transparent);
  border-radius: 999px;
  color: var(--tile-color);
  background: color-mix(in srgb, var(--tile-color) 9%, var(--color-surface-soft));
  font-size: .63rem;
  font-weight: 900;
}
.achievement-tile__points { color: var(--achievement-gold); }
.achievement-tile__body { min-width: 0; display: grid; align-content: start; gap: 6px; }
.achievement-tile__body small {
  overflow: hidden;
  color: var(--tile-color);
  font-size: .6rem;
  font-weight: 900;
  letter-spacing: .05em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}
.achievement-tile__body h2 {
  margin: 0;
  color: var(--color-text);
  font-family: var(--font-display);
  font-size: 1.1rem;
  line-height: 1.18;
  text-wrap: balance;
}
.achievement-tile__body p {
  display: -webkit-box;
  overflow: hidden;
  min-height: 3em;
  margin: 0;
  color: var(--color-text-muted);
  font-size: .74rem;
  line-height: 1.5;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.achievement-tile__progress {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
}
.achievement-tile__progress > span {
  height: 7px;
  overflow: hidden;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-border) 76%, transparent);
}
.achievement-tile__progress i {
  height: 100%;
  display: block;
  border-radius: inherit;
  background: linear-gradient(90deg, color-mix(in srgb, var(--tile-color) 74%, #111), var(--tile-color));
  box-shadow: 0 0 10px color-mix(in srgb, var(--tile-color) 22%, transparent);
}
.achievement-tile__progress small {
  color: var(--color-text-muted);
  font-size: .62rem;
  font-weight: 800;
}
.achievement-tile footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 12px;
  border-top: 1px solid color-mix(in srgb, var(--tile-color) 10%, var(--color-border));
  color: var(--color-text-muted);
  font-size: .64rem;
}
.achievement-tile footer span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--tile-color);
  font-weight: 850;
}

.achievements-sidebar {
  position: sticky;
  top: 18px;
  display: grid;
  gap: 14px;
}
.achievements-sidebar__panel {
  display: grid;
  gap: 14px;
  padding: 18px;
  border: 1px solid color-mix(in srgb, var(--achievement-accent) 12%, var(--color-border));
  border-radius: 20px;
  background:
    radial-gradient(circle at 100% 0, color-mix(in srgb, var(--achievement-accent) 8%, transparent), transparent 42%),
    var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}
.achievements-sidebar__panel header {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
}
.achievements-sidebar__panel header > i {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--achievement-accent) 13%, transparent);
  border-radius: 13px;
  color: var(--achievement-accent);
  background: color-mix(in srgb, var(--achievement-accent) 10%, var(--color-surface-soft));
}
.achievements-sidebar__panel header span { min-width: 0; display: grid; gap: 1px; }
.achievements-sidebar__panel header small {
  color: var(--color-text-muted);
  font-size: .58rem;
  font-weight: 850;
  letter-spacing: .05em;
  text-transform: uppercase;
}
.achievements-sidebar__panel header strong { color: var(--color-text); font-size: .87rem; }
.achievements-recent { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
.achievements-recent li {
  min-width: 0;
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr) auto;
  align-items: center;
  gap: 9px;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: 13px;
  background: color-mix(in srgb, var(--achievement-accent) 2%, var(--color-surface-soft));
  transition: border-color .18s ease, transform .18s ease;
}
.achievements-recent li:hover {
  transform: translateX(2px);
  border-color: color-mix(in srgb, var(--achievement-accent) 32%, var(--color-border));
}
.achievements-recent img {
  width: 40px;
  height: 40px;
  object-fit: contain;
  image-rendering: pixelated;
}
.achievements-recent span { min-width: 0; display: grid; gap: 2px; }
.achievements-recent strong {
  overflow: hidden;
  color: var(--color-text);
  font-size: .71rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.achievements-recent small { color: var(--color-text-muted); font-size: .58rem; }
.achievements-recent b { color: var(--achievement-gold); font-size: .68rem; }
.achievements-sidebar__empty { margin: 0; color: var(--color-text-muted); font-size: .72rem; line-height: 1.55; }

.achievements-state,
.achievements-empty-entry {
  min-height: 270px;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 9px;
  padding: 34px;
  border: 1px dashed var(--color-border-strong);
  border-radius: 20px;
  color: var(--color-text-muted);
  background: var(--color-surface-strong);
  text-align: center;
}
.achievements-state > i,
.achievements-empty-entry > i { color: var(--achievement-accent); font-size: 1.55rem; }
.achievements-state strong,
.achievements-empty-entry strong {
  color: var(--color-text);
  font-family: var(--font-display);
  font-size: 1.2rem;
}
.achievements-state span,
.achievements-empty-entry p { max-width: 560px; margin: 0; line-height: 1.55; }
.achievements-state--error { border-color: color-mix(in srgb, var(--color-danger) 40%, var(--color-border)); }
.achievements-state--error > i { color: var(--color-danger); }
.achievements-spin { animation: achievements-spin .8s linear infinite; }
@keyframes achievements-spin { to { transform: rotate(360deg); } }

@media (max-width: 1120px) {
  .achievements-workspace { grid-template-columns: 1fr; }
  .achievements-sidebar { position: static; }
  .achievements-recent { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 900px) {
  .achievements-hero { grid-template-columns: 1fr; padding: 36px; }
  .achievements-hero__progress { display: none; }
  .achievements-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .achievements-toolbar__controls { grid-template-columns: 1fr 1fr; }
  .achievements-search { grid-column: 1 / -1; }
}

@media (max-width: 680px) {
  .achievements-page { padding: 0; }
  .achievements-hero { min-height: 0; padding: 28px 22px; border-radius: 20px; }
  .achievements-hero h1 { font-size: clamp(2.25rem, 13vw, 3.35rem); }
  .achievements-player-search { grid-template-columns: 1fr; }
  .achievements-player-search > label { grid-column: auto; }
  .achievements-metrics,
  .achievements-grid,
  .achievements-recent { grid-template-columns: 1fr; }
  .achievements-toolbar__heading { align-items: start; flex-direction: column; gap: 6px; }
  .achievements-toolbar__controls { grid-template-columns: 1fr; }
  .achievements-search { grid-column: auto; }
  .achievement-tile { min-height: 0; }
  .achievement-tile footer { align-items: flex-start; flex-direction: column; gap: 5px; }
}

@media (max-width: 440px) {
  .achievements-metrics { grid-template-columns: 1fr; }
  .achievement-tile__top { align-items: center; }
  .achievement-tile__badges { max-width: 150px; }
}

@media (prefers-reduced-motion: reduce) {
  .achievements-spin { animation: none; }
  .achievement-tile,
  .achievement-tile__icon-wrap img,
  .achievements-recent li,
  .achievements-status-tabs button,
  .achievements-hero__progress-value,
  .achievements-overall-progress__track i { transition: none; }
}
</style>
