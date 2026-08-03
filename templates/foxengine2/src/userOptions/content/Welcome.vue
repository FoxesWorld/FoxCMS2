<script setup lang="ts">
import { t } from '@/i18n'

import type { PublicRewardOffer } from '@engine/domain/publicRewardOffers'
import type { RewardOfferFeedback } from '@engine/rewards/usePublicRewardOffer'
import { formatBalanceAmount } from '@engine/domain/userBalance'
import ArtworkShowcase from '@theme/foxEngine/ArtworkShowcase.vue'

defineProps<{
  name: string
  isGuest: boolean
  artwork: string
  rewardOffer: PublicRewardOffer | null
  rewardOfferIcon: string
  rewardOfferLoading: boolean
  rewardOfferClaiming: boolean
  rewardOfferFeedback: RewardOfferFeedback | null
}>()
const emit = defineEmits<{
  navigate: [route: string]
  claimRewardOffer: []
}>()
</script>

<template>
  <article class="content-surface welcome-native">
    <div class="welcome-native__copy">
      <span class="eyebrow">{{ t('theme.useroptions.content.welcome.001') }}</span>
      <h2>{{ t('theme.useroptions.content.welcome.002') }} {{ name }}!</h2>
      <p v-if="isGuest" class="lead">{{ t('theme.useroptions.content.welcome.003') }}</p>
      <p v-else class="lead">{{ t('theme.useroptions.content.welcome.004') }}</p>
      <div class="feature-grid">
        <div><strong>{{ t('theme.useroptions.content.welcome.005') }}</strong><span>{{ t('theme.useroptions.content.welcome.006') }}</span></div>
        <div><strong>{{ t('theme.useroptions.content.welcome.007') }}</strong><span>{{ t('theme.useroptions.content.welcome.008') }}</span></div>
        <div><strong>{{ t('theme.useroptions.content.welcome.009') }}</strong><span>{{ t('theme.useroptions.content.welcome.010') }}</span></div>
      </div>

      <section
        v-if="!isGuest && (rewardOfferLoading || rewardOffer)"
        class="welcome-reward"
        :class="{
          'welcome-reward--claimed': rewardOffer?.acquired,
          'welcome-reward--error': rewardOfferFeedback?.type === 'error',
        }"
        aria-labelledby="welcome-reward-title"
      >
        <span class="welcome-reward__icon" aria-hidden="true">
          <img v-if="rewardOfferIcon" :src="rewardOfferIcon" alt="">
          <span v-else>◆</span>
        </span>
        <div class="welcome-reward__copy">
          <strong id="welcome-reward-title">
            {{ rewardOffer?.reward.title || t('theme.useroptions.content.welcome.011') }}
          </strong>
          <span v-if="rewardOffer">
            {{ rewardOffer.reward.description || rewardOffer.reward.badge?.description || t('theme.useroptions.content.welcome.012') }}
            <template v-if="rewardOffer.reward.currency"> {{ t('theme.useroptions.content.welcome.013') }} {{ formatBalanceAmount(rewardOffer.reward.currency.amount) }} {{ rewardOffer.reward.currency.currencyName }}.
            </template>
          </span>
          <span v-else>{{ t('theme.useroptions.content.welcome.014') }}</span>
          <p
            v-if="rewardOfferFeedback"
            class="welcome-reward__feedback"
            :class="`welcome-reward__feedback--${rewardOfferFeedback.type}`"
            :role="rewardOfferFeedback.type === 'error' ? 'alert' : 'status'"
            aria-live="polite"
          >
            {{ rewardOfferFeedback.message }}
          </p>
        </div>
        <button
          class="button button--primary welcome-reward__button"
          type="button"
          :disabled="rewardOfferLoading || rewardOfferClaiming || !rewardOffer?.claimable"
          @click="emit('claimRewardOffer')"
        >
          <span v-if="rewardOfferLoading">{{ t('theme.useroptions.content.welcome.016') }}</span>
          <span v-else-if="rewardOfferClaiming">{{ t('theme.useroptions.content.welcome.017') }}</span>
          <span v-else-if="rewardOffer?.acquired">{{ t('theme.useroptions.content.welcome.018') }}</span>
          <span v-else>{{ t('theme.useroptions.content.welcome.019') }}</span>
        </button>
      </section>

      <div class="hero__actions">
        <button class="button button--primary button--large" type="button" @click="emit('navigate', 'start')">{{ t('theme.useroptions.content.welcome.020') }}</button>
        <button class="button button--ghost button--large" type="button" @click="emit('navigate', 'about')">{{ t('theme.useroptions.content.welcome.021') }}</button>
      </div>
    </div>
    <ArtworkShowcase
      :src="artwork"
      :alt="t('theme.useroptions.content.welcome.022')"
      :caption="t('theme.useroptions.content.welcome.023')"
      variant="invite"
      eager
    />
  </article>
</template>
