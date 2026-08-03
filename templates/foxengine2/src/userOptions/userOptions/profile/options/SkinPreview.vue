<script setup lang="ts">
import { t } from '@/i18n'

defineProps<{ front: string; back: string; loading: boolean }>()
</script>

<template>
  <div class="skin-preview" :class="{ 'skin-preview--loading': loading }" aria-live="polite">
    <div v-if="loading" class="skin-preview__loading"><i class="fa-solid fa-rotate" aria-hidden="true" /><span>{{ t('theme.useroptions.useroptions.profile.options.skinpreview.001') }}</span></div>
    <figure>
      <div class="skin-preview__model"><img v-if="front" :src="front" :alt="t('theme.useroptions.useroptions.profile.options.skinpreview.002')"><span v-else>{{ t('theme.useroptions.useroptions.profile.options.skinpreview.003') }}</span></div>
      <figcaption>{{ t('theme.useroptions.useroptions.profile.options.skinpreview.004') }}</figcaption>
    </figure>
    <figure>
      <div class="skin-preview__model"><img v-if="back" :src="back" :alt="t('theme.useroptions.useroptions.profile.options.skinpreview.005')"><span v-else>{{ t('theme.useroptions.useroptions.profile.options.skinpreview.006') }}</span></div>
      <figcaption>{{ t('theme.useroptions.useroptions.profile.options.skinpreview.007') }}</figcaption>
    </figure>
  </div>
</template>
