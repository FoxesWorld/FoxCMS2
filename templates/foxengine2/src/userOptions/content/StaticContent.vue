<script setup lang="ts">
import type { StaticPageDefinition } from '@engine/content/contentData'
import type { PublicRewardOffer } from '@engine/domain/publicRewardOffers'
import type { RewardOfferFeedback } from '@engine/rewards/usePublicRewardOffer'
import { formatBalanceAmount } from '@engine/domain/userBalance'
import StaticPage from './StaticPage.vue'

defineProps<{
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
</script>

<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <template v-else-if="page">
    <StaticPage :page="page" />

    <Teleport
      v-if="pageId === 'rules'"
      defer
      to=".static-content-page--rules"
    >
      <section class="rules-badge-claim" aria-labelledby="rules-badge-title">
        <div class="rules-badge-claim__mark" aria-hidden="true">
          <img v-if="rewardOfferIcon" :src="rewardOfferIcon" alt="">
          <i v-else class="fa-solid fa-award" />
        </div>
        <div class="rules-badge-claim__content">
          <p class="rules-badge-claim__eyebrow">Награда за ознакомление</p>
          <h2 id="rules-badge-title">{{ rewardOffer?.reward.title || 'Награда за правила' }}</h2>
          <p v-if="rewardOffer">
            {{ rewardOffer.reward.description || rewardOffer.reward.badge?.description || 'Подтвердите ознакомление с правилами и получите бейдж в профиль.' }}
            <template v-if="rewardOffer.reward.currency">
              Награда: {{ formatBalanceAmount(rewardOffer.reward.currency.amount) }} {{ rewardOffer.reward.currency.currencyName }}.
            </template>
          </p>
          <p v-else-if="rewardOfferLoading">Проверяем выпущенный криптографический ключ награды…</p>
          <p v-else>Для этого раздела пока не выпущен активный placement-ключ.</p>
        </div>
        <button
          class="button button--primary rules-badge-claim__button"
          type="button"
          :disabled="!authenticated || rewardOfferLoading || rewardOfferClaiming || !rewardOffer?.claimable"
          @click="emit('claimReward')"
        >
          <i
            class="fa-solid"
            :class="rewardOfferClaiming ? 'fa-spinner' : rewardOffer?.acquired ? 'fa-circle-check' : 'fa-key'"
            aria-hidden="true"
          />
          <span v-if="rewardOfferLoading">Проверяем ключ…</span>
          <span v-else-if="rewardOfferClaiming">Погашаем ключ…</span>
          <span v-else-if="rewardOffer?.acquired">Награда получена</span>
          <span v-else>Получить по ключу</span>
        </button>
        <p
          v-if="rewardOfferFeedback"
          class="rules-badge-claim__feedback"
          :class="`rules-badge-claim__feedback--${rewardOfferFeedback.type}`"
          :role="rewardOfferFeedback.type === 'error' ? 'alert' : 'status'"
          aria-live="polite"
        >
          {{ rewardOfferFeedback.message }}
        </p>
        <p v-else-if="!authenticated" class="rules-badge-claim__hint">
          Для получения награды необходимо войти в аккаунт.
        </p>
      </section>
    </Teleport>
  </template>
  <div v-else-if="error" class="system-message system-message--error">
    <strong>Страница не найдена</strong>
    <p>Запрошенный материал отсутствует в runtime-реестре темы.</p>
  </div>
</template>
