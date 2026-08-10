<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@/i18n'
import { appBootstrap } from '@engine/app/context'
import { themeAsset, type NavigationDefinition } from '@engine/domain/bootstrap'
import { balanceCurrencyIconPath, formatBalanceAmount, type BalanceMatrix } from '@engine/domain/userBalance'
import { formatSessionTime, refreshUserSessions, sessionDeviceIcon, userSessions } from '@engine/sessions/userSessions'
import { accountNavigationIcon } from '@theme/domain/accountNavigation'

const props = defineProps<{
  open: boolean
  displayName: string
  profilePhoto: string
  balance: BalanceMatrix
  accountItems: NavigationDefinition[]
}>()

const emit = defineEmits<{
  toggle: []
  activate: [item: NavigationDefinition]
}>()

const router = useRouter()
const photoLoadFailed = ref(false)
const devicesPreview = computed(() => userSessions.items.slice(0, 3))
const visibleAccountItems = computed(() => props.accountItems.filter(
  (item) => item.intent !== 'devices' && item.route !== 'devices',
))
const balanceCurrencies = computed(() => props.balance.currencies.map((currency) => ({
  ...currency,
  formatted: formatBalanceAmount(currency.amount),
  icon: themeAsset(appBootstrap, balanceCurrencyIconPath(currency.code)),
})))

watch(
  () => props.open,
  (open) => {
    if (open) void refreshUserSessions({ silent: userSessions.initialized })
  },
)

function openDevicesPage(): void {
  void router.push({ name: 'devices' })
}
</script>

<template>
  <div class="profile-menu user-panel__popover">
    <button
      class="profile-button"
      :class="{ 'is-open': open }"
      type="button"
      aria-haspopup="menu"
      :aria-expanded="open"
      aria-controls="profile-dropdown"
      @click="emit('toggle')"
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
      v-if="open"
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
          <span><i class="fa-solid fa-laptop" aria-hidden="true" />{{ t('theme.userblock.016') }}</span>
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
            <span class="profile-dropdown__device-icon"><i :class="sessionDeviceIcon(session)" aria-hidden="true" /></span>
            <span class="profile-dropdown__device-content">
              <strong>{{ session.deviceLabel }}</strong>
              <small>{{ session.current ? t('theme.userblock.020') : formatSessionTime(session.lastSeenAt) }}</small>
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
        @click="emit('activate', item)"
      >
        <i :class="accountNavigationIcon(item)" aria-hidden="true" />
        <span>{{ item.label }}</span>
      </button>
    </div>
  </div>
</template>
