<script setup lang="ts">
import { computed, markRaw, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t } from '@/i18n'
import RuntimeTpl from '@/runtime/RuntimeTpl.vue'
import { loadRuntimePageTemplates, runtimePageTemplate, runtimePageTemplatesState } from '@/runtime/pageTemplates'
import AchievementTreeNode from './AchievementTreeNode.vue'
import { buildAchievementTrees, filterAchievementTree, type AchievementTreeNodeModel } from './achievementStatisticsTree'
import {
  loadAchievementStatistics,
  type AchievementStatistic,
  type AchievementStatisticsSummary,
} from './playerAchievements'

const items = ref<AchievementStatistic[]>([])
const summary = ref<AchievementStatisticsSummary>({
  achievementCount: 0,
  earnedAchievementCount: 0,
  playerCount: 0,
  unlockCount: 0,
})
const loading = ref(true)
const error = ref('')
const search = ref('')
const server = ref('')
const category = ref('all')
const servers = ref<string[]>([])
let controller: AbortController | null = null
let initialized = false

interface AchievementStatisticsCategorySummary {
  id: string
  label: string
  totalCount: number
  completedCount: number
  completionPercent: number
  isCompleted: boolean
  unlockCount: number
  iconDataUrl: string
  iconItem: string
  items: AchievementStatistic[]
}

const UNCATEGORIZED_CATEGORY = '__uncategorized__'
const itemCategoryId = (item: AchievementStatistic): string => item.category.trim() || UNCATEGORIZED_CATEGORY
const itemCategoryLabel = (id: string, categoryItems: AchievementStatistic[]): string => {
  if (id === UNCATEGORIZED_CATEGORY) return t('engine.views.achievements.068')
  return categoryItems.find((item) => item.categoryLabel.trim())?.categoryLabel.trim() || id
}

const categorySummaries = computed<AchievementStatisticsCategorySummary[]>(() => {
  const grouped = new Map<string, AchievementStatisticsCategorySummary>()
  for (const item of items.value) {
    const id = itemCategoryId(item)
    const current = grouped.get(id) ?? {
      id,
      label: '',
      totalCount: 0,
      completedCount: 0,
      completionPercent: 0,
      isCompleted: false,
      unlockCount: 0,
      iconDataUrl: '',
      iconItem: '',
      items: [],
    }
    current.totalCount += 1
    if (item.earnedCount > 0) current.completedCount += 1
    current.unlockCount += item.earnedCount
    current.items.push(item)
    current.label = itemCategoryLabel(id, current.items)
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
  if (!needle) return categorySummaries.value
  return categorySummaries.value.filter((entry) => entry.label.toLocaleLowerCase('ru-RU').includes(needle)
    || entry.items.some((item) => [item.title, item.description, item.achievementKey, item.iconItem]
      .some((value) => value.toLocaleLowerCase('ru-RU').includes(needle))))
})
const trees = computed(() => buildAchievementTrees(items.value))
const filteredTrees = computed(() => {
  const selectedCategory = category.value === UNCATEGORIZED_CATEGORY ? '' : category.value
  return trees.value
    .map((tree) => ({
      ...tree,
      roots: tree.roots
        .map((root) => filterAchievementTree(root, search.value, selectedCategory))
        .filter((root): root is NonNullable<typeof root> => root !== null),
    }))
    .filter((tree) => tree.roots.length > 0)
})
const visibleCount = computed(() => {
  const countNode = (node: AchievementTreeNodeModel): number => node.children.reduce(
    (total, child) => total + countNode(child),
    1,
  )
  return filteredTrees.value.reduce(
    (total, tree) => total + tree.roots.reduce((subtotal, root) => subtotal + countNode(root), 0),
    0,
  )
})

onMounted(() => void initialize())
watch(server, (value, previous) => {
  category.value = 'all'
  if (!initialized || !value || value === previous) return
  void loadServer(value)
})
watch(categorySummaries, (values) => {
  if (category.value !== 'all' && !values.some((entry) => entry.id === category.value)) category.value = 'all'
})
onBeforeUnmount(() => controller?.abort())

function emptySummary(): AchievementStatisticsSummary {
  return {
    achievementCount: 0,
    earnedAchievementCount: 0,
    playerCount: 0,
    unlockCount: 0,
  }
}

async function initialize(): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  initialized = false
  loading.value = true
  error.value = ''
  items.value = []
  summary.value = emptySummary()
  try {
    const discovery = await loadAchievementStatistics(request.signal)
    const availableServers = [...new Set(discovery.items.map((item) => item.serverId).filter(Boolean))].sort()
    servers.value = availableServers
    const selectedServer = availableServers.includes(server.value) ? server.value : availableServers[0] ?? ''
    server.value = selectedServer
    if (!selectedServer) {
      items.value = []
      summary.value = emptySummary()
      return
    }
    const response = await loadAchievementStatistics(request.signal, selectedServer)
    items.value = response.items
    summary.value = response.summary
  } catch (reason) {
    if (reason instanceof DOMException && reason.name === 'AbortError') return
    error.value = reason instanceof Error ? reason.message : t('engine.views.achievements.052')
  } finally {
    if (controller === request) {
      controller = null
      loading.value = false
      initialized = true
    }
  }
}

async function loadServer(serverId: string): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  loading.value = true
  error.value = ''
  items.value = []
  summary.value = emptySummary()
  try {
    const response = await loadAchievementStatistics(request.signal, serverId)
    items.value = response.items
    summary.value = response.summary
  } catch (reason) {
    if (reason instanceof DOMException && reason.name === 'AbortError') return
    error.value = reason instanceof Error ? reason.message : t('engine.views.achievements.052')
  } finally {
    if (controller === request) {
      controller = null
      loading.value = false
    }
  }
}

async function refresh(): Promise<void> {
  await initialize()
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
  category.value = 'all'
}
const pageTemplate = runtimePageTemplate('achievement-statistics')
const runtimeTemplateComponents = markRaw({ AchievementTreeNode })
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  items,
  summary,
  loading,
  error,
  search,
  server,
  category,
  servers,
  categories,
  categorySummaries,
  visibleCategorySummaries,
  categoryIndex,
  activeCategorySummary,
  trees,
  filteredTrees,
  visibleCount,
  refresh,
  openCategory,
  closeCategory,
  resetFilters,
}
void loadRuntimePageTemplates().catch((reason: unknown) => {
  console.error('[FoxesCraft] StatisticsTree.tpl failed to load', reason)
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
