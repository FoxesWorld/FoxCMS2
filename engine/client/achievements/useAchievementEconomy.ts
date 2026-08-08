import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { t } from '@/i18n'
import {
  exchangeAchievementPoints as submitAchievementPointExchange,
  loadAchievementEconomy,
  type AchievementEconomyState,
} from '@/achievements/achievementEconomy'

export function useAchievementEconomy(enabled: MaybeRefOrGetter<boolean>) {
  const economy = ref<AchievementEconomyState | null>(null)
  const economyLoading = ref(false)
  const economyError = ref('')
  const exchangeBusy = ref(false)
  const exchangePointsInput = ref(0)
  const exchangeMessage = ref('')

  const exchangeAmount = computed(() => Math.max(0, Math.trunc(Number(exchangePointsInput.value) || 0)))
  const exchangePreviewUnits = computed(() => economy.value
    ? Math.floor(exchangeAmount.value / Math.max(1, economy.value.pointsPerUnit))
    : 0)
  const canExchangePoints = computed(() => {
    const state = economy.value
    if (!state || !state.enabled || exchangeBusy.value) return false
    const amount = exchangeAmount.value
    return amount >= state.minimumPoints
      && amount <= state.availablePoints
      && amount % state.pointsPerUnit === 0
      && exchangePreviewUnits.value > 0
  })

  function reset(): void {
    economy.value = null
    economyError.value = ''
    exchangeMessage.value = ''
    exchangePointsInput.value = 0
  }

  async function refreshAchievementEconomy(): Promise<void> {
    if (!toValue(enabled)) return
    economyLoading.value = true
    economyError.value = ''
    try {
      const state = await loadAchievementEconomy()
      if (!toValue(enabled)) return
      economy.value = state
      const current = exchangeAmount.value
      if (state.availablePoints < state.minimumPoints) {
        exchangePointsInput.value = 0
      } else if (current < state.minimumPoints || current > state.availablePoints || current % state.pointsPerUnit !== 0) {
        exchangePointsInput.value = state.minimumPoints
      }
    } catch (reason) {
      economy.value = null
      economyError.value = reason instanceof Error ? reason.message : t('engine.views.achievements.082')
    } finally {
      economyLoading.value = false
    }
  }

  function exchangeAllAchievementPoints(): void {
    if (!economy.value) return
    exchangePointsInput.value = economy.value.maxExchangeablePoints
  }

  async function exchangeMyAchievementPoints(): Promise<void> {
    if (!canExchangePoints.value || !economy.value) return
    const points = exchangeAmount.value
    const units = exchangePreviewUnits.value
    if (!window.confirm(t('engine.views.achievements.083', [points, units]))) return
    exchangeBusy.value = true
    economyError.value = ''
    exchangeMessage.value = ''
    try {
      const response = await submitAchievementPointExchange(points)
      economy.value = response.economy
      exchangeMessage.value = response.message
      exchangePointsInput.value = response.economy.availablePoints >= response.economy.minimumPoints
        ? response.economy.minimumPoints
        : 0
    } catch (reason) {
      economyError.value = reason instanceof Error ? reason.message : t('engine.views.achievements.084')
    } finally {
      exchangeBusy.value = false
    }
  }

  watch(
    () => toValue(enabled),
    (active) => {
      if (active) void refreshAchievementEconomy()
      else reset()
    },
    { immediate: true },
  )

  return {
    economy,
    economyLoading,
    economyError,
    exchangeBusy,
    exchangePointsInput,
    exchangeMessage,
    exchangeAmount,
    exchangePreviewUnits,
    canExchangePoints,
    refreshAchievementEconomy,
    exchangeAllAchievementPoints,
    exchangeMyAchievementPoints,
  }
}
