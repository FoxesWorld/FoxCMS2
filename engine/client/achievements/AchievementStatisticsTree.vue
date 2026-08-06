<script setup lang="ts">
import { computed, markRaw, onBeforeUnmount, onMounted, ref } from 'vue'
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
const server = ref('all')
const category = ref('all')
let controller: AbortController | null = null

const servers = computed(() => [...new Set(items.value.map((item) => item.serverId).filter(Boolean))].sort())
const categories = computed(() => [...new Set(items.value.map((item) => item.category).filter(Boolean))].sort())
const trees = computed(() => buildAchievementTrees(items.value))
const filteredTrees = computed(() => trees.value
  .filter((tree) => server.value === 'all' || tree.serverId === server.value)
  .map((tree) => ({
    ...tree,
    roots: tree.roots
      .map((root) => filterAchievementTree(root, search.value, category.value))
      .filter((root): root is NonNullable<typeof root> => root !== null),
  }))
  .filter((tree) => tree.roots.length > 0))
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

onMounted(() => void refresh())
onBeforeUnmount(() => controller?.abort())

async function refresh(): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  loading.value = true
  error.value = ''
  try {
    const response = await loadAchievementStatistics(request.signal)
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

function resetFilters(): void {
  search.value = ''
  server.value = 'all'
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
  trees,
  filteredTrees,
  visibleCount,
  refresh,
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
