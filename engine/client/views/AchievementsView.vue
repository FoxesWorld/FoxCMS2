<script setup lang="ts">
import { computed, defineAsyncComponent, markRaw, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { appBootstrap } from '@/app/context'
import { bootstrapBoolean, bootstrapString } from '@/domain/bootstrap'
import { t } from '@/i18n'
import RuntimeTpl from '@/runtime/RuntimeTpl.vue'
import { loadRuntimePageTemplates, runtimePageTemplate, runtimePageTemplatesState } from '@/runtime/pageTemplates'
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
const pageTemplate = runtimePageTemplate('achievements')
const runtimeTemplateComponents = markRaw({ AchievementStatisticsTree })
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  items,
  summary,
  loading,
  error,
  search,
  playerInput,
  status,
  server,
  category,
  isLogged,
  currentLogin,
  currentUuid,
  requestedIdentity,
  statisticsMode,
  playerIdentity,
  playerName,
  completionPercent,
  remainingCount,
  challengeCount,
  completedChallengeCount,
  servers,
  categories,
  filteredItems,
  recentAchievements,
  refresh,
  openPlayer,
  openMyAchievements,
  openStatistics,
  resetFilters,
  progressPercent,
  achievementDate,
}
void loadRuntimePageTemplates().catch((reason: unknown) => {
  console.error('[FoxesCraft] Achievements.tpl failed to load', reason)
})
</script>

<template>
  <div v-if="runtimePageTemplatesState.error" class="system-message system-message--error" role="alert">
    <strong>{{ t('engine.runtime.pagetemplates.003') }}</strong>
    <p>{{ runtimePageTemplatesState.error }}</p>
  </div>
  <RuntimeTpl
    v-else-if="pageTemplate"
    :template-id="pageTemplate.id"
    :module-url="pageTemplate.moduleUrl"
    :revision="pageTemplate.revision"
    :context="runtimeTemplateContext"
    :components="runtimeTemplateComponents"
  />
  <div v-else class="runtime-panel-skeleton" aria-hidden="true"><span /><span /><span /></div>
</template>
