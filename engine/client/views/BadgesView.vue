<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { foxesApi } from '@engine/api'
import { bootstrapBoolean } from '@engine/domain/bootstrap'
import { loadBadges, type BadgeDefinition } from '@engine/content/contentData'
import Badges from '@theme/userOptions/pages/badges/Badges.vue'


interface BadgeClaimFeedback {
  type: 'success' | 'warning' | 'error'
  message: string
}
interface BadgeClaimResponse {
  type?: 'success' | 'warning'
  message?: string
  alreadyOwned?: boolean
  badge?: {
    id: number
    badgeName: string
    title: string
    description: string
    image: string | null
    acquiredAt: number
  }
}

const badges = ref<readonly BadgeDefinition[]>([])
const authenticated = bootstrapBoolean(appBootstrap, 'isLogged')
const claimCode = ref('')
const claiming = ref(false)
const claimFeedback = ref<BadgeClaimFeedback | null>(null)
const claimedBadge = ref<NonNullable<BadgeClaimResponse['badge']> | null>(null)
const loading = ref(true)
const error = ref(false)


async function claimBadge(): Promise<void> {
  const code = claimCode.value.trim()
  claimFeedback.value = null
  claimedBadge.value = null
  if (!authenticated) {
    claimFeedback.value = { type: 'error', message: 'Войдите в аккаунт, чтобы получить бейдж.' }
    return
  }
  if (!/^fcb_[A-Za-z0-9_-]{43}$/.test(code)) {
    claimFeedback.value = { type: 'error', message: 'Введите полный код получения бейджа.' }
    return
  }

  claiming.value = true
  try {
    const response = await foxesApi.post<BadgeClaimResponse>({
      user_doaction: 'claimBadge',
      claimCode: code,
    })
    claimFeedback.value = {
      type: response.type === 'warning' ? 'warning' : 'success',
      message: response.message || 'Бейдж добавлен в профиль.',
    }
    claimedBadge.value = response.badge ?? null
    claimCode.value = ''
  } catch (requestError) {
    console.error('[FoxesCraft] Badge claim failed', requestError)
    claimFeedback.value = {
      type: 'error',
      message: requestError instanceof Error && requestError.message.trim()
        ? requestError.message
        : 'Не удалось применить код получения бейджа.',
    }
  } finally {
    claiming.value = false
  }
}

async function load(): Promise<void> {
  loading.value = true
  error.value = false
  try {
    badges.value = await loadBadges()
    const siteTitle = appBootstrap.site.title || 'FoxesCraft'
    document.title = `Бейджи — ${siteTitle}`
  } catch (requestError) {
    console.error('[FoxesCraft] Badge catalog failed', requestError)
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
</script>

<template>
  <Badges
    v-model:claim-code="claimCode"
    :badges="badges"
    :loading="loading"
    :error="error"
    :authenticated="authenticated"
    :claiming="claiming"
    :claim-feedback="claimFeedback"
    :claimed-badge="claimedBadge"
    @claim="claimBadge"
  />
</template>
