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
const pageTemplate = runtimePageTemplate('achievement-profile-panel')
const runtimeTemplateComponents = markRaw({})
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  ...toRefs(props),
  loading,
  error,
  items,
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
