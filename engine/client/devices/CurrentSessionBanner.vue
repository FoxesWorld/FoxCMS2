<script setup lang="ts">
import { t } from '@/i18n'
import { sessionDeviceIcon, type ActiveUserSession } from '@/sessions/userSessions'

defineProps<{ session: ActiveUserSession }>()
</script>

<template>
  <aside class="devices-center__current-access">
    <span class="devices-center__current-icon">
      <i :class="sessionDeviceIcon(session)" aria-hidden="true" />
      <i class="fa-solid fa-circle-check" aria-hidden="true" />
    </span>
    <span class="devices-center__current-copy">
      <small>{{ t('engine.views.devices.034') }}</small>
      <strong>{{ session.deviceLabel }}</strong>
      <span>{{ session.locationLabel || t('engine.views.devices.015') }}</span>
    </span>
    <span class="devices-center__current-description">
      <i class="fa-solid fa-shield" aria-hidden="true" />
      {{ t('engine.views.devices.035') }}
    </span>
  </aside>
</template>
