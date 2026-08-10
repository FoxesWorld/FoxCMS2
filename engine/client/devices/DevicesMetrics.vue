<script setup lang="ts">
import { t } from '@/i18n'

defineProps<{
  activeCount: number
  rememberedCount: number
  shortCount: number
}>()
</script>

<template>
  <div class="devices-center__metrics" :aria-label="t('engine.views.devices.036')">
    <article class="devices-metric devices-metric--active">
      <span class="devices-metric__icon"><i class="fa-solid fa-signal" aria-hidden="true" /></span>
      <span class="devices-metric__content">
        <small>{{ t('engine.views.devices.031') }}</small>
        <strong>{{ activeCount }}</strong>
        <span>{{ t('engine.views.devices.004') }}</span>
      </span>
    </article>

    <article class="devices-metric devices-metric--remembered">
      <span class="devices-metric__icon"><i class="fa-solid fa-key" aria-hidden="true" /></span>
      <span class="devices-metric__content">
        <small>{{ t('engine.views.devices.022') }}</small>
        <strong>{{ rememberedCount }}</strong>
        <span>{{ t('engine.views.devices.032') }}</span>
      </span>
    </article>

    <article class="devices-metric devices-metric--short">
      <span class="devices-metric__icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true" /></span>
      <span class="devices-metric__content">
        <small>{{ t('engine.views.devices.023') }}</small>
        <strong>{{ shortCount }}</strong>
        <span>{{ t('engine.views.devices.033') }}</span>
      </span>
    </article>
  </div>
</template>
