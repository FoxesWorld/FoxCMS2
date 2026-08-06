<script setup lang="ts">
import { t } from '@/i18n'

import { computed, markRaw, ref, toRefs } from 'vue'
import { useRouter } from 'vue-router'
import type { BadgeDefinition } from '@engine/content/contentData'
import RuntimeTpl from '@engine/runtime/RuntimeTpl.vue'
import { loadRuntimePageTemplates, runtimePageTemplate, runtimePageTemplatesState } from '@engine/runtime/pageTemplates'

interface RewardClaimFeedback {
  type: 'success' | 'warning' | 'error'
  message: string
}
interface ClaimedRewardBadge {
  id: number
  badgeName: string
  title: string
  description: string
  image: string | null
  acquiredAt: number
}
interface ClaimedRewardCurrency {
  currencyCode: string
  currencyName: string
  currencySymbol: string
  amount: number
}
interface ClaimedReward {
  id: number
  rewardName: string
  title: string
  description: string
  badge: ClaimedRewardBadge | null
  currency: ClaimedRewardCurrency | null
}

const props = defineProps<{
  badges: readonly BadgeDefinition[]
  loading: boolean
  error: boolean
  claimCode: string
  authenticated: boolean
  claiming: boolean
  claimFeedback: RewardClaimFeedback | null
  claimedReward: ClaimedReward | null
}>()

const emit = defineEmits<{
  'update:claimCode': [value: string]
  claim: []
}>()

const router = useRouter()
const search = ref('')

const normalizedSearch = computed(() => search.value.trim().toLocaleLowerCase('ru'))
const filteredBadges = computed(() => {
  const query = normalizedSearch.value
  if (!query) return props.badges
  return props.badges.filter((badge) =>
    badge.title.toLocaleLowerCase('ru').includes(query)
    || badge.description.toLocaleLowerCase('ru').includes(query),
  )
})
const configuredCount = computed(() => props.badges.filter((badge) => badge.pageConfigured).length)

function updateClaimCode(event: Event): void {
  emit('update:claimCode', (event.target as HTMLInputElement).value)
}

function openBadge(badge: BadgeDefinition): void {
  if (!badge.pageConfigured) return
  void router.push({ name: 'badge', params: { id: badge.id } })
}

function handleRowKeydown(event: KeyboardEvent, badge: BadgeDefinition): void {
  if (event.key !== 'Enter' && event.key !== ' ') return
  event.preventDefault()
  openBadge(badge)
}
const pageTemplate = runtimePageTemplate('badges')
const runtimeTemplateComponents = markRaw({})
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  ...toRefs(props),
  emit,
  search,
  normalizedSearch,
  filteredBadges,
  configuredCount,
  updateClaimCode,
  openBadge,
  handleRowKeydown,
}
void loadRuntimePageTemplates().catch((reason: unknown) => {
  console.error('[FoxesCraft] Badges.tpl failed to load', reason)
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
