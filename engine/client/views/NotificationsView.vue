<script setup lang="ts">
import { onMounted } from 'vue'
import {
  formatNotificationTime,
  loadMoreNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  notificationCenter,
  notificationIcon,
  refreshNotifications,
  type UserNotification,
} from '@/notifications/notificationCenter'
import { t } from '@/i18n'

onMounted(() => void refreshNotifications({ silent: notificationCenter.initialized }))

async function openNotification(notification: UserNotification): Promise<void> {
  if (notification.unread) {
    try {
      await markNotificationRead(notification.id)
    } catch {
      return
    }
  }
  if (notification.actionUrl) window.location.assign(notification.actionUrl)
}
</script>

<template>
  <section class="notifications-center" aria-labelledby="notifications-center-title">
    <header class="notifications-center__hero">
      <div>
        <span class="notifications-center__eyebrow">{{ t('engine.views.notifications.001') }}</span>
        <h1 id="notifications-center-title">{{ t('engine.views.notifications.002') }}</h1>
        <p>{{ t('engine.views.notifications.003') }}</p>
      </div>
      <div class="notifications-center__summary" aria-live="polite">
        <strong>{{ notificationCenter.unreadCount }}</strong>
        <span>{{ t('engine.views.notifications.004') }}</span>
      </div>
    </header>

    <div class="notifications-center__toolbar">
      <button
        class="button button--ghost"
        type="button"
        :disabled="notificationCenter.loading"
        @click="refreshNotifications()"
      >
        <i class="fa-solid fa-rotate" aria-hidden="true" />
        <span>{{ t('engine.views.notifications.005') }}</span>
      </button>
      <button
        class="button button--primary"
        type="button"
        :disabled="notificationCenter.markingAll || notificationCenter.unreadCount === 0"
        @click="markAllNotificationsRead"
      >
        <i class="fa-solid fa-check-double" aria-hidden="true" />
        <span>{{ t('engine.views.notifications.006') }}</span>
      </button>
    </div>

    <div v-if="notificationCenter.error" class="notifications-center__error" role="alert">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
      <span>{{ notificationCenter.error }}</span>
      <button class="button button--ghost" type="button" @click="refreshNotifications()">
        {{ t('engine.views.notifications.007') }}
      </button>
    </div>

    <div
      v-if="notificationCenter.loading && !notificationCenter.initialized"
      class="notifications-center__state"
      aria-live="polite"
    >
      <i class="fa-solid fa-spinner notification-spinner" aria-hidden="true" />
      <strong>{{ t('engine.views.notifications.008') }}</strong>
    </div>

    <div v-else-if="notificationCenter.items.length === 0" class="notifications-center__state">
      <i class="fa-solid fa-bell-slash" aria-hidden="true" />
      <strong>{{ t('engine.views.notifications.009') }}</strong>
      <p>{{ t('engine.views.notifications.010') }}</p>
    </div>

    <ol v-else class="notifications-center__list">
      <li v-for="notification in notificationCenter.items" :key="notification.id">
        <button
          class="notification-card"
          :class="[
            `notification-card--${notification.severity}`,
            { 'is-unread': notification.unread },
          ]"
          type="button"
          :aria-label="t('engine.views.notifications.011', [notification.title])"
          @click="openNotification(notification)"
        >
          <span class="notification-card__icon">
            <i :class="notificationIcon(notification.type)" aria-hidden="true" />
          </span>
          <span class="notification-card__content">
            <span class="notification-card__heading">
              <strong>{{ notification.title }}</strong>
              <span v-if="notification.unread" class="notification-card__status">
                {{ t('engine.views.notifications.012') }}
              </span>
            </span>
            <span class="notification-card__message">{{ notification.message }}</span>
            <time :datetime="new Date(notification.createdAt * 1000).toISOString()">
              <i class="fa-solid fa-clock" aria-hidden="true" />
              {{ formatNotificationTime(notification.createdAt) }}
            </time>
          </span>
          <i
            v-if="notification.actionUrl"
            class="fa-solid fa-arrow-right notification-card__arrow"
            aria-hidden="true"
          />
        </button>
      </li>
    </ol>

    <div v-if="notificationCenter.hasMore" class="notifications-center__load-more">
      <button
        class="button button--ghost"
        type="button"
        :disabled="notificationCenter.loadingMore"
        @click="loadMoreNotifications"
      >
        <i class="fa-solid fa-chevron-down" aria-hidden="true" />
        <span>{{ notificationCenter.loadingMore ? t('engine.views.notifications.008') : t('engine.views.notifications.013') }}</span>
      </button>
    </div>
  </section>
</template>

<style scoped>
.notifications-center { display:grid; gap:18px; }
.notification-spinner { animation:notification-spin .9s linear infinite; }
@keyframes notification-spin { to { transform:rotate(360deg); } }
.notifications-center__hero { display:flex; align-items:center; justify-content:space-between; gap:24px; padding:28px; border:1px solid var(--color-border); border-radius:var(--radius-large); background:linear-gradient(135deg,color-mix(in srgb,var(--color-accent) 11%,var(--color-surface-strong)),var(--color-surface-strong)); box-shadow:var(--shadow-soft); }
.notifications-center__hero > div:first-child { min-width:0; }
.notifications-center__eyebrow { display:block; margin-bottom:8px; color:var(--color-accent); font-family:var(--font-game); font-size:.7rem; font-weight:850; letter-spacing:.09em; text-transform:uppercase; }
.notifications-center__hero h1 { margin:0; font-family:var(--font-display); font-size:clamp(1.8rem,4vw,3rem); line-height:1; }
.notifications-center__hero p { max-width:680px; margin:10px 0 0; color:var(--color-text-muted); line-height:1.55; }
.notifications-center__summary { width:112px; min-height:96px; flex:0 0 112px; display:grid; place-items:center; align-content:center; padding:12px; border:1px solid color-mix(in srgb,var(--color-accent) 35%,var(--color-border)); border-radius:20px; background:color-mix(in srgb,var(--color-accent) 10%,var(--color-surface-soft)); text-align:center; }
.notifications-center__summary strong { color:var(--color-accent); font-family:var(--font-display); font-size:2.2rem; line-height:1; }
.notifications-center__summary span { margin-top:6px; color:var(--color-text-muted); font-size:.73rem; font-weight:800; text-transform:uppercase; }
.notifications-center__toolbar { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:10px; }
.notifications-center__error { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:12px; padding:14px; border:1px solid color-mix(in srgb,var(--color-danger) 42%,var(--color-border)); border-radius:14px; background:color-mix(in srgb,var(--color-danger) 9%,var(--color-surface)); }
.notifications-center__error > i { color:var(--color-danger); }
.notifications-center__state { min-height:260px; display:grid; place-items:center; align-content:center; gap:10px; padding:28px; border:1px dashed var(--color-border-strong); border-radius:var(--radius-large); color:var(--color-text-muted); background:var(--color-surface-soft); text-align:center; }
.notifications-center__state > i { font-size:2rem; }
.notifications-center__state strong { color:var(--color-text); font-size:1rem; }
.notifications-center__state p { margin:0; }
.notifications-center__list { display:grid; gap:10px; margin:0; padding:0; list-style:none; }
.notification-card { width:100%; display:grid; grid-template-columns:48px minmax(0,1fr) auto; align-items:center; gap:14px; padding:16px; border:1px solid var(--color-border); border-radius:16px; color:var(--color-text); background:var(--color-surface); cursor:pointer; text-align:left; transition:border-color var(--transition-fast),background var(--transition-fast),transform var(--transition-fast),box-shadow var(--transition-fast); }
.notification-card:hover,.notification-card:focus-visible { border-color:var(--color-border-strong); background:var(--color-surface-hover); transform:translateY(-1px); box-shadow:var(--shadow-soft); outline:none; }
.notification-card.is-unread { border-left:4px solid var(--color-accent); background:linear-gradient(90deg,color-mix(in srgb,var(--color-accent) 8%,var(--color-surface)),var(--color-surface)); }
.notification-card__icon { width:46px; height:46px; display:grid; place-items:center; border-radius:14px; color:var(--color-accent); background:color-mix(in srgb,var(--color-accent) 11%,var(--color-surface-soft)); font-size:1.1rem; }
.notification-card--security .notification-card__icon { color:var(--color-danger); background:color-mix(in srgb,var(--color-danger) 10%,var(--color-surface-soft)); }
.notification-card--success .notification-card__icon { color:var(--color-success); background:color-mix(in srgb,var(--color-success) 10%,var(--color-surface-soft)); }
.notification-card--warning .notification-card__icon { color:var(--color-warning); background:color-mix(in srgb,var(--color-warning) 10%,var(--color-surface-soft)); }
.notification-card__content { min-width:0; display:grid; gap:6px; }
.notification-card__heading { display:flex; align-items:center; gap:10px; }
.notification-card__heading strong { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.notification-card__status { flex:0 0 auto; padding:3px 7px; border-radius:var(--radius-pill); color:var(--color-accent-contrast); background:var(--color-accent); font-size:.62rem; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
.notification-card__message { color:var(--color-text-muted); line-height:1.5; }
.notification-card time { display:flex; align-items:center; gap:6px; color:var(--color-text-soft,var(--color-text-muted)); font-size:.75rem; }
.notification-card__arrow { color:var(--color-text-muted); }
.notifications-center__load-more { display:flex; justify-content:center; padding-top:4px; }
@media (max-width: 680px) {
  .notifications-center__hero { align-items:flex-start; padding:20px; }
  .notifications-center__summary { width:82px; min-height:78px; flex-basis:82px; border-radius:16px; }
  .notifications-center__summary strong { font-size:1.75rem; }
  .notifications-center__toolbar { justify-content:stretch; }
  .notifications-center__toolbar .button { flex:1 1 190px; }
  .notifications-center__error { grid-template-columns:auto minmax(0,1fr); }
  .notifications-center__error .button { grid-column:1 / -1; }
  .notification-card { grid-template-columns:42px minmax(0,1fr); padding:13px; }
  .notification-card__icon { width:40px; height:40px; }
  .notification-card__arrow { display:none; }
}
</style>
