<fox-page-template id="static-content" schema="1" revision="1" updated-at="">
  <fox-template-body>
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
        <p class="rules-badge-claim__eyebrow">{{ t('theme.useroptions.content.staticcontent.001') }}</p>
        <h2 id="rules-badge-title">{{ rewardOffer?.reward.title || t('theme.useroptions.content.staticcontent.002') }}</h2>
        <p v-if="rewardOffer">
          {{ rewardOffer.reward.description || rewardOffer.reward.badge?.description || t('theme.useroptions.content.staticcontent.003') }}
          <template v-if="rewardOffer.reward.currency"> {{ t('theme.useroptions.content.staticcontent.004') }} {{ formatBalanceAmount(rewardOffer.reward.currency.amount) }} {{ rewardOffer.reward.currency.currencyName }}.
          </template>
        </p>
        <p v-else-if="rewardOfferLoading">{{ t('theme.useroptions.content.staticcontent.005') }}</p>
        <p v-else>{{ t('theme.useroptions.content.staticcontent.006') }}</p>
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
        <span v-if="rewardOfferLoading">{{ t('theme.useroptions.content.staticcontent.007') }}</span>
        <span v-else-if="rewardOfferClaiming">{{ t('theme.useroptions.content.staticcontent.008') }}</span>
        <span v-else-if="rewardOffer?.acquired">{{ t('theme.useroptions.content.staticcontent.009') }}</span>
        <span v-else>{{ t('theme.useroptions.content.staticcontent.010') }}</span>
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
      <p v-else-if="!authenticated" class="rules-badge-claim__hint"> {{ t('theme.useroptions.content.staticcontent.012') }} </p>
    </section>
  </Teleport>
</template>
<div v-else-if="error" class="system-message system-message--error">
  <strong>{{ t('theme.useroptions.content.staticcontent.013') }}</strong>
  <p>{{ t('theme.useroptions.content.staticcontent.014') }}</p>
</div>
  </fox-template-body>
</fox-page-template>
