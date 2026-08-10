<script setup lang="ts">
import { t } from '@/i18n'
import CurrentSessionBanner from '@/devices/CurrentSessionBanner.vue'
import DeviceSessionCard from '@/devices/DeviceSessionCard.vue'
import DevicesHero from '@/devices/DevicesHero.vue'
import DevicesMetrics from '@/devices/DevicesMetrics.vue'
import { useDevicesCenter } from '@/devices/useDevicesCenter'
import '@/devices/devices-center.css'

const {
  currentSession,
  deactivateSession,
  refreshSessions,
  rememberedSessionsCount,
  shortSessionsCount,
  userSessions,
} = useDevicesCenter()
</script>

<template>
  <section class="devices-center" aria-labelledby="devices-center-title">
    <DevicesHero
      :active-count="userSessions.activeCount"
      :loading="userSessions.loading"
      @refresh="refreshSessions()"
    />

    <DevicesMetrics
      :active-count="userSessions.activeCount"
      :remembered-count="rememberedSessionsCount"
      :short-count="shortSessionsCount"
    />

    <CurrentSessionBanner v-if="currentSession" :session="currentSession" />

    <div class="devices-center__section-heading">
      <div>
        <span class="devices-center__section-icon">
          <i class="fa-solid fa-laptop-file" aria-hidden="true" />
        </span>
        <span>
          <small>{{ t('engine.views.devices.021') }}</small>
          <strong>{{ t('engine.views.devices.025') }}</strong>
          <p>{{ t('engine.views.devices.026') }}</p>
        </span>
      </div>
      <span class="devices-center__section-count">{{ userSessions.activeCount }}</span>
    </div>

    <div v-if="userSessions.error" class="devices-center__error" role="alert">
      <span class="devices-center__error-icon">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
      </span>
      <span>
        <strong>{{ t('engine.views.devices.037') }}</strong>
        <small>{{ userSessions.error }}</small>
      </span>
      <button class="button button--ghost" type="button" @click="refreshSessions()">
        <i class="fa-solid fa-rotate" aria-hidden="true" />
        {{ t('engine.views.devices.007') }}
      </button>
    </div>

    <div
      v-if="userSessions.loading && !userSessions.initialized"
      class="devices-center__state"
      aria-live="polite"
    >
      <span class="devices-center__state-icon">
        <i class="fa-solid fa-spinner devices-spinner" aria-hidden="true" />
      </span>
      <strong>{{ t('engine.views.devices.010') }}</strong>
    </div>

    <div v-else-if="userSessions.items.length === 0" class="devices-center__state">
      <span class="devices-center__state-icon">
        <i class="fa-solid fa-laptop" aria-hidden="true" />
      </span>
      <strong>{{ t('engine.views.devices.011') }}</strong>
      <p>{{ t('engine.views.devices.012') }}</p>
    </div>

    <ol v-else class="devices-center__list">
      <DeviceSessionCard
        v-for="session in userSessions.items"
        :key="session.sessionUuid"
        :session="session"
        :revoking-session-uuid="userSessions.revokingSessionUuid"
        @revoke="deactivateSession"
      />
    </ol>

    <footer class="devices-center__footnote">
      <span class="devices-center__footnote-icon">
        <i class="fa-solid fa-user-shield" aria-hidden="true" />
      </span>
      <span>
        <strong>{{ t('engine.views.devices.005') }}</strong>
        <p>{{ t('engine.views.devices.020') }}</p>
        <small>{{ t('engine.views.devices.030') }}</small>
      </span>
    </footer>
  </section>
</template>
