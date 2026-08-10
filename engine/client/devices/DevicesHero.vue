<script setup lang="ts">
import { t } from '@/i18n'

defineProps<{
  activeCount: number
  loading: boolean
}>()

const emit = defineEmits<{ refresh: [] }>()
</script>

<template>
  <header class="devices-center__hero">
    <div class="devices-center__hero-content">
      <span class="devices-center__eyebrow">
        <i class="fa-solid fa-shield-halved" aria-hidden="true" />
        {{ t('engine.views.devices.001') }}
      </span>
      <h1 id="devices-center-title">{{ t('engine.views.devices.002') }}</h1>
      <p>{{ t('engine.views.devices.003') }}</p>

      <div class="devices-center__hero-actions">
        <button
          class="button button--primary devices-center__refresh"
          type="button"
          :disabled="loading"
          @click="emit('refresh')"
        >
          <i class="fa-solid fa-rotate" :class="{ 'devices-spinner': loading }" aria-hidden="true" />
          <span>{{ t('engine.views.devices.006') }}</span>
        </button>
        <span class="devices-center__registry-status">
          <i class="fa-solid fa-lock" aria-hidden="true" />
          {{ t('engine.views.devices.024') }}
        </span>
      </div>
    </div>

    <div class="devices-center__visual" aria-hidden="true">
      <span class="devices-center__orbit devices-center__orbit--outer" />
      <span class="devices-center__orbit devices-center__orbit--inner" />
      <span class="devices-center__node devices-center__node--desktop"><i class="fa-solid fa-desktop" /></span>
      <span class="devices-center__node devices-center__node--mobile"><i class="fa-solid fa-mobile-screen-button" /></span>
      <span class="devices-center__node devices-center__node--browser"><i class="fa-solid fa-globe" /></span>
      <span class="devices-center__visual-core">
        <strong>{{ activeCount }}</strong>
        <small>{{ t('engine.views.devices.004') }}</small>
      </span>
    </div>
  </header>
</template>
