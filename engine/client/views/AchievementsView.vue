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
  loadAchievementStatistics,
  loadPlayerAchievementsByIdentity,
  type PlayerAchievement,
  type PlayerAchievementSummary,
} from '@/achievements/playerAchievements'

type StatusFilter = 'all' | 'completed' | 'locked' | 'challenge'

interface AchievementCategorySummary {
  id: string
  label: string
  totalCount: number
  completedCount: number
  completionPercent: number
  isCompleted: boolean
  iconDataUrl: string
  iconItem: string
  items: PlayerAchievement[]
}

const UNCATEGORIZED_CATEGORY = '__uncategorized__'

const AchievementStatisticsTree = defineAsyncComponent(
  () => import('@/achievements/AchievementStatisticsTree.vue'),
)

const route = useRoute()
const router = useRouter()
const items = ref<PlayerAchievement[]>([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const playerInput = ref('')
const status = ref<StatusFilter>('all')
const server = ref('')
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
  return requestedView === 'statistics'
})
const playerIdentity = computed(() => statisticsMode.value
  ? ''
  : requestedIdentity.value || currentUuid.value || currentLogin.value)
const hasPlayerContext = computed(() => Boolean(playerIdentity.value))
const playerName = computed(() =>
  items.value.find((item) => item.playerName.trim())?.playerName
  || (playerIdentity.value && playerIdentity.value !== currentUuid.value ? playerIdentity.value : currentLogin.value)
  || t('engine.views.achievements.034'))
const servers = computed(() => [...new Set(items.value.map((item) => item.serverId).filter(Boolean))].sort())
const selectedItems = computed(() => server.value
  ? items.value.filter((item) => item.serverId === server.value)
  : [])
const summary = computed<PlayerAchievementSummary>(() => {
  let completedCount = 0
  let points = 0
  for (const item of selectedItems.value) {
    if (!item.completed) continue
    completedCount += 1
    points += item.points
  }
  return {
    trackedCount: selectedItems.value.length,
    completedCount,
    points,
  }
})
const completionPercent = computed(() => {
  if (summary.value.trackedCount <= 0) return 0
  return Math.min(100, Math.round((summary.value.completedCount / summary.value.trackedCount) * 100))
})
const remainingCount = computed(() => Math.max(0, summary.value.trackedCount - summary.value.completedCount))
const challengeCount = computed(() => selectedItems.value.filter((item) => item.frameType === 'challenge').length)
const completedChallengeCount = computed(() =>
  selectedItems.value.filter((item) => item.frameType === 'challenge' && item.completed).length)
function categoryId(item: PlayerAchievement): string {
  return item.category.trim() || UNCATEGORIZED_CATEGORY
}

function categoryLabel(id: string, categoryItems: PlayerAchievement[]): string {
  if (id === UNCATEGORIZED_CATEGORY) return t('engine.views.achievements.068')
  return categoryItems.find((item) => item.categoryLabel.trim())?.categoryLabel.trim() || id
}

function matchesStatus(item: PlayerAchievement): boolean {
  if (status.value === 'completed') return item.completed
  if (status.value === 'locked') return !item.completed
  if (status.value === 'challenge') return item.frameType === 'challenge'
  return true
}

function matchesSearch(item: PlayerAchievement, needle: string): boolean {
  if (!needle) return true
  return [item.title, item.description, item.achievementKey, item.iconItem, item.categoryLabel, item.category, item.serverId]
    .some((value) => value.toLocaleLowerCase('ru-RU').includes(needle))
}

const categorySummaries = computed<AchievementCategorySummary[]>(() => {
  const grouped = new Map<string, AchievementCategorySummary>()
  for (const item of selectedItems.value) {
    const id = categoryId(item)
    const current = grouped.get(id) ?? {
      id,
      label: '',
      totalCount: 0,
      completedCount: 0,
      completionPercent: 0,
      isCompleted: false,
      iconDataUrl: '',
      iconItem: '',
      items: [],
    }
    current.totalCount += 1
    if (item.completed) current.completedCount += 1
    current.items.push(item)
    current.label = categoryLabel(id, current.items)
    grouped.set(id, current)
  }
  return [...grouped.values()]
    .map((entry) => {
      const categoryKeys = new Set(entry.items.map((item) => item.achievementKey))
      const iconSource = entry.items.find((item) => !item.parentKey || !categoryKeys.has(item.parentKey)) ?? entry.items[0]
      entry.iconDataUrl = iconSource?.iconDataUrl ?? ''
      entry.iconItem = iconSource?.iconItem ?? ''
      entry.completionPercent = entry.totalCount > 0
        ? Math.min(100, Math.round((entry.completedCount / entry.totalCount) * 100))
        : 0
      entry.isCompleted = entry.totalCount > 0 && entry.completedCount === entry.totalCount
      return entry
    })
    .sort((left, right) => left.label.localeCompare(right.label, 'ru-RU'))
})
const categories = computed(() => categorySummaries.value.map((entry) => entry.id))
const categoryIndex = computed(() => categorySummaries.value.length > 1 && category.value === 'all')
const activeCategorySummary = computed(() => category.value === 'all'
  ? null
  : categorySummaries.value.find((entry) => entry.id === category.value) ?? null)
const visibleCategorySummaries = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase('ru-RU')
  return categorySummaries.value.filter((entry) => {
    const categoryMatches = !needle || entry.label.toLocaleLowerCase('ru-RU').includes(needle)
    return entry.items.some((item) => matchesStatus(item) && (categoryMatches || matchesSearch(item, needle)))
  })
})
const filteredItems = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase('ru-RU')
  return selectedItems.value.filter((item) => {
    if (!matchesStatus(item)) return false
    if (category.value !== 'all' && categoryId(item) !== category.value) return false
    return matchesSearch(item, needle)
  })
})
const recentAchievements = computed(() => selectedItems.value
  .filter((item) => item.completed)
  .sort((left, right) => (right.completedAt ?? right.updatedAt) - (left.completedAt ?? left.updatedAt))
  .slice(0, 5))

watch(playerIdentity, (identity) => void refresh(identity), { immediate: true })
watch(servers, (values) => {
  if (!values.includes(server.value)) server.value = values[0] ?? ''
}, { immediate: true })
watch(server, () => {
  category.value = 'all'
})
watch(categorySummaries, (values) => {
  if (category.value !== 'all' && !values.some((entry) => entry.id === category.value)) category.value = 'all'
})
onBeforeUnmount(() => controller?.abort())

async function refresh(identity = playerIdentity.value): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  const preferredServer = server.value
  items.value = []
  error.value = ''
  loading.value = true
  try {
    if (identity) {
      const response = await loadPlayerAchievementsByIdentity(achievementIdentity(identity), request.signal)
      items.value = response.items
    } else {
      const response = await loadAchievementStatistics(request.signal)
      items.value = response.items.map((item) => ({
        serverId: item.serverId,
        playerName: '',
        achievementKey: item.achievementKey,
        achievementType: 'achievement',
        parentKey: item.parentKey,
        title: item.title,
        description: item.description,
        frameType: item.frameType,
        category: item.category,
        categoryLabel: item.categoryLabel,
        iconDataUrl: item.iconDataUrl,
        iconItem: item.iconItem,
        points: item.points,
        progress: 0,
        target: 1,
        completed: false,
        completedAt: null,
        updatedAt: 0,
      }))
    }
    const availableServers = [...new Set(items.value.map((item) => item.serverId).filter(Boolean))].sort()
    server.value = availableServers.includes(preferredServer) ? preferredServer : availableServers[0] ?? ''
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

function openCategory(categoryId: string): void {
  if (!categorySummaries.value.some((entry) => entry.id === categoryId)) return
  category.value = categoryId
}

function closeCategory(): void {
  category.value = 'all'
}

function resetFilters(): void {
  search.value = ''
  status.value = 'all'
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
  selectedItems,
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
  hasPlayerContext,
  playerName,
  completionPercent,
  remainingCount,
  challengeCount,
  completedChallengeCount,
  servers,
  categories,
  categorySummaries,
  visibleCategorySummaries,
  categoryIndex,
  activeCategorySummary,
  filteredItems,
  recentAchievements,
  refresh,
  openPlayer,
  openMyAchievements,
  openStatistics,
  openCategory,
  closeCategory,
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
