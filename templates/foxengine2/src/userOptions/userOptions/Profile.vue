<script setup lang="ts">
import { t } from '@/i18n'

import { useRouter } from 'vue-router'
import type { ProfileBadge, ProfileEntry, ProfileRecord } from '@engine/contracts/user-pages'
import ProfileBadges from './profile/ProfileBadges.vue'
import ProfileDataSection from './profile/ProfileDataSection.vue'
import ProfileFacts from './profile/ProfileFacts.vue'
import ProfileHeader from './profile/ProfileHeader.vue'
import ProfileInfo from './profile/ProfileInfo.vue'
import ProfilePhotoDialog from './profile/ProfilePhotoDialog.vue'

const props = defineProps<{
  loading: boolean
  error: string
  profile: ProfileRecord | null
  viewerGroupTag: string
  isOwner: boolean
  canEditPhoto: boolean
  canEditUser: boolean
  photoDialogOpen: boolean
  photoUploading: boolean
  photoError: string
  accent: string
  registration: string
  lastActivity: string
  balances: ProfileEntry[]
  badges: ProfileBadge[]
  servers: ProfileEntry[]
}>()
const emit = defineEmits<{
  editPhoto: []
  closePhoto: []
  uploadPhoto: [file: File]
}>()
const router = useRouter()
function openBadge(id: string): void { void router.push({ name: 'badge', params: { id } }) }
function openSettings(): void { void router.push({ name: 'profile-settings' }) }
function openUserSettings(): void {
  if (!props.profile?.uuid) return
  void router.push(`/settings/profile/${encodeURIComponent(props.profile.uuid)}`)
}
</script>

<template>
  <div v-if="loading" class="content-skeleton" :aria-label="t('theme.useroptions.useroptions.profile.001')"><span /><span /><span /></div>
  <div v-else-if="error || !profile" class="system-message system-message--error">
    <strong>{{ t('theme.useroptions.useroptions.profile.002') }}</strong>
    <p>{{ error }}</p>
  </div>

  <article v-else class="content-surface profile-page" :style="{ '--profile-accent': accent }">
    <ProfileHeader
      :key="profile.uuid || profile.login"
      :profile="profile"
      :accent="accent"
      :is-owner="isOwner"
      :can-edit-photo="canEditPhoto"
      :can-edit-user="canEditUser"
      @settings="openSettings"
      @edit-user="openUserSettings"
      @edit-photo="emit('editPhoto')"
    />
    <ProfileFacts
      :profile="profile"
      :registration="registration"
      :last-activity="lastActivity"
      :badges="badges"
      @open-badge="openBadge"
    />

    <aside
      v-if="viewerGroupTag === 'admin' && !isOwner"
      class="profile-admin-context"
      :aria-label="t('theme.useroptions.useroptions.profile.003')"
    >
      <span class="profile-admin-context__icon" aria-hidden="true">
        <i class="fa-solid fa-shield-halved" />
      </span>
      <span class="profile-admin-context__copy">
        <strong>{{ t('theme.useroptions.useroptions.profile.004') }}</strong>
        <span>{{ t('theme.useroptions.useroptions.profile.005') }}</span>
      </span>
      <span class="profile-admin-context__status">
        <span aria-hidden="true" /> {{ t('theme.useroptions.useroptions.profile.006') }} </span>
    </aside>

    <div class="profile-content-grid">
      <div class="profile-content-grid__main">
        <ProfileInfo :profile="profile" />
        <ProfileDataSection
          :title="t('theme.useroptions.useroptions.profile.007')"
          :eyebrow="t('theme.useroptions.useroptions.profile.008')"
          :entries="servers"
          :empty-text="t('theme.useroptions.useroptions.profile.009')"
        />
      </div>

      <aside class="profile-content-grid__aside">
        <ProfileBadges :badges="badges" @open="openBadge" />
        <ProfileDataSection
          v-if="isOwner || viewerGroupTag === 'admin'"
          :title="t('theme.useroptions.useroptions.profile.010')"
          :eyebrow="t('theme.useroptions.useroptions.profile.011')"
          :entries="balances"
          variant="balance"
          :empty-text="t('theme.useroptions.useroptions.profile.012')"
        />
      </aside>
    </div>
  </article>

  <ProfilePhotoDialog
    v-if="photoDialogOpen && profile"
    :target-login="profile.login || t('theme.useroptions.useroptions.profile.013')"
    :preview="profile.profilePhoto"
    :uploading="photoUploading"
    :error="photoError"
    @close="emit('closePhoto')"
    @upload="emit('uploadPhoto', $event)"
  />
</template>

<style src="../../styles/profile.css"></style>
