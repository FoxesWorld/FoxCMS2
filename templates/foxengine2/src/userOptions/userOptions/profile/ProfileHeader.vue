<script setup lang="ts">
import type { CSSProperties } from 'vue'
import type { ProfileRecord } from '@engine/contracts/user-pages'

const props = defineProps<{ profile: ProfileRecord; accent: string; isOwner: boolean; canEditPhoto: boolean }>()
const emit = defineEmits<{ settings: []; editPhoto: [] }>()
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
      <img v-if="profile.profilePhoto" :src="profile.profilePhoto" :alt="profile.login">
      <span v-else>{{ profile.login?.slice(0, 1).toUpperCase() }}</span>
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
        {{ profile.groupName || `Группа ${profile.user_group ?? '—'}` }}
      </span>
    </div>

    <button v-if="isOwner" class="button button--primary profile-cover__settings" type="button" @click="emit('settings')">
      Настроить профиль
    </button>
  </header>
</template>
