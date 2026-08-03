<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { appBootstrap } from '@/app/context'
import { usePublicRewardOffer } from '@/rewards/usePublicRewardOffer'
import { bootstrapBoolean } from '@/domain/bootstrap'
import { loadStaticPages, type StaticPageDefinition } from '@/content/contentData'
import StaticContent from '@theme/userOptions/content/StaticContent.vue'

const props = defineProps<{ pageId: string }>()
const page = ref<StaticPageDefinition | null>(null)
const loading = ref(true)
const error = ref(false)
const authenticated = bootstrapBoolean(appBootstrap, 'isLogged')
const rulesOfferEnabled = computed(() => authenticated && props.pageId === 'rules')
const rulesOffer = usePublicRewardOffer('rules', rulesOfferEnabled)

async function load(): Promise<void> {
  loading.value = true
  error.value = false
  try {
    const entry = (await loadStaticPages())[props.pageId] ?? null
    page.value = entry
    error.value = !page.value
    if (page.value?.title) {
      const siteTitle = appBootstrap.site.title || 'FoxesCraft'
      document.title = `${page.value.title} — ${siteTitle}`
    }
  } catch (requestError) {
    console.error('[FoxesCraft] Project page content failed', requestError)
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
watch(() => props.pageId, () => void load())
</script>

<template>
  <StaticContent
    :page-id="pageId"
    :loading="loading"
    :error="error"
    :page="page"
    :authenticated="authenticated"
    :reward-offer="rulesOffer.offer.value"
    :reward-offer-icon="rulesOffer.icon.value"
    :reward-offer-loading="rulesOffer.loading.value"
    :reward-offer-claiming="rulesOffer.claiming.value"
    :reward-offer-feedback="rulesOffer.feedback.value"
    @claim-reward="rulesOffer.claim"
  />
</template>
