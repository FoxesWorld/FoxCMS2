<script setup lang="ts">
import type { UserDraft, UserRow } from '@modules/AdminPanel/client/useAdminPanel'
defineProps<{ selected:UserRow|null; draft:UserDraft }>()
const emit=defineEmits<{ save:[] }>()
</script>
<template>
  <form v-if="selected" class="admin-editor" @submit.prevent="emit('save')">
    <h2>{{ selected.login }}</h2>
    <label><span>UUID</span><input :value="selected.uuid" type="text" readonly></label>
    <label><span>Логин</span><input v-model="draft.login" type="text" minlength="3" maxlength="64" required></label>
    <label><span>Имя</span><input v-model="draft.realname" type="text"></label>
    <label><span>Email</span><input v-model="draft.email" type="email" required></label>
    <label><span>Статус</span><input v-model="draft.userStatus" type="text"></label>
    <label><span>Группа</span><input v-model.number="draft.user_group" type="number" min="1"></label>
    <label><span>Баланс</span><textarea v-model="draft.balance" rows="5" /></label>
    <label><span>Бейджи</span><textarea v-model="draft.badges" rows="5" /></label>
    <label><span>Игровая активность</span><textarea v-model="draft.serversOnline" rows="7" /></label>
    <button class="button button--primary" type="submit">Сохранить пользователя</button>
  </form>
  <div v-else class="empty-state">Выберите пользователя для редактирования.</div>
</template>
