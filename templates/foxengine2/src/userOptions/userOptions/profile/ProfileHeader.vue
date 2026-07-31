<script setup lang="ts">
import { ref, type CSSProperties } from 'vue'
import type { ProfileRecord } from '@engine/contracts/user-pages'

const props = defineProps<{ profile: ProfileRecord; accent: string; isOwner: boolean; canEditPhoto: boolean; canEditUser: boolean }>()
const emit = defineEmits<{ settings: []; editPhoto: []; editUser: [] }>()
const photoFailed = ref(false)
const groupStyle = {
  '--profile-group-color': /^#[0-9a-f]{3,8}$/i.test(props.profile.groupColor ?? '')
    ? props.profile.groupColor
    : props.accent,
} as CSSProperties
</script>

<template>
  <header class="profile-cover">
    <div class="profile-cover__texture" aria-hidden="true" />
    <div class="profile-cover__avatar">
      <img v-if="profile.profilePhoto && !photoFailed" :src="profile.profilePhoto" :alt="profile.login" @error="photoFailed = true">
      <span v-else>{{ profile.login?.slice(0, 1).toUpperCase() || '?' }}</span>
      <button
        v-if="canEditPhoto"
        class="profile-cover__avatar-edit"
        type="button"
        aria-label="Загрузить фото профиля"
        @click="emit('editPhoto')"
      >
        <b aria-hidden="true">＋</b>
        <span>Загрузить фото</span>
      </button>
    </div>

    <div class="profile-cover__identity">
      <span class="profile-cover__overline">Профиль участника</span>
      <h1>{{ profile.login }}</h1>
      <p v-if="profile.realname && profile.realname !== profile.login">{{ profile.realname }}</p>
      <span class="profile-cover__group" :style="groupStyle">
        {{ profile.groupName || profile.groupTag || 'Гости' }}
      </span>
    </div>

    <div v-if="isOwner || canEditUser" class="profile-cover__actions">
      <button v-if="isOwner" class="button button--primary profile-cover__settings" type="button" @click="emit('settings')">
        <i class="fa-solid fa-user-gear" aria-hidden="true" />
        <span>Настроить профиль</span>
      </button>
      <button v-if="canEditUser" class="button button--glass profile-cover__settings" type="button" @click="emit('editUser')">
        <i class="fa-solid fa-pen-to-square" aria-hidden="true" />
        <span>Редактировать</span>
      </button>
    </div>
  </header>
</template>
