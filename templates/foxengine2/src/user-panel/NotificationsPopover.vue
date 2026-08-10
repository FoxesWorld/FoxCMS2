<script setup lang="ts">
import { computed, watch } from 'vue'
import { t } from '@/i18n'
import { appBootstrap } from '@engine/app/context'
import {
  formatNotificationTime,
  markAllNotificationsRead,
  markNotificationRead,
  notificationCenter,
  notificationIcon,
  refreshNotifications,
  type UserNotification,
} from '@engine/notifications/notificationCenter'
import { formatUnreadCounter, resolveUserPanelState } from '@theme/domain/userPanel'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ toggle: [] }>()
const panelState = resolveUserPanelState(appBootstrap)
const notificationsPreview = computed(() => notificationCenter.items.slice(0, 5))

watch(
  () => props.open,
  (open) => {
    if (open) void refreshNotifications({ silent: notificationCenter.initialized })
  },
)

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

function openNotificationsPage(): void {
  window.location.assign(panelState.notificationsUrl)
}
</script>

<template>
  <div class="user-panel__popover">
    <button
      class="user-panel__icon-button"
      :class="{ 'is-open': open }"
      type="button"
      :title="t('theme.userblock.008')"
      :aria-label="t('theme.userblock.014')"
      aria-haspopup="dialog"
      :aria-expanded="open"
      aria-controls="notifications-dropdown"
      @click="emit('toggle')"
    >
      <i class="fa-solid fa-bell" aria-hidden="true" />
      <span
        v-if="notificationCenter.unreadCount > 0"
        class="user-panel__unread"
        data-element="notificationsCounter"
      >{{ formatUnreadCounter(notificationCenter.unreadCount) }}</span>
    </button>

    <div
      v-if="open"
      id="notifications-dropdown"
      class="user-panel-dropdown user-panel-dropdown--notifications"
      role="dialog"
      :aria-label="t('theme.userblock.008')"
      data-element="notificationsPane"
    >
      <div class="user-panel-dropdown__heading">
        <div>
          <span>{{ t('theme.userblock.015') }}</span>
          <h3>{{ t('theme.userblock.008') }}</h3>
        </div>
        <i class="fa-solid fa-bell" aria-hidden="true" />
      </div>

      <div class="user-panel-dropdown__body" data-element="notificationsList">
        <div v-if="notificationCenter.unreadCount > 0" class="notification-dropdown__toolbar">
          <span>{{ t('theme.userblock.010', [notificationCenter.unreadCount]) }}</span>
          <button
            type="button"
            :disabled="notificationCenter.markingAll"
            @click="markAllNotificationsRead"
          >
            <i class="fa-solid fa-check-double" aria-hidden="true" />
            {{ t('engine.views.notifications.006') }}
          </button>
        </div>

        <div v-if="notificationCenter.loading && !notificationCenter.initialized" class="user-panel-dropdown__empty">
          <span class="user-panel-dropdown__empty-icon">
            <i class="fa-solid fa-spinner notification-spinner" aria-hidden="true" />
          </span>
          <strong>{{ t('engine.views.notifications.008') }}</strong>
        </div>

        <div v-else-if="notificationCenter.error && notificationsPreview.length === 0" class="user-panel-dropdown__empty">
          <span class="user-panel-dropdown__empty-icon">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
          </span>
          <p>{{ notificationCenter.error }}</p>
          <button class="button button--ghost" type="button" @click="refreshNotifications()">
            {{ t('engine.views.notifications.007') }}
          </button>
        </div>

        <div v-else-if="notificationsPreview.length === 0" class="user-panel-dropdown__empty">
          <span class="user-panel-dropdown__empty-icon">
            <i class="fa-solid fa-bell-slash" aria-hidden="true" />
          </span>
          <p>{{ t('theme.userblock.009') }}</p>
        </div>

        <div v-else class="notification-dropdown__list">
          <button
            v-for="notification in notificationsPreview"
            :key="notification.id"
            class="notification-dropdown__item"
            :class="[
              `notification-dropdown__item--${notification.severity}`,
              { 'is-unread': notification.unread },
            ]"
            type="button"
            @click="openNotification(notification)"
          >
            <span class="notification-dropdown__icon">
              <i :class="notificationIcon(notification.type)" aria-hidden="true" />
            </span>
            <span class="notification-dropdown__content">
              <strong>{{ notification.title }}</strong>
              <span>{{ notification.message }}</span>
              <time :datetime="new Date(notification.createdAt * 1000).toISOString()">
                {{ formatNotificationTime(notification.createdAt, true) }}
              </time>
            </span>
            <span v-if="notification.unread" class="notification-dropdown__dot" :title="t('engine.views.notifications.012')" />
          </button>
        </div>

        <button class="button button--primary user-panel-dropdown__action" type="button" @click="openNotificationsPage">
          <i class="fa-solid fa-list-ul" aria-hidden="true" />
          <span>{{ t('theme.userblock.011') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
