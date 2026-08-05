<script setup lang="ts">
import { t } from '@/i18n'

import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { appBootstrap } from '@engine/app/context'
import { themeAsset, type NavigationDefinition } from '@engine/domain/bootstrap'
import { accountNavigationIcon } from '@theme/domain/accountNavigation'
import { formatUnreadCounter, resolveUserPanelState } from '@theme/domain/userPanel'
import { balanceCurrencyIconPath, formatBalanceAmount, type BalanceMatrix } from '@engine/domain/userBalance'
import {
  formatNotificationTime,
  markAllNotificationsRead,
  markNotificationRead,
  notificationCenter,
  notificationIcon,
  refreshNotifications,
  type UserNotification,
} from '@engine/notifications/notificationCenter'
import {
  formatSessionTime,
  refreshUserSessions,
  sessionDeviceIcon,
  userSessions,
} from '@engine/sessions/userSessions'

type UserPanelName = 'profile' | 'messages' | 'notifications'

const props = defineProps<{
  displayName: string
  profilePhoto: string
  balance: BalanceMatrix
  isGuest: boolean
  guestItems: NavigationDefinition[]
  accountItems: NavigationDefinition[]
}>()
const emit = defineEmits<{ activate: [item: NavigationDefinition] }>()
const router = useRouter()
const activePanel = ref<UserPanelName | null>(null)
const userPanelRoot = ref<HTMLElement | null>(null)
const photoLoadFailed = ref(false)
const panelState = resolveUserPanelState(appBootstrap)
const notificationsPreview = computed(() => notificationCenter.items.slice(0, 5))
const devicesPreview = computed(() => userSessions.items.slice(0, 3))
const visibleAccountItems = computed(() => props.accountItems.filter((item) => item.intent !== 'devices' && item.route !== 'devices'))
const balanceCurrencies = computed(() => props.balance.currencies.map((currency) => ({
  ...currency,
  formatted: formatBalanceAmount(currency.amount),
  icon: themeAsset(appBootstrap, balanceCurrencyIconPath(currency.code)),
})))

function togglePanel(panel: UserPanelName): void {
  activePanel.value = activePanel.value === panel ? null : panel
  if (activePanel.value === 'notifications') {
    void refreshNotifications({ silent: notificationCenter.initialized })
  }
  if (activePanel.value === 'profile') {
    void refreshUserSessions({ silent: userSessions.initialized })
  }
}

function closePanels(): void {
  activePanel.value = null
}

function activate(item: NavigationDefinition): void {
  closePanels()
  emit('activate', item)
}

function closeOutside(event: PointerEvent): void {
  if (!userPanelRoot.value?.contains(event.target as Node)) closePanels()
}

async function openNotification(notification: UserNotification): Promise<void> {
  if (notification.unread) {
    try {
      await markNotificationRead(notification.id)
    } catch {
      return
    }
  }
  if (notification.actionUrl) {
    closePanels()
    window.location.assign(notification.actionUrl)
  }
}

function openNotificationsPage(): void {
  closePanels()
  window.location.assign(panelState.notificationsUrl)
}

function openDevicesPage(): void {
  closePanels()
  void router.push({ name: 'devices' })
}

onMounted(() => document.addEventListener('pointerdown', closeOutside))
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOutside))
</script>

<template>
  <div class="header-actions legacy-header-actions">
    <template v-if="isGuest">
      <button
        v-for="(item, index) in guestItems"
        :key="item.intent"
        class="button"
        :class="index === guestItems.length - 1 ? 'button--primary' : 'button--ghost'"
        type="button"
        @click="emit('activate', item)"
      >
        <i :class="accountNavigationIcon(item)" aria-hidden="true" />
        <span>{{ item.label }}</span>
      </button>
    </template>

    <div
      v-else
      ref="userPanelRoot"
      class="user-panel"
      @keydown.esc="closePanels"
    >
      <div class="profile-menu user-panel__popover">
        <button
          class="profile-button"
          :class="{ 'is-open': activePanel === 'profile' }"
          type="button"
          aria-haspopup="menu"
          :aria-expanded="activePanel === 'profile'"
          aria-controls="profile-dropdown"
          @click="togglePanel('profile')"
        >
          <span class="profile-button__avatar">
            <img
              v-if="profilePhoto && !photoLoadFailed"
              :src="profilePhoto"
              :alt="displayName"
              @error="photoLoadFailed = true"
            >
            <span v-else aria-hidden="true">{{ displayName.trim().slice(0, 1).toUpperCase() || '?' }}</span>
          </span>
          <span class="profile-button__name">{{ displayName }}</span>
          <span class="profile-button__chevron" aria-hidden="true" />
        </button>

        <div
          v-if="activePanel === 'profile'"
          id="profile-dropdown"
          class="profile-dropdown"
          role="menu"
          :aria-label="t('theme.userblock.001')"
        >
          <div
            class="profile-dropdown__item profile-dropdown__item--balance"
            role="menuitem"
            aria-disabled="true"
            tabindex="-1"
            :aria-label="t('theme.userblock.002')"
          >
            <span class="profile-dropdown__balance-matrix">
              <span
                v-for="currency in balanceCurrencies"
                :key="currency.code"
                class="profile-dropdown__balance-row"
                :class="`profile-dropdown__balance-row--${currency.code}`"
              >
                <img :src="currency.icon" alt="" aria-hidden="true">
                <span>
                  <small>{{ currency.name }}</small>
                  <strong>{{ currency.formatted }}</strong>
                </span>
              </span>
            </span>
          </div>

          <section class="profile-dropdown__devices" :aria-label="t('theme.userblock.016')">
            <header class="profile-dropdown__devices-heading">
              <span>
                <i class="fa-solid fa-laptop" aria-hidden="true" />
                {{ t('theme.userblock.016') }}
              </span>
              <strong>{{ userSessions.activeCount }}</strong>
            </header>

            <div v-if="userSessions.loading && !userSessions.initialized" class="profile-dropdown__devices-state">
              <i class="fa-solid fa-spinner notification-spinner" aria-hidden="true" />
              <span>{{ t('theme.userblock.017') }}</span>
            </div>

            <div v-else-if="userSessions.error" class="profile-dropdown__devices-state profile-dropdown__devices-state--error">
              <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
              <span>{{ userSessions.error }}</span>
              <button type="button" @click="refreshUserSessions()">{{ t('theme.userblock.018') }}</button>
            </div>

            <div v-else-if="devicesPreview.length === 0" class="profile-dropdown__devices-state">
              <i class="fa-solid fa-laptop" aria-hidden="true" />
              <span>{{ t('theme.userblock.019') }}</span>
            </div>

            <div v-else class="profile-dropdown__devices-list">
              <div
                v-for="session in devicesPreview"
                :key="session.sessionUuid"
                class="profile-dropdown__device"
                :class="{ 'is-current': session.current }"
              >
                <span class="profile-dropdown__device-icon">
                  <i :class="sessionDeviceIcon(session)" aria-hidden="true" />
                </span>
                <span class="profile-dropdown__device-content">
                  <strong>{{ session.deviceLabel }}</strong>
                  <small>
                    {{ session.current ? t('theme.userblock.020') : formatSessionTime(session.lastSeenAt) }}
                  </small>
                </span>
                <span v-if="session.remembered" class="profile-dropdown__device-remembered" :title="t('theme.userblock.021')">
                  <i class="fa-solid fa-key" aria-hidden="true" />
                </span>
              </div>
            </div>

            <button class="profile-dropdown__devices-link" type="button" @click="openDevicesPage">
              <span>{{ t('theme.userblock.022') }}</span>
              <i class="fa-solid fa-arrow-right" aria-hidden="true" />
            </button>
          </section>

          <button
            v-for="item in visibleAccountItems"
            :key="t('theme.userblock.004', [item.intent, item.route, item.action])"
            class="profile-dropdown__item"
            :class="{ 'profile-dropdown__item--danger': item.action === 'logout' }"
            type="button"
            role="menuitem"
            @click="activate(item)"
          >
            <i :class="accountNavigationIcon(item)" aria-hidden="true" />
            <span>{{ item.label }}</span>
          </button>
        </div>
      </div>

      <span class="user-panel__separator" aria-hidden="true" />

      <div class="user-panel__popover">
        <button
          class="user-panel__icon-button"
          :class="{ 'is-open': activePanel === 'messages' }"
          type="button"
          :title="t('theme.userblock.005')"
          :aria-label="t('theme.userblock.012')"
          aria-haspopup="dialog"
          :aria-expanded="activePanel === 'messages'"
          aria-controls="messages-dropdown"
          @click="togglePanel('messages')"
        >
          <i class="fa-solid fa-envelope" aria-hidden="true" />
          <span
            v-if="panelState.messagesUnread > 0"
            class="user-panel__unread"
            data-element="messagesCounter"
          >{{ formatUnreadCounter(panelState.messagesUnread) }}</span>
        </button>

        <div
          v-if="activePanel === 'messages'"
          id="messages-dropdown"
          class="user-panel-dropdown user-panel-dropdown--messages"
          role="dialog"
          :aria-label="t('theme.userblock.005')"
          data-element="messagesPane"
        >
          <div class="user-panel-dropdown__heading">
            <div>
              <span>{{ t('theme.userblock.013') }}</span>
              <h3>{{ t('theme.userblock.005') }}</h3>
            </div>
            <i class="fa-solid fa-envelope" aria-hidden="true" />
          </div>
          <div class="user-panel-dropdown__body">
            <div class="user-panel-dropdown__empty">
              <span class="user-panel-dropdown__empty-icon">
                <i class="fa-solid fa-pen-ruler" aria-hidden="true" />
              </span>
              <p>{{ t('theme.userblock.006') }}</p>
            </div>
            <a class="button button--primary user-panel-dropdown__action" :href="panelState.messagesUrl">
              <span>{{ t('theme.userblock.007') }}</span>
              <i class="fa-solid fa-arrow-right" aria-hidden="true" />
            </a>
          </div>
        </div>
      </div>

      <div class="user-panel__popover">
        <button
          class="user-panel__icon-button"
          :class="{ 'is-open': activePanel === 'notifications' }"
          type="button"
          :title="t('theme.userblock.008')"
          :aria-label="t('theme.userblock.014')"
          aria-haspopup="dialog"
          :aria-expanded="activePanel === 'notifications'"
          aria-controls="notifications-dropdown"
          @click="togglePanel('notifications')"
        >
          <i class="fa-solid fa-bell" aria-hidden="true" />
          <span
            v-if="notificationCenter.unreadCount > 0"
            class="user-panel__unread"
            data-element="notificationsCounter"
          >{{ formatUnreadCounter(notificationCenter.unreadCount) }}</span>
        </button>

        <div
          v-if="activePanel === 'notifications'"
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
    </div>
  </div>
</template>
