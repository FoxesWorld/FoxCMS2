<script setup lang="ts">
import { computed, getCurrentInstance, markRaw, toRefs, type Component } from 'vue'
import { t } from '@/i18n'
import { RouterLink } from 'vue-router'
import RuntimeTpl from '@/runtime/RuntimeTpl.vue'
import { loadRuntimePageTemplates, runtimePageTemplate, runtimePageTemplatesState } from '@/runtime/pageTemplates'
import type { AchievementTreeNodeModel } from './achievementStatisticsTree'

const AchievementTreeNode = getCurrentInstance()?.type as Component

const props = withDefaults(defineProps<{
  node: AchievementTreeNodeModel
  depth?: number
}>(), {
  depth: 0,
})

const nodeTone = computed(() => {
  if (props.node.frameType === 'challenge') return 'challenge'
  if (props.node.earnedCount > 0) return 'earned'
  return 'unearned'
})

function playerLabel(player: AchievementTreeNodeModel['players'][number]): string {
  return player.playerName || player.login || t('engine.views.achievements.034')
}

function playerInitial(player: AchievementTreeNodeModel['players'][number]): string {
  return playerLabel(player).trim().slice(0, 1).toLocaleUpperCase('ru-RU') || '?'
}

function playerRoute(player: AchievementTreeNodeModel['players'][number]): Record<string, unknown> {
  return { name: 'achievements', params: { value: player.login || player.uuid }, query: {} }
}

function completedDate(timestamp: number | null): string {
  if (!timestamp) return t('engine.views.achievements.031')
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(timestamp * 1000))
}
const pageTemplate = runtimePageTemplate('achievement-tree-node')
const runtimeTemplateComponents = markRaw({ RouterLink, AchievementTreeNode })
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  ...toRefs(props),
  nodeTone,
  playerLabel,
  playerInitial,
  playerRoute,
  completedDate,
}
void loadRuntimePageTemplates().catch((reason: unknown) => {
  console.error('[FoxesCraft] TreeNode.tpl failed to load', reason)
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
