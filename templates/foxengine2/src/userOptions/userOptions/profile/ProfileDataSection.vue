<script setup lang="ts">
import type { ProfileEntry } from '@engine/contracts/user-pages'

defineProps<{
  title: string
  entries: ProfileEntry[]
  eyebrow?: string
  emptyText?: string
}>()
</script>

<template>
  <section class="profile-panel profile-data-section">
    <header class="profile-panel__heading">
      <div>
        <span v-if="eyebrow" class="profile-panel__eyebrow">{{ eyebrow }}</span>
        <h2>{{ title }}</h2>
      </div>
      <strong v-if="entries.length">{{ entries.length }}</strong>
    </header>
    <div v-if="entries.length" class="profile-data-grid">
      <div v-for="entry in entries" :key="`${entry.label}-${entry.value}`">
        <span>{{ entry.label }}</span>
        <strong>{{ entry.value }}</strong>
        <small v-if="entry.detail">{{ entry.detail }}</small>
      </div>
    </div>
    <p v-else class="profile-panel__empty">{{ emptyText || 'Данные пока не опубликованы.' }}</p>
  </section>
</template>
