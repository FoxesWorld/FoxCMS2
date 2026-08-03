import { t } from '@/i18n'
import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { themeAsset, type BootstrapValue } from '@/domain/bootstrap'
import { balanceCurrencyIconPath } from '@/domain/userBalance'
import { normalizePublicRewardOffer, type PublicRewardOffer } from '@/domain/publicRewardOffers'

export interface RewardOfferFeedback {
  type: 'success' | 'warning' | 'error'
  message: string
}

interface RewardOfferResponse {
  offer?: unknown
}

interface RewardOfferClaimResponse extends RewardOfferResponse {
  type?: 'success' | 'warning'
  message?: string
  alreadyClaimed?: boolean
  badgeApplied?: boolean
  currencyApplied?: boolean
  badges?: BootstrapValue
  balance?: BootstrapValue
}

export function usePublicRewardOffer(placement: string, enabled: MaybeRefOrGetter<boolean>) {
  const offer = ref<PublicRewardOffer | null>(null)
  const loading = ref(toValue(enabled))
  const claiming = ref(false)
  const feedback = ref<RewardOfferFeedback | null>(null)
  const icon = computed(() => {
    const badgeImage = offer.value?.reward.badge?.image
    if (badgeImage) return badgeImage
    const code = offer.value?.reward.currency?.currencyCode
    return code ? themeAsset(appBootstrap, balanceCurrencyIconPath(code)) : ''
  })

  async function load(): Promise<void> {
    if (!toValue(enabled)) {
      loading.value = false
      return
    }
    loading.value = true
    feedback.value = null
    try {
      const response = await foxesApi.post<RewardOfferResponse>({
        user_doaction: 'getRewardOffer',
        placement,
      })
      offer.value = normalizePublicRewardOffer(response.offer)
      if (offer.value?.acquired) {
        feedback.value = {
          type: 'warning',
          message: t('engine.rewards.usepublicrewardoffer.001', [offer.value.reward.title]),
        }
      }
    } catch (error) {
      console.error('[FoxesCraft] Public reward offer loading failed', error)
      offer.value = null
    } finally {
      loading.value = false
    }
  }

  async function claim(): Promise<void> {
    if (!toValue(enabled) || loading.value || claiming.value || !offer.value?.claimable) return
    claiming.value = true
    feedback.value = null
    try {
      const response = await foxesApi.post<RewardOfferClaimResponse>({
        user_doaction: 'claimReward',
        offerPlacement: placement,
      })
      const updatedOffer = normalizePublicRewardOffer(response.offer)
      if (updatedOffer) offer.value = updatedOffer
      if (response.badges !== undefined) appBootstrap.user.badges = response.badges
      if (response.balance !== undefined) appBootstrap.user.balance = response.balance
      feedback.value = {
        type: response.type === 'warning' ? 'warning' : 'success',
        message: response.message || t('engine.rewards.usepublicrewardoffer.002'),
      }
    } catch (error) {
      console.error('[FoxesCraft] Public reward claim failed', error)
      feedback.value = {
        type: 'error',
        message: error instanceof Error && error.message.trim()
          ? error.message
          : t('engine.rewards.usepublicrewardoffer.003'),
      }
    } finally {
      claiming.value = false
    }
  }

  watch(
    () => toValue(enabled),
    (active) => {
      if (active) {
        void load()
        return
      }
      offer.value = null
      feedback.value = null
      loading.value = false
    },
    { immediate: true },
  )

  return { offer, loading, claiming, feedback, icon, load, claim }
}
