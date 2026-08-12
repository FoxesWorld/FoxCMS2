<script setup lang="ts">
import { computed, markRaw, onBeforeUnmount, ref, toRefs, watch } from 'vue'
import { t } from '@/i18n'
import RuntimeTpl from '@engine/runtime/RuntimeTpl.vue'
import { loadRuntimePageTemplates, runtimePageTemplate, runtimePageTemplatesState } from '@engine/runtime/pageTemplates'
import {
  loadPlayerAchievements,
  type PlayerAchievement,
  type PlayerAchievementSummary,
} from '@engine/achievements/playerAchievements'

const props = defineProps<{ playerUuid: string }>()

const loading = ref(false)
const error = ref('')
const items = ref<PlayerAchievement[]>([])
const server = ref('')
let controller: AbortController | null = null

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

watch(
  () => props.playerUuid,
  (uuid) => void refresh(uuid),
  { immediate: true },
)
watch(servers, (values) => {
  if (!values.includes(server.value)) server.value = values[0] ?? ''
}, { immediate: true })

onBeforeUnmount(() => controller?.abort())

async function refresh(uuid = props.playerUuid): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  const normalizedUuid = uuid.trim()
  const preferredServer = server.value
  items.value = []
  error.value = ''
  if (!normalizedUuid) return
  loading.value = true
  try {
    const result = await loadPlayerAchievements(normalizedUuid, request.signal)
    items.value = result.items
    const availableServers = [...new Set(result.items.map((item) => item.serverId).filter(Boolean))].sort()
    server.value = availableServers.includes(preferredServer) ? preferredServer : availableServers[0] ?? ''
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
const pageTemplate = runtimePageTemplate('achievement-profile-panel')
const runtimeTemplateComponents = markRaw({})
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  ...toRefs(props),
  loading,
  error,
  items,
  server,
  servers,
  selectedItems,
  summary,
  completionPercent,
  refresh,
  timestamp,
  progressPercent,
}
void loadRuntimePageTemplates().catch((reason: unknown) => {
  console.error('[FoxesCraft] ProfilePanel.tpl failed to load', reason)
})
</script>

<template>
  <div v-if="runtimePageTemplatesState.error" class="system-message system-message--error" role="alert">
    <strong>{{ t('engine.runtime.pagetemplates.003') }}</strong>
    <p>{{ t('engine.runtime.pagetemplates.004') }}</p>
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
