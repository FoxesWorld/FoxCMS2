<script setup lang="ts">
import { t } from '@/i18n'

import { useRouter } from 'vue-router'
import { appBootstrap } from '@engine/app/context'
import { bootstrapBoolean, bootstrapString, themeAsset } from '@engine/domain/bootstrap'
import { usePublicRewardOffer } from '@engine/rewards/usePublicRewardOffer'
import Welcome from '@theme/userOptions/content/Welcome.vue'

const router = useRouter()
const isGuest = !bootstrapBoolean(appBootstrap, 'isLogged', false)
  || bootstrapString(appBootstrap, 'groupTag', 'guest') === 'guest'
const name = bootstrapString(appBootstrap, 'realname', bootstrapString(appBootstrap, 'login', t('engine.homeview.001')))
const artwork = themeAsset(appBootstrap, 'img/AbbyAnderson/AbbyAndFoxInviting2.png')
const rewardOffer = usePublicRewardOffer('welcome-native', !isGuest)

function navigate(route: string): void {
  void router.push({ name: route })
}
</script>

<template>
  <Welcome
    :name="name"
    :is-guest="isGuest"
    :artwork="artwork"
    :reward-offer="rewardOffer.offer.value"
    :reward-offer-icon="rewardOffer.icon.value"
    :reward-offer-loading="rewardOffer.loading.value"
    :reward-offer-claiming="rewardOffer.claiming.value"
    :reward-offer-feedback="rewardOffer.feedback.value"
    @navigate="navigate"
    @claim-reward-offer="rewardOffer.claim"
  />
</template>
