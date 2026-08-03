<script setup lang="ts">
import { t } from '@/i18n'

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
  <section class="profile-statbar" :aria-label="t('theme.useroptions.useroptions.profile.profilefacts.001')">
    <div class="profile-statbar__status">
      <span>{{ t('theme.useroptions.useroptions.profile.profilefacts.002') }}</span>
      <strong>{{ profile.userStatus || t('theme.useroptions.useroptions.profile.profilefacts.003') }}</strong>
    </div>
    <div>
      <span>{{ t('theme.useroptions.useroptions.profile.profilefacts.004') }}</span>
      <strong>{{ registration }}</strong>
    </div>
    <div>
      <span>{{ t('theme.useroptions.useroptions.profile.profilefacts.005') }}</span>
      <strong>{{ lastActivity }}</strong>
    </div>
    <div v-if="badges.length" class="profile-statbar__badges">
      <span>{{ t('theme.useroptions.useroptions.profile.profilefacts.006') }}</span>
      <ProfileBadges :badges="badges" compact @open="emit('openBadge', $event)" />
    </div>
  </section>
</template>
