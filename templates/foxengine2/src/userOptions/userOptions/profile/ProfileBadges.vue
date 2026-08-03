<script setup lang="ts">
import { t } from '@/i18n'

import { ref } from 'vue'
import type { ProfileBadge } from '@engine/contracts/user-pages'

defineProps<{
  badges: ProfileBadge[]
  compact?: boolean
}>()
const emit = defineEmits<{ open: [id: string] }>()
const failedImages = ref(new Set<string>())
function badgeKey(badge: ProfileBadge): string { return `${badge.id}-${badge.acquiredAt ?? ''}` }
function markImageFailed(badge: ProfileBadge): void {
  failedImages.value = new Set([...failedImages.value, badgeKey(badge)])
}
</script>

<template>
  <div v-if="compact" class="profile-badges profile-badges--compact" :aria-label="t('theme.useroptions.useroptions.profile.profilebadges.001')">
    <button
      v-for="badge in badges"
      :key="badgeKey(badge)"
      class="profile-badge-icon"
      type="button"
      :title="t('theme.useroptions.useroptions.profile.profilebadges.002', [badge.title, badge.acquiredLabel ? t('theme.useroptions.useroptions.profile.profilebadges.009', [badge.acquiredLabel]) : ''])"
      :aria-label="t('theme.useroptions.useroptions.profile.profilebadges.003', [badge.title])"
      @click="emit('open', badge.id)"
    >
      <img v-if="badge.image && !failedImages.has(badgeKey(badge))" :src="badge.image" alt="" @error="markImageFailed(badge)">
      <span v-else>{{ badge.title.slice(0, 1).toUpperCase() }}</span>
    </button>
  </div>

  <section v-else class="profile-panel profile-badge-panel">
    <header class="profile-panel__heading">
      <div>
        <span class="profile-panel__eyebrow">{{ t('theme.useroptions.useroptions.profile.profilebadges.004') }}</span>
        <h2>{{ t('theme.useroptions.useroptions.profile.profilebadges.005') }}</h2>
      </div>
      <strong>{{ badges.length }}</strong>
    </header>

    <div v-if="badges.length" class="profile-badge-list">
      <button
        v-for="badge in badges"
        :key="badgeKey(badge)"
        class="profile-badge-card"
        type="button"
        @click="emit('open', badge.id)"
      >
        <span class="profile-badge-card__visual">
          <img v-if="badge.image && !failedImages.has(badgeKey(badge))" :src="badge.image" alt="" @error="markImageFailed(badge)">
          <span v-else>{{ badge.title.slice(0, 1).toUpperCase() }}</span>
        </span>
        <span class="profile-badge-card__copy">
          <strong>{{ badge.title }}</strong>
          <small v-if="badge.acquiredLabel">{{ t('theme.useroptions.useroptions.profile.profilebadges.006') }} {{ badge.acquiredLabel }}</small>
          <small v-else>{{ t('theme.useroptions.useroptions.profile.profilebadges.007') }}</small>
          <span>{{ badge.description }}</span>
        </span>
        <span class="profile-badge-card__arrow" aria-hidden="true">›</span>
      </button>
    </div>
    <p v-else class="profile-panel__empty">{{ t('theme.useroptions.useroptions.profile.profilebadges.008') }}</p>
  </section>
</template>
