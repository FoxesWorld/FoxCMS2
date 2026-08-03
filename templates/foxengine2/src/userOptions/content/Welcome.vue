<script setup lang="ts">
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
      <span class="eyebrow">Лисий Мир 3.0</span>
      <h2>Привет, {{ name }}!</h2>
      <p v-if="isGuest" class="lead">Добро пожаловать в мир приключений, сюжетных выборов и технологических экспериментов.</p>
      <p v-else class="lead">Рада видеть тебя снова. Твой путь, достижения и открытия сохранены.</p>
      <div class="feature-grid">
        <div><strong>Живой мир</strong><span>Истории, выборы и события сообщества.</span></div>
        <div><strong>Прогресс</strong><span>Достижения, игровые профили и уникальные бейджи.</span></div>
        <div><strong>Своя атмосфера</strong><span>Лисы, технологии и самостоятельные игровые миры.</span></div>
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
            {{ rewardOffer?.reward.title || 'Загрузка предложения…' }}
          </strong>
          <span v-if="rewardOffer">
            {{ rewardOffer.reward.description || rewardOffer.reward.badge?.description || 'Получите памятный бейдж проекта.' }}
            <template v-if="rewardOffer.reward.currency">
              Награда: {{ formatBalanceAmount(rewardOffer.reward.currency.amount) }} {{ rewardOffer.reward.currency.currencyName }}.
            </template>
          </span>
          <span v-else>Проверяем доступную награду.</span>
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
          <span v-if="rewardOfferLoading">Загрузка…</span>
          <span v-else-if="rewardOfferClaiming">Получение…</span>
          <span v-else-if="rewardOffer?.acquired">Получено</span>
          <span v-else>Получить награду</span>
        </button>
      </section>

      <div class="hero__actions">
        <button class="button button--primary button--large" type="button" @click="emit('navigate', 'start')">В путь</button>
        <button class="button button--ghost button--large" type="button" @click="emit('navigate', 'about')">Оглядеться</button>
      </div>
    </div>
    <ArtworkShowcase
      :src="artwork"
      alt="Эбби и лиса приглашают в Лисий Мир"
      caption="Добро пожаловать в Лисий Мир 3.0"
      variant="invite"
      eager
    />
  </article>
</template>
