<script setup lang="ts">
import type { ProfileBadge, ProfileRecord } from '@engine/contracts/user-pages'
import ProfileBadges from './ProfileBadges.vue'

defineProps<{
  profile: ProfileRecord
  registration: string
  lastActivity: string
  badges: ProfileBadge[]
}>()
const emit = defineEmits<{ openBadge: [id: string] }>()
</script>

<template>
  <section class="profile-statbar" aria-label="Сводка профиля">
    <div class="profile-statbar__status">
      <span>Статус</span>
      <strong>{{ profile.userStatus || 'Путешествует по Лисьему Миру' }}</strong>
    </div>
    <div>
      <span>Регистрация</span>
      <strong>{{ registration }}</strong>
    </div>
    <div>
      <span>Последнее посещение</span>
      <strong>{{ lastActivity }}</strong>
    </div>
    <div v-if="badges.length" class="profile-statbar__badges">
      <span>Бейджи</span>
      <ProfileBadges :badges="badges" compact @open="emit('openBadge', $event)" />
    </div>
  </section>
</template>
