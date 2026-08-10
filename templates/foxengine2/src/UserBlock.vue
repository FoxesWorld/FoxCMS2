<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { NavigationDefinition } from '@engine/domain/bootstrap'
import type { BalanceMatrix } from '@engine/domain/userBalance'
import { accountNavigationIcon } from '@theme/domain/accountNavigation'
import MessagesPopover from './user-panel/MessagesPopover.vue'
import NotificationsPopover from './user-panel/NotificationsPopover.vue'
import ProfilePopover from './user-panel/ProfilePopover.vue'

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
const activePanel = ref<UserPanelName | null>(null)
const userPanelRoot = ref<HTMLElement | null>(null)

function togglePanel(panel: UserPanelName): void {
  activePanel.value = activePanel.value === panel ? null : panel
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
      <ProfilePopover
        :open="activePanel === 'profile'"
        :display-name="displayName"
        :profile-photo="profilePhoto"
        :balance="balance"
        :account-items="accountItems"
        @toggle="togglePanel('profile')"
        @activate="activate"
      />

      <span class="user-panel__separator" aria-hidden="true" />

      <MessagesPopover
        :open="activePanel === 'messages'"
        @toggle="togglePanel('messages')"
      />

      <NotificationsPopover
        :open="activePanel === 'notifications'"
        @toggle="togglePanel('notifications')"
      />
    </div>
  </div>
</template>
