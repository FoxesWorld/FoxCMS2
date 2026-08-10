<script setup lang="ts">
import { t } from '@/i18n'
import {
  formatSessionTime,
  sessionDeviceIcon,
  type ActiveUserSession,
} from '@/sessions/userSessions'
import { sessionTechnicalLabel, sessionTypeLabel } from './deviceSessionPresentation'

const props = defineProps<{
  session: ActiveUserSession
  revokingSessionUuid: string
}>()

const emit = defineEmits<{ revoke: [session: ActiveUserSession] }>()
</script>

<template>
  <li
    class="device-session-card"
    :class="{
      'is-current': session.current,
      'is-remembered': session.remembered,
    }"
  >
    <span class="device-session-card__rail" aria-hidden="true" />

    <div class="device-session-card__top">
      <span class="device-session-card__icon">
        <i :class="sessionDeviceIcon(session)" aria-hidden="true" />
      </span>

      <span class="device-session-card__identity">
        <small>{{ session.current ? t('engine.views.devices.013') : sessionTypeLabel(session) }}</small>
        <strong>{{ session.deviceLabel }}</strong>
        <span>{{ sessionTechnicalLabel(session) }}</span>
      </span>

      <span class="device-session-card__badges">
        <span v-if="session.current" class="device-session-card__badge device-session-card__badge--current">
          <i class="fa-solid fa-circle-check" aria-hidden="true" />
          {{ t('engine.views.devices.013') }}
        </span>
        <span class="device-session-card__badge" :class="{ 'device-session-card__badge--remembered': session.remembered }">
          <i :class="session.remembered ? 'fa-solid fa-key' : 'fa-solid fa-clock'" aria-hidden="true" />
          {{ session.remembered ? t('engine.views.devices.022') : t('engine.views.devices.023') }}
        </span>
      </span>
    </div>

    <dl class="device-session-card__details">
      <div class="device-session-card__detail device-session-card__detail--location">
        <dt><span><i class="fa-solid fa-location-dot" aria-hidden="true" /></span>{{ t('engine.views.devices.014') }}</dt>
        <dd>{{ session.locationLabel || t('engine.views.devices.015') }}</dd>
      </div>
      <div class="device-session-card__detail">
        <dt><span><i class="fa-solid fa-globe" aria-hidden="true" /></span>{{ t('engine.views.devices.016') }}</dt>
        <dd class="device-session-card__mono">{{ session.ipAddress || '—' }}</dd>
      </div>
      <div class="device-session-card__detail">
        <dt><span><i class="fa-solid fa-earth-europe" aria-hidden="true" /></span>{{ t('engine.views.devices.027') }}</dt>
        <dd>{{ session.browser || '—' }}</dd>
      </div>
      <div class="device-session-card__detail">
        <dt><span><i class="fa-solid fa-microchip" aria-hidden="true" /></span>{{ t('engine.views.devices.028') }}</dt>
        <dd>{{ session.operatingSystem || '—' }}</dd>
      </div>
      <div class="device-session-card__detail">
        <dt><span><i class="fa-solid fa-clock-rotate-left" aria-hidden="true" /></span>{{ t('engine.views.devices.017') }}</dt>
        <dd>{{ formatSessionTime(session.lastSeenAt) }}</dd>
      </div>
      <div class="device-session-card__detail">
        <dt><span><i class="fa-solid fa-hourglass-end" aria-hidden="true" /></span>{{ t('engine.views.devices.018') }}</dt>
        <dd>{{ formatSessionTime(session.expiresAt) }}</dd>
      </div>
    </dl>

    <footer class="device-session-card__footer">
      <span>
        <i class="fa-solid fa-calendar-check" aria-hidden="true" />
        {{ t('engine.views.devices.019') }}:
        <strong>{{ formatSessionTime(session.createdAt) }}</strong>
      </span>
      <span v-if="session.current" class="device-session-card__current-lock">
        <i class="fa-solid fa-lock" aria-hidden="true" />
        {{ t('engine.views.devices.029') }}
      </span>
      <button
        v-else
        class="device-session-card__revoke"
        type="button"
        :disabled="revokingSessionUuid !== ''"
        @click="emit('revoke', props.session)"
      >
        <i
          :class="revokingSessionUuid === session.sessionUuid
            ? 'fa-solid fa-spinner devices-spinner'
            : 'fa-solid fa-power-off'"
          aria-hidden="true"
        />
        <span>
          {{ revokingSessionUuid === session.sessionUuid
            ? t('engine.views.devices.041')
            : t('engine.views.devices.042') }}
        </span>
      </button>
    </footer>
  </li>
</template>
