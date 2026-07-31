<script setup lang="ts">
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import { useLastUser } from '@engine/shell/useLastUser'
const icon=themeAsset(appBootstrap,'icons/lastuser.png')
const { user,loading,error,formatDate,openProfile }=useLastUser()
</script>
<template>
  <section class="sidebar-card legacy-sidebar-card">
    <div class="sidebar-card__heading legacy-card-title"><img :src="icon" alt="" aria-hidden="true"><div><strong>Новый лис</strong><small>Последняя регистрация</small></div></div>
    <div v-if="loading" class="sidebar-placeholder">Загружаем профиль…</div>
    <div v-else-if="error || !user" class="sidebar-placeholder">Новый участник скоро появится здесь.</div>
    <button v-else class="last-user" type="button" @click="openProfile(user.login)">
      <img v-if="user.profilePhoto" :src="user.profilePhoto" :alt="user.login"><span v-else class="last-user__fallback">{{ user.login.slice(0,1).toUpperCase() }}</span>
      <span><strong>{{ user.realname || user.login }}</strong><small>@{{ user.login }} · {{ formatDate(user.reg_date) }}</small></span>
    </button>
  </section>
</template>
