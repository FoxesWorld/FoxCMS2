<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { NavigationDefinition } from '@engine/domain/bootstrap'
import { accountNavigationIcon } from '@theme/domain/accountNavigation'

defineProps<{
  displayName: string
  profilePhoto: string
  isGuest: boolean
  guestItems: NavigationDefinition[]
  accountItems: NavigationDefinition[]
}>()
const emit = defineEmits<{ activate: [item: NavigationDefinition] }>()
const menuOpen = ref(false)
const profileMenu = ref<HTMLElement | null>(null)
const photoLoadFailed = ref(false)

function activate(item: NavigationDefinition): void {
  menuOpen.value = false
  emit('activate', item)
}

function closeOutside(event: PointerEvent): void {
  if (!profileMenu.value?.contains(event.target as Node)) menuOpen.value = false
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

    <div v-else ref="profileMenu" class="profile-menu" @keydown.esc="menuOpen = false">
      <button
        class="profile-button"
        :class="{ 'is-open': menuOpen }"
        type="button"
        aria-haspopup="menu"
        :aria-expanded="menuOpen"
        aria-controls="profile-dropdown"
        @click="menuOpen = !menuOpen"
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
        v-if="menuOpen"
        id="profile-dropdown"
        class="profile-dropdown"
        role="menu"
        aria-label="Меню пользователя"
      >
        <button
          v-for="item in accountItems"
          :key="`${item.intent}:${item.route}:${item.action}`"
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
  </div>
</template>
