<script setup lang="ts">
import { t } from '@/i18n'
import { appBootstrap } from '@engine/app/context'
import { formatUnreadCounter, resolveUserPanelState } from '@theme/domain/userPanel'

defineProps<{ open: boolean }>()
const emit = defineEmits<{ toggle: [] }>()
const panelState = resolveUserPanelState(appBootstrap)
</script>

<template>
  <div class="user-panel__popover">
    <button
      class="user-panel__icon-button"
      :class="{ 'is-open': open }"
      type="button"
      :title="t('theme.userblock.005')"
      :aria-label="t('theme.userblock.012')"
      aria-haspopup="dialog"
      :aria-expanded="open"
      aria-controls="messages-dropdown"
      @click="emit('toggle')"
    >
      <i class="fa-solid fa-envelope" aria-hidden="true" />
      <span
        v-if="panelState.messagesUnread > 0"
        class="user-panel__unread"
        data-element="messagesCounter"
      >{{ formatUnreadCounter(panelState.messagesUnread) }}</span>
    </button>

    <div
      v-if="open"
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
</template>
