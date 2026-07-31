<script setup lang="ts">
import type { UserRow } from '@modules/AdminPanel/client/useAdminPanel'
defineProps<{ users:UserRow[]; search:string; selected:UserRow|null; formatTimestamp:(value?:number|string)=>string }>()
const emit=defineEmits<{ 'update:search':[value:string]; search:[]; edit:[user:UserRow] }>()
function update(event:Event):void{emit('update:search',(event.target as HTMLInputElement).value)}
</script>
<template>
  <div>
    <div class="admin-toolbar">
      <input :value="search" type="search" placeholder="Логин, email или имя" @input="update" @keyup.enter="emit('search')">
      <button class="button button--ghost" type="button" @click="emit('search')">Найти</button>
    </div>
    <div class="admin-list">
      <button v-for="user in users" :key="user.uuid" type="button" :class="{ active:selected?.uuid===user.uuid }" @click="emit('edit',user)">
        <img v-if="user.profilePhoto" :src="user.profilePhoto" alt=""><span v-else>{{ user.login.slice(0,1).toUpperCase() }}</span>
        <div><strong>{{ user.realname || user.login }}</strong><small>@{{ user.login }} · группа {{ user.user_group }} · {{ formatTimestamp(user.last_date) }}</small></div>
      </button>
    </div>
  </div>
</template>
