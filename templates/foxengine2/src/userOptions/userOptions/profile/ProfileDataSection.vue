<script setup lang="ts">
import { computed } from 'vue'
import type { ProfileEntry } from '@engine/contracts/user-pages'

const props = withDefaults(defineProps<{
  title: string
  entries: ProfileEntry[]
  eyebrow?: string
  emptyText?: string
  variant?: 'default' | 'balance'
}>(), {
  variant: 'default',
})

const isBalance = computed(() => props.variant === 'balance')
const balanceCounter = computed(() => {
  const count = props.entries.length
  if (count === 1) return '1 валюта'
  if (count >= 2 && count <= 4) return `${count} валюты`
  return `${count} валют`
})
</script>

<template>
  <section
    class="profile-panel profile-data-section"
    :class="{
      balance: isBalance,
      'profile-data-section--balance': isBalance,
    }"
  >
    <header class="profile-panel__heading">
      <div class="profile-data-section__title">
        <span class="profile-data-section__heading-copy">
          <span v-if="eyebrow" class="profile-panel__eyebrow">{{ eyebrow }}</span>
          <h2>{{ title }}</h2>
        </span>
      </div>
      <strong
        v-if="entries.length"
        class="profile-data-section__counter"
        :class="{ 'profile-data-section__counter--balance': isBalance }"
      >
        {{ isBalance ? balanceCounter : entries.length }}
      </strong>
    </header>

    <div
      v-if="entries.length"
      class="profile-data-grid"
      :class="{ 'profile-balance-grid': isBalance }"
    >
      <article
        v-for="entry in entries"
        :key="`${entry.label}-${entry.value}`"
        class="profile-data-grid__entry"
        :class="[
          { 'profile-data-grid__entry--icon': entry.icon },
          entry.kind ? `profile-data-grid__entry--${entry.kind}` : '',
        ]"
      >
        <span v-if="entry.icon" class="profile-data-grid__icon-shell" aria-hidden="true">
          <img class="profile-data-grid__icon" :src="entry.icon" alt="">
        </span>
        <span class="profile-data-grid__copy">
          <span class="profile-data-grid__label">{{ entry.label }}</span>
          <strong>{{ entry.value }}</strong>
          <small v-if="entry.detail">{{ entry.detail }}</small>
        </span>
      </article>
    </div>

    <p v-else class="profile-panel__empty">{{ emptyText || 'Данные пока не опубликованы.' }}</p>
  </section>
</template>
