<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { t } from '@/i18n'
import { showToast } from '@/notifications/toasts'
import {
  formatSessionTime,
  refreshUserSessions,
  revokeUserSession,
  sessionDeviceIcon,
  userSessions,
  type ActiveUserSession,
} from '@/sessions/userSessions'

const rememberedSessionsCount = computed(() =>
  userSessions.items.filter((session) => session.remembered).length)
const shortSessionsCount = computed(() =>
  userSessions.items.filter((session) => !session.remembered).length)
const currentSession = computed(() =>
  userSessions.items.find((session) => session.current) ?? null)

onMounted(() => void refreshUserSessions({ silent: userSessions.initialized }))

function sessionTypeLabel(session: ActiveUserSession): string {
  return session.remembered
    ? t('engine.views.devices.008')
    : t('engine.views.devices.009')
}

function sessionTechnicalLabel(session: ActiveUserSession): string {
  return [session.browser, session.operatingSystem]
    .filter((value) => value.trim())
    .join(' · ') || session.deviceLabel
}

async function deactivateSession(session: ActiveUserSession): Promise<void> {
  if (session.current) return
  if (!window.confirm(t('engine.views.devices.038', [session.deviceLabel]))) return

  try {
    const message = await revokeUserSession(session.sessionUuid)
    showToast(message, 'success')
  } catch (error) {
    showToast(
      error instanceof Error ? error.message : t('engine.views.devices.039'),
      'error',
    )
  }
}
</script>

<template>
  <section class="devices-center" aria-labelledby="devices-center-title">
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
            :disabled="userSessions.loading"
            @click="refreshUserSessions()"
          >
            <i
              class="fa-solid fa-rotate"
              :class="{ 'devices-spinner': userSessions.loading }"
              aria-hidden="true"
            />
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
        <span class="devices-center__node devices-center__node--desktop">
          <i class="fa-solid fa-desktop" />
        </span>
        <span class="devices-center__node devices-center__node--mobile">
          <i class="fa-solid fa-mobile-screen-button" />
        </span>
        <span class="devices-center__node devices-center__node--browser">
          <i class="fa-solid fa-globe" />
        </span>
        <span class="devices-center__visual-core">
          <strong>{{ userSessions.activeCount }}</strong>
          <small>{{ t('engine.views.devices.004') }}</small>
        </span>
      </div>
    </header>

    <div class="devices-center__metrics" :aria-label="t('engine.views.devices.036')">
      <article class="devices-metric devices-metric--active">
        <span class="devices-metric__icon">
          <i class="fa-solid fa-signal" aria-hidden="true" />
        </span>
        <span class="devices-metric__content">
          <small>{{ t('engine.views.devices.031') }}</small>
          <strong>{{ userSessions.activeCount }}</strong>
          <span>{{ t('engine.views.devices.004') }}</span>
        </span>
      </article>

      <article class="devices-metric devices-metric--remembered">
        <span class="devices-metric__icon">
          <i class="fa-solid fa-key" aria-hidden="true" />
        </span>
        <span class="devices-metric__content">
          <small>{{ t('engine.views.devices.022') }}</small>
          <strong>{{ rememberedSessionsCount }}</strong>
          <span>{{ t('engine.views.devices.032') }}</span>
        </span>
      </article>

      <article class="devices-metric devices-metric--short">
        <span class="devices-metric__icon">
          <i class="fa-solid fa-hourglass-half" aria-hidden="true" />
        </span>
        <span class="devices-metric__content">
          <small>{{ t('engine.views.devices.023') }}</small>
          <strong>{{ shortSessionsCount }}</strong>
          <span>{{ t('engine.views.devices.033') }}</span>
        </span>
      </article>
    </div>

    <aside v-if="currentSession" class="devices-center__current-access">
      <span class="devices-center__current-icon">
        <i :class="sessionDeviceIcon(currentSession)" aria-hidden="true" />
        <i class="fa-solid fa-circle-check" aria-hidden="true" />
      </span>
      <span class="devices-center__current-copy">
        <small>{{ t('engine.views.devices.034') }}</small>
        <strong>{{ currentSession.deviceLabel }}</strong>
        <span>{{ currentSession.locationLabel || t('engine.views.devices.015') }}</span>
      </span>
      <span class="devices-center__current-description">
        <i class="fa-solid fa-shield" aria-hidden="true" />
        {{ t('engine.views.devices.035') }}
      </span>
    </aside>

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
      <button class="button button--ghost" type="button" @click="refreshUserSessions()">
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
      <li
        v-for="session in userSessions.items"
        :key="session.sessionUuid"
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
            <dt>
              <span><i class="fa-solid fa-location-dot" aria-hidden="true" /></span>
              {{ t('engine.views.devices.014') }}
            </dt>
            <dd>{{ session.locationLabel || t('engine.views.devices.015') }}</dd>
          </div>
          <div class="device-session-card__detail">
            <dt>
              <span><i class="fa-solid fa-globe" aria-hidden="true" /></span>
              {{ t('engine.views.devices.016') }}
            </dt>
            <dd class="device-session-card__mono">{{ session.ipAddress || '—' }}</dd>
          </div>
          <div class="device-session-card__detail">
            <dt>
              <span><i class="fa-solid fa-earth-europe" aria-hidden="true" /></span>
              {{ t('engine.views.devices.027') }}
            </dt>
            <dd>{{ session.browser || '—' }}</dd>
          </div>
          <div class="device-session-card__detail">
            <dt>
              <span><i class="fa-solid fa-microchip" aria-hidden="true" /></span>
              {{ t('engine.views.devices.028') }}
            </dt>
            <dd>{{ session.operatingSystem || '—' }}</dd>
          </div>
          <div class="device-session-card__detail">
            <dt>
              <span><i class="fa-solid fa-clock-rotate-left" aria-hidden="true" /></span>
              {{ t('engine.views.devices.017') }}
            </dt>
            <dd>{{ formatSessionTime(session.lastSeenAt) }}</dd>
          </div>
          <div class="device-session-card__detail">
            <dt>
              <span><i class="fa-solid fa-hourglass-end" aria-hidden="true" /></span>
              {{ t('engine.views.devices.018') }}
            </dt>
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
            :disabled="userSessions.revokingSessionUuid !== ''"
            @click="deactivateSession(session)"
          >
            <i
              :class="userSessions.revokingSessionUuid === session.sessionUuid
                ? 'fa-solid fa-spinner devices-spinner'
                : 'fa-solid fa-power-off'"
              aria-hidden="true"
            />
            <span>
              {{ userSessions.revokingSessionUuid === session.sessionUuid
                ? t('engine.views.devices.041')
                : t('engine.views.devices.042') }}
            </span>
          </button>
        </footer>
      </li>
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

<style scoped>
.devices-center {
  display: grid;
  gap: 22px;
  padding: 10px;
  border-radius: 10px;
  background: #ebe6e0;
}

.devices-spinner {
  animation: devices-spin .9s linear infinite;
}

@keyframes devices-spin {
  to { transform: rotate(360deg); }
}

.devices-center__hero {
  position: relative;
  min-height: 290px;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 280px;
  align-items: center;
  gap: 32px;
  overflow: hidden;
  padding: 38px 42px;
  border: 1px solid color-mix(in srgb, var(--color-accent) 28%, var(--color-border));
  border-radius: 26px;
  background:
    radial-gradient(circle at 86% 40%, color-mix(in srgb, var(--color-accent) 19%, transparent), transparent 31%),
    linear-gradient(132deg, color-mix(in srgb, var(--color-accent) 10%, var(--color-surface-strong)), var(--color-surface-strong) 62%);
  box-shadow: var(--shadow-medium);
  isolation: isolate;
}

.devices-center__hero::before {
  position: absolute;
  inset: 0;
  z-index: -1;
  content: '';
  background-image:
    linear-gradient(color-mix(in srgb, var(--color-border) 28%, transparent) 1px, transparent 1px),
    linear-gradient(90deg, color-mix(in srgb, var(--color-border) 28%, transparent) 1px, transparent 1px);
  background-size: 36px 36px;
  opacity: .24;
  mask-image: linear-gradient(90deg, transparent 8%, #000 74%);
}

.devices-center__hero::after {
  position: absolute;
  right: -80px;
  bottom: -130px;
  width: 390px;
  height: 390px;
  z-index: -1;
  border: 1px solid color-mix(in srgb, var(--color-accent) 26%, transparent);
  border-radius: 50%;
  content: '';
  box-shadow:
    0 0 0 42px color-mix(in srgb, var(--color-accent) 3%, transparent),
    0 0 0 84px color-mix(in srgb, var(--color-accent) 2%, transparent);
}

.devices-center__hero-content {
  min-width: 0;
  position: relative;
  z-index: 2;
}

.devices-center__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 13px;
  color: var(--color-accent);
  font-family: var(--font-game);
  font-size: .7rem;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
}

.devices-center__eyebrow i {
  font-size: .9rem;
}

.devices-center__hero h1 {
  max-width: 700px;
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(2.2rem, 5vw, 4rem);
  line-height: .95;
  letter-spacing: -.025em;
}

.devices-center__hero p {
  max-width: 690px;
  margin: 18px 0 0;
  color: var(--color-text-muted);
  font-size: .98rem;
  line-height: 1.7;
}

.devices-center__hero-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 26px;
}

.devices-center__refresh {
  min-width: 132px;
  justify-content: center;
}

.devices-center__registry-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text-muted);
  font-size: .76rem;
  font-weight: 750;
}

.devices-center__registry-status i {
  color: var(--color-success);
}

.devices-center__visual {
  position: relative;
  width: 230px;
  height: 230px;
  justify-self: center;
}

.devices-center__orbit {
  position: absolute;
  inset: 0;
  border: 1px solid color-mix(in srgb, var(--color-accent) 34%, transparent);
  border-radius: 50%;
}

.devices-center__orbit--outer {
  box-shadow: inset 0 0 48px color-mix(in srgb, var(--color-accent) 5%, transparent);
}

.devices-center__orbit--inner {
  inset: 42px;
  border-style: dashed;
  opacity: .72;
  animation: devices-orbit 24s linear infinite;
}

@keyframes devices-orbit {
  to { transform: rotate(360deg); }
}

.devices-center__visual-core {
  position: absolute;
  inset: 70px;
  display: grid;
  place-items: center;
  align-content: center;
  border: 1px solid color-mix(in srgb, var(--color-accent) 45%, var(--color-border));
  border-radius: 50%;
  background: color-mix(in srgb, var(--color-accent) 13%, var(--color-surface-strong));
  box-shadow: 0 18px 50px color-mix(in srgb, var(--color-accent) 18%, transparent);
  text-align: center;
}

.devices-center__visual-core strong {
  color: var(--color-accent);
  font-family: var(--font-display);
  font-size: 2.5rem;
  line-height: .9;
}

.devices-center__visual-core small {
  width: 76px;
  margin-top: 7px;
  color: var(--color-text-muted);
  font-size: .58rem;
  font-weight: 900;
  line-height: 1.15;
  letter-spacing: .05em;
  text-transform: uppercase;
}

.devices-center__node {
  position: absolute;
  z-index: 2;
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--color-accent) 38%, var(--color-border));
  border-radius: 14px;
  color: var(--color-accent);
  background: var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}

.devices-center__node--desktop { top: 8px; left: 30px; }
.devices-center__node--mobile { right: 0; top: 96px; }
.devices-center__node--browser { bottom: 4px; left: 40px; }

.devices-center__metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.devices-metric {
  --metric-color: var(--color-accent);
  min-width: 0;
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr);
  align-items: center;
  gap: 14px;
  padding: 18px;
  border: 1px solid var(--color-border);
  border-radius: 18px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--metric-color) 7%, var(--color-surface)), var(--color-surface) 58%);
  box-shadow: var(--shadow-soft);
}

.devices-metric--active { --metric-color: var(--color-success); }
.devices-metric--short { --metric-color: var(--color-warning); }

.devices-metric__icon {
  width: 52px;
  height: 52px;
  display: grid;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--metric-color) 28%, var(--color-border));
  border-radius: 16px;
  color: var(--metric-color);
  background: color-mix(in srgb, var(--metric-color) 10%, var(--color-surface-soft));
  font-size: 1.12rem;
}

.devices-metric__content {
  min-width: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 2px 10px;
}

.devices-metric__content small {
  grid-column: 1;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: .68rem;
  font-weight: 850;
  letter-spacing: .045em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.devices-metric__content strong {
  grid-column: 2;
  grid-row: 1 / 3;
  align-self: center;
  color: var(--metric-color);
  font-family: var(--font-display);
  font-size: 2rem;
  line-height: 1;
}

.devices-metric__content span {
  grid-column: 1;
  color: var(--color-text-muted);
  font-size: .75rem;
  line-height: 1.35;
}

.devices-center__current-access {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) minmax(220px, .65fr);
  align-items: center;
  gap: 16px;
  padding: 16px 18px;
  border: 1px solid color-mix(in srgb, var(--color-success) 38%, var(--color-border));
  border-radius: 18px;
  background: linear-gradient(90deg, color-mix(in srgb, var(--color-success) 9%, var(--color-surface)), var(--color-surface));
}

.devices-center__current-icon {
  position: relative;
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  border-radius: 15px;
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 12%, var(--color-surface-soft));
  font-size: 1.15rem;
}

.devices-center__current-icon .fa-circle-check {
  position: absolute;
  right: -4px;
  bottom: -3px;
  padding: 2px;
  border-radius: 50%;
  color: var(--color-success);
  background: var(--color-surface);
  font-size: .72rem;
}

.devices-center__current-copy {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.devices-center__current-copy small {
  color: var(--color-success);
  font-size: .66rem;
  font-weight: 900;
  letter-spacing: .05em;
  text-transform: uppercase;
}

.devices-center__current-copy strong,
.devices-center__current-copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.devices-center__current-copy span {
  color: var(--color-text-muted);
  font-size: .78rem;
}

.devices-center__current-description {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding-left: 16px;
  border-left: 1px solid color-mix(in srgb, var(--color-success) 24%, var(--color-border));
  color: var(--color-text-muted);
  font-size: .78rem;
  line-height: 1.45;
}

.devices-center__current-description i {
  color: var(--color-success);
}

.devices-center__section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-top: 6px;
}

.devices-center__section-heading > div {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 13px;
}

.devices-center__section-icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 10%, var(--color-surface-soft));
}

.devices-center__section-heading small,
.devices-center__section-heading strong,
.devices-center__section-heading p {
  display: block;
}

.devices-center__section-heading small {
  margin-bottom: 2px;
  color: var(--color-accent);
  font-size: .65rem;
  font-weight: 900;
  letter-spacing: .055em;
  text-transform: uppercase;
}

.devices-center__section-heading strong {
  color: var(--color-text);
  font-family: var(--font-display);
  font-size: 1.25rem;
}

.devices-center__section-heading p {
  margin: 3px 0 0;
  color: var(--color-text-muted);
  font-size: .78rem;
}

.devices-center__section-count {
  min-width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  padding-inline: 10px;
  border: 1px solid var(--color-border);
  border-radius: 13px;
  color: var(--color-accent);
  background: var(--color-surface-soft);
  font-family: var(--font-display);
  font-size: 1.12rem;
}

.devices-center__error {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 13px;
  padding: 15px;
  border: 1px solid color-mix(in srgb, var(--color-danger) 42%, var(--color-border));
  border-radius: 16px;
  background: color-mix(in srgb, var(--color-danger) 8%, var(--color-surface));
}

.devices-center__error-icon {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 13px;
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 11%, var(--color-surface-soft));
}

.devices-center__error > span:nth-child(2) {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.devices-center__error small {
  overflow: hidden;
  color: var(--color-text-muted);
  text-overflow: ellipsis;
}

.devices-center__state {
  min-height: 280px;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 12px;
  padding: 32px;
  border: 1px dashed var(--color-border-strong);
  border-radius: 22px;
  color: var(--color-text-muted);
  background:
    radial-gradient(circle at 50% 42%, color-mix(in srgb, var(--color-accent) 8%, transparent), transparent 32%),
    var(--color-surface-soft);
  text-align: center;
}

.devices-center__state-icon {
  width: 66px;
  height: 66px;
  display: grid;
  place-items: center;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  color: var(--color-accent);
  background: var(--color-surface);
  font-size: 1.45rem;
  box-shadow: var(--shadow-soft);
}

.devices-center__state strong {
  color: var(--color-text);
  font-size: 1rem;
}

.devices-center__state p {
  margin: 0;
}

.devices-center__list {
  display: grid;
  gap: 14px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.device-session-card {
  --session-color: var(--color-accent);
  position: relative;
  display: grid;
  gap: 17px;
  overflow: hidden;
  padding: 20px 20px 16px 24px;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background:
    linear-gradient(105deg, color-mix(in srgb, var(--session-color) 5%, var(--color-surface)), var(--color-surface) 48%);
  box-shadow: var(--shadow-soft);
  transition: border-color var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
}

.device-session-card:hover {
  border-color: color-mix(in srgb, var(--session-color) 35%, var(--color-border));
  transform: translateY(-1px);
  box-shadow: var(--shadow-medium);
}

.device-session-card.is-current {
  --session-color: var(--color-success);
  border-color: color-mix(in srgb, var(--color-success) 46%, var(--color-border));
}

.device-session-card.is-remembered:not(.is-current) {
  --session-color: var(--color-accent);
}

.device-session-card__rail {
  position: absolute;
  inset: 0 auto 0 0;
  width: 4px;
  background: var(--session-color);
  box-shadow: 6px 0 22px color-mix(in srgb, var(--session-color) 28%, transparent);
}

.device-session-card__top {
  min-width: 0;
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
}

.device-session-card__icon {
  width: 54px;
  height: 54px;
  display: grid;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--session-color) 28%, var(--color-border));
  border-radius: 17px;
  color: var(--session-color);
  background: color-mix(in srgb, var(--session-color) 10%, var(--color-surface-soft));
  font-size: 1.2rem;
}

.device-session-card__identity {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.device-session-card__identity small {
  color: var(--session-color);
  font-size: .64rem;
  font-weight: 900;
  letter-spacing: .055em;
  text-transform: uppercase;
}

.device-session-card__identity strong,
.device-session-card__identity > span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.device-session-card__identity strong {
  color: var(--color-text);
  font-size: 1.05rem;
}

.device-session-card__identity > span {
  color: var(--color-text-muted);
  font-size: .78rem;
}

.device-session-card__badges {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 7px;
}

.device-session-card__badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 9px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  color: var(--color-text-muted);
  background: var(--color-surface-soft);
  font-size: .66rem;
  font-weight: 850;
  white-space: nowrap;
}

.device-session-card__badge--current {
  border-color: color-mix(in srgb, var(--color-success) 34%, var(--color-border));
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 10%, var(--color-surface-soft));
}

.device-session-card__badge--remembered {
  border-color: color-mix(in srgb, var(--color-accent) 30%, var(--color-border));
  color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 9%, var(--color-surface-soft));
}

.device-session-card__details {
  display: grid;
  grid-template-columns: 1.35fr .9fr 1fr 1fr 1.2fr 1.2fr;
  gap: 8px;
  margin: 0;
}

.device-session-card__detail {
  min-width: 0;
  padding: 11px 12px;
  border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
  border-radius: 13px;
  background: color-mix(in srgb, var(--color-surface-soft) 82%, transparent);
}

.device-session-card__detail dt {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 6px;
  color: var(--color-text-muted);
  font-size: .62rem;
  font-weight: 850;
  letter-spacing: .035em;
  text-transform: uppercase;
}

.device-session-card__detail dt span {
  width: 23px;
  height: 23px;
  flex: 0 0 23px;
  display: grid;
  place-items: center;
  border-radius: 7px;
  color: var(--session-color);
  background: color-mix(in srgb, var(--session-color) 9%, var(--color-surface));
  font-size: .65rem;
}

.device-session-card__detail dd {
  margin: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: .78rem;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.device-session-card__mono {
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  font-size: .74rem !important;
}

.device-session-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 13px;
  border-top: 1px solid var(--color-border);
  color: var(--color-text-muted);
  font-size: .72rem;
}

.device-session-card__footer span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.device-session-card__footer i {
  color: var(--session-color);
}

.device-session-card__footer strong {
  color: var(--color-text);
  font-weight: 750;
}

.device-session-card__current-lock {
  color: var(--color-success);
  font-weight: 750;
}

.device-session-card__revoke {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 34px;
  padding: 7px 11px;
  border: 1px solid color-mix(in srgb, var(--color-danger) 34%, var(--color-border));
  border-radius: 10px;
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 7%, var(--color-surface-soft));
  cursor: pointer;
  font: inherit;
  font-size: .7rem;
  font-weight: 850;
  transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}

.device-session-card__revoke:hover:not(:disabled),
.device-session-card__revoke:focus-visible:not(:disabled) {
  border-color: color-mix(in srgb, var(--color-danger) 60%, var(--color-border));
  background: color-mix(in srgb, var(--color-danger) 13%, var(--color-surface-soft));
  transform: translateY(-1px);
  outline: none;
}

.device-session-card__revoke:disabled {
  cursor: wait;
  opacity: .58;
}

.devices-center__footnote {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  gap: 14px;
  padding: 18px;
  border: 1px solid var(--color-border);
  border-radius: 18px;
  color: var(--color-text-muted);
  background: linear-gradient(135deg, color-mix(in srgb, var(--color-accent) 5%, var(--color-surface-soft)), var(--color-surface-soft));
}

.devices-center__footnote-icon {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  color: var(--color-accent);
  background: color-mix(in srgb, var(--color-accent) 10%, var(--color-surface));
}

.devices-center__footnote > span:last-child {
  min-width: 0;
  display: grid;
  gap: 6px;
}

.devices-center__footnote strong {
  color: var(--color-text);
  font-size: .84rem;
}

.devices-center__footnote p {
  margin: 0;
  font-size: .78rem;
  line-height: 1.55;
}

.devices-center__footnote small {
  color: var(--color-accent);
  font-size: .7rem;
  font-weight: 750;
}

@media (max-width: 1120px) {
  .device-session-card__details {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 860px) {
  .devices-center__hero {
    grid-template-columns: minmax(0, 1fr) 190px;
    min-height: 250px;
    padding: 30px;
  }

  .devices-center__visual {
    width: 180px;
    height: 180px;
  }

  .devices-center__orbit--inner { inset: 34px; }
  .devices-center__visual-core { inset: 54px; }
  .devices-center__node--desktop { top: 1px; left: 19px; }
  .devices-center__node--mobile { top: 70px; }
  .devices-center__node--browser { left: 26px; }

  .devices-center__metrics {
    grid-template-columns: 1fr;
  }

  .devices-center__current-access {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .devices-center__current-description {
    grid-column: 1 / -1;
    justify-content: flex-start;
    padding: 12px 0 0;
    border-top: 1px solid color-mix(in srgb, var(--color-success) 24%, var(--color-border));
    border-left: 0;
  }

  .device-session-card__top {
    grid-template-columns: 54px minmax(0, 1fr);
  }

  .device-session-card__badges {
    grid-column: 1 / -1;
    justify-content: flex-start;
  }
}

@media (max-width: 650px) {
  .devices-center {
    gap: 17px;
  }

  .devices-center__hero {
    min-height: auto;
    grid-template-columns: 1fr;
    padding: 25px 21px;
    border-radius: 21px;
  }

  .devices-center__hero::before {
    mask-image: linear-gradient(180deg, transparent, #000);
  }

  .devices-center__hero h1 {
    font-size: clamp(2.1rem, 12vw, 3.2rem);
  }

  .devices-center__visual {
    display: none;
  }

  .devices-center__hero-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .devices-center__refresh {
    width: 100%;
  }

  .devices-center__registry-status {
    justify-content: center;
  }

  .devices-metric {
    padding: 15px;
  }

  .devices-center__current-access {
    padding: 14px;
  }

  .devices-center__section-heading p {
    display: none;
  }

  .devices-center__error {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .devices-center__error .button {
    grid-column: 1 / -1;
    width: 100%;
  }

  .device-session-card {
    gap: 14px;
    padding: 16px 14px 14px 18px;
    border-radius: 17px;
  }

  .device-session-card__top {
    grid-template-columns: 46px minmax(0, 1fr);
    gap: 11px;
  }

  .device-session-card__icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
  }

  .device-session-card__details {
    grid-template-columns: 1fr 1fr;
  }

  .device-session-card__detail--location {
    grid-column: 1 / -1;
  }

  .device-session-card__footer {
    align-items: flex-start;
    flex-direction: column;
  }

  .devices-center__footnote {
    padding: 15px;
  }
}

@media (max-width: 430px) {
  .devices-center__section-icon {
    display: none;
  }

  .devices-center__section-count {
    min-width: 36px;
    height: 36px;
  }

  .devices-center__current-description {
    font-size: .73rem;
  }

  .device-session-card__details {
    grid-template-columns: 1fr;
  }

  .device-session-card__detail--location {
    grid-column: auto;
  }

  .device-session-card__badge {
    font-size: .61rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .devices-spinner,
  .devices-center__orbit--inner {
    animation: none;
  }

  .device-session-card {
    transition: none;
  }
}
</style>
