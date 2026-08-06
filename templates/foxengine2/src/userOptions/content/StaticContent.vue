<script setup lang="ts">
import { t } from '@/i18n'

import { markRaw, toRefs } from 'vue'
import type { StaticPageDefinition } from '@engine/content/contentData'
import type { PublicRewardOffer } from '@engine/domain/publicRewardOffers'
import type { RewardOfferFeedback } from '@engine/rewards/usePublicRewardOffer'
import { formatBalanceAmount } from '@engine/domain/userBalance'
import RuntimeTpl from '@engine/runtime/RuntimeTpl.vue'
import {
  loadRuntimePageTemplates,
  runtimePageTemplate,
  runtimePageTemplatesState,
} from '@engine/runtime/pageTemplates'
import StaticPage from './StaticPage.vue'

const props = defineProps<{
  pageId: string
  loading: boolean
  error: boolean
  page: StaticPageDefinition | null
  authenticated: boolean
  rewardOffer: PublicRewardOffer | null
  rewardOfferIcon: string
  rewardOfferLoading: boolean
  rewardOfferClaiming: boolean
  rewardOfferFeedback: RewardOfferFeedback | null
}>()

const emit = defineEmits<{ claimReward: [] }>()
const pageTemplate = runtimePageTemplate('static-content')
const runtimeTemplateComponents = markRaw({ StaticPage })
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  formatBalanceAmount,
  ...toRefs(props),
  emit,
}

void loadRuntimePageTemplates().catch((error: unknown) => {
  console.error('[FoxesCraft] StaticContent.tpl failed to load', error)
})
</script>

<template>
  <div v-if="runtimePageTemplatesState.error" class="system-message system-message--error" role="alert">
    <strong>{{ t('engine.runtime.pagetemplates.003') }}</strong>
    <p>{{ runtimePageTemplatesState.error }}</p>
  </div>
  <RuntimeTpl
    v-else-if="pageTemplate"
    template-id="static-content"
    :module-url="pageTemplate.moduleUrl"
    :revision="pageTemplate.revision"
    :context="runtimeTemplateContext"
    :components="runtimeTemplateComponents"
  />
  <div v-else class="runtime-panel-skeleton" aria-hidden="true"><span /><span /><span /></div>
</template>
