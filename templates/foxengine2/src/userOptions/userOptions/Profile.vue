<script setup lang="ts">
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
  viewerGroup: number
  isOwner: boolean
  canEditPhoto: boolean
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
</script>

<template>
  <div v-if="loading" class="content-skeleton" aria-label="Загрузка профиля"><span /><span /><span /></div>
  <div v-else-if="error || !profile" class="system-message system-message--error">
    <strong>Профиль недоступен</strong>
    <p>{{ error }}</p>
  </div>

  <article v-else class="content-surface profile-page" :style="{ '--profile-accent': accent }">
    <ProfileHeader
      :profile="profile"
      :accent="accent"
      :is-owner="isOwner"
      :can-edit-photo="canEditPhoto"
      @settings="openSettings"
      @edit-photo="emit('editPhoto')"
    />
    <ProfileFacts
      :profile="profile"
      :registration="registration"
      :last-activity="lastActivity"
      :badges="badges"
      @open-badge="openBadge"
    />

    <div v-if="viewerGroup === 1 && !isOwner" class="profile-admin-context">
      Административный просмотр: приватные данные отображаются только в пределах разрешённого API-контракта.
    </div>

    <div class="profile-content-grid">
      <div class="profile-content-grid__main">
        <ProfileInfo :profile="profile" />
        <ProfileDataSection
          title="Игровая активность"
          eyebrow="Экспедиции"
          :entries="servers"
          empty-text="Сохранённой игровой активности пока нет."
        />
      </div>

      <aside class="profile-content-grid__aside">
        <ProfileBadges :badges="badges" @open="openBadge" />
        <ProfileDataSection
          v-if="isOwner || viewerGroup === 1"
          title="Баланс"
          eyebrow="Личный счёт"
          :entries="balances"
          empty-text="Баланс пока пуст."
        />
      </aside>
    </div>
  </article>

  <ProfilePhotoDialog
    v-if="photoDialogOpen && profile"
    :target-login="profile.login || 'пользователь'"
    :preview="profile.profilePhoto"
    :uploading="photoUploading"
    :error="photoError"
    @close="emit('closePhoto')"
    @upload="emit('uploadPhoto', $event)"
  />
</template>

<style src="../../styles/profile.css"></style>
