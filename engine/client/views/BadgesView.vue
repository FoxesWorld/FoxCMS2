<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { foxesApi } from '@engine/api'
import { bootstrapBoolean, type BootstrapValue } from '@engine/domain/bootstrap'
import { loadBadges, type BadgeDefinition } from '@engine/content/contentData'
import Badges from '@theme/userOptions/pages/badges/Badges.vue'


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
interface RewardClaimResponse {
  type?: 'success' | 'warning'
  message?: string
  alreadyClaimed?: boolean
  badgeApplied?: boolean
  currencyApplied?: boolean
  reward?: ClaimedReward
  badge?: ClaimedRewardBadge | null
  currency?: ClaimedRewardCurrency | null
  badges?: BootstrapValue
  balance?: BootstrapValue
}

const badges = ref<readonly BadgeDefinition[]>([])
const authenticated = bootstrapBoolean(appBootstrap, 'isLogged')
const claimCode = ref('')
const claiming = ref(false)
const claimFeedback = ref<RewardClaimFeedback | null>(null)
const claimedReward = ref<ClaimedReward | null>(null)
const loading = ref(true)
const error = ref(false)


async function claimReward(): Promise<void> {
  const code = claimCode.value.trim()
  claimFeedback.value = null
  claimedReward.value = null
  if (!authenticated) {
    claimFeedback.value = { type: 'error', message: 'Войдите в аккаунт, чтобы получить награду.' }
    return
  }
  if (!/^(?:fcr|fcb)_[A-Za-z0-9_-]{43}$/.test(code)) {
    claimFeedback.value = { type: 'error', message: 'Введите полный криптографический код награды.' }
    return
  }

  claiming.value = true
  try {
    const response = await foxesApi.post<RewardClaimResponse>({
      user_doaction: 'claimReward',
      claimCode: code,
    })
    claimFeedback.value = {
      type: response.type === 'warning' ? 'warning' : 'success',
      message: response.message || 'Награда получена.',
    }
    claimedReward.value = response.reward
      ? {
          ...response.reward,
          badge: response.badge ?? response.reward.badge ?? null,
          currency: response.currency ?? response.reward.currency ?? null,
        }
      : null
    if (response.badges !== undefined) appBootstrap.user.badges = response.badges
    if (response.balance !== undefined) appBootstrap.user.balance = response.balance
    claimCode.value = ''
  } catch (requestError) {
    console.error('[FoxesCraft] Reward claim failed', requestError)
    claimFeedback.value = {
      type: 'error',
      message: requestError instanceof Error && requestError.message.trim()
        ? requestError.message
        : 'Не удалось применить код награды.',
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
    :claimed-reward="claimedReward"
    @claim="claimReward"
  />
</template>
