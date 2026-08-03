<script setup lang="ts">
import { t } from '@/i18n'

import { ref } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
const STORAGE_KEY = 'foxescraft.cookies.accepted'
const visible = ref(localStorage.getItem(STORAGE_KEY) !== 'true')
const iconUrl = themeAsset(appBootstrap, 'icons/cookie.png')
function accept(): void { localStorage.setItem(STORAGE_KEY, 'true'); visible.value = false }
</script>

<template>
  <Transition name="cookie">
    <aside v-if="visible" class="cookie-banner legacy-cookie" :aria-label="t('theme.cookiepopup.001')">
      <img class="cookie-banner__image" :src="iconUrl" alt="" aria-hidden="true">
      <p>{{ t('theme.cookiepopup.002') }}</p>
      <button class="button button--primary" type="button" @click="accept">{{ t('theme.cookiepopup.003') }}</button>
    </aside>
  </Transition>
</template>
