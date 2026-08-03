<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { bootstrapBoolean } from '@/domain/bootstrap'
import { loadStaticPages, type StaticPageDefinition } from '@/content/contentData'
import StaticContent from '@theme/userOptions/content/StaticContent.vue'

interface BadgeClaimFeedback {
  type: 'success' | 'warning' | 'error'
  message: string
}
interface BadgeClaimResponse {
  type?: 'success' | 'warning'
  message?: string
  alreadyOwned?: boolean
}

const props = defineProps<{ pageId: string }>()
const page = ref<StaticPageDefinition | null>(null)
const loading = ref(true)
const error = ref(false)
const authenticated = bootstrapBoolean(appBootstrap, 'isLogged')
const claimingBadge = ref(false)
const claimedBadgeOwned = ref(false)
const badgeClaimFeedback = ref<BadgeClaimFeedback | null>(null)

async function claimBadge(badgeName: string): Promise<void> {
  badgeClaimFeedback.value = null
  if (!authenticated) {
    badgeClaimFeedback.value = { type: 'error', message: 'Войдите в аккаунт, чтобы получить бейдж.' }
    return
  }
  if (claimingBadge.value || claimedBadgeOwned.value) return

  claimingBadge.value = true
  try {
    const response = await foxesApi.post<BadgeClaimResponse>({
      user_doaction: 'claimBadge',
      badgeName,
    })
    claimedBadgeOwned.value = true
    badgeClaimFeedback.value = {
      type: response.type === 'warning' ? 'warning' : 'success',
      message: response.message || 'Бейдж «Знаток правил» добавлен в ваш профиль.',
    }
  } catch (requestError) {
    console.error('[FoxesCraft] Public badge claim failed', requestError)
    badgeClaimFeedback.value = {
      type: 'error',
      message: requestError instanceof Error && requestError.message.trim()
        ? requestError.message
        : 'Не удалось получить бейдж.',
    }
  } finally {
    claimingBadge.value = false
  }
}

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
watch(() => props.pageId, () => {
  badgeClaimFeedback.value = null
  void load()
})
</script>

<template>
  <StaticContent
    :page-id="pageId"
    :loading="loading"
    :error="error"
    :page="page"
    :authenticated="authenticated"
    :claiming-badge="claimingBadge"
    :claimed-badge-owned="claimedBadgeOwned"
    :badge-claim-feedback="badgeClaimFeedback"
    @claim-badge="claimBadge"
  />
</template>
