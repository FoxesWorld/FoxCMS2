<script setup lang="ts">
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
    <aside v-if="visible" class="cookie-banner legacy-cookie" aria-label="Использование cookie">
      <img class="cookie-banner__image" :src="iconUrl" alt="" aria-hidden="true">
      <p>FoxesCraft использует необходимые cookie для авторизации и сохранения настроек интерфейса.</p>
      <button class="button button--primary" type="button" @click="accept">Принять</button>
    </aside>
  </Transition>
</template>
