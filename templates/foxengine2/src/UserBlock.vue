<script setup lang="ts">
import type { NavigationDefinition } from '@engine/domain/bootstrap'

defineProps<{
  displayName: string
  isGuest: boolean
  guestItems: NavigationDefinition[]
  accountItems: NavigationDefinition[]
}>()
const emit = defineEmits<{ activate: [item: NavigationDefinition] }>()
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
      >{{ item.label }}</button>
    </template>
    <template v-else>
      <button
        v-for="item in accountItems"
        :key="item.intent"
        :class="item.intent === 'profile' ? 'profile-button' : 'button button--ghost'"
        type="button"
        @click="emit('activate', item)"
      >
        <template v-if="item.intent === 'profile'">
          <span class="profile-button__avatar">{{ displayName.slice(0, 1).toUpperCase() }}</span>
          <span>{{ displayName }}</span>
        </template>
        <template v-else>{{ item.label }}</template>
      </button>
    </template>
  </div>
</template>
