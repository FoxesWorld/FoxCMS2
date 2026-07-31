<script setup lang="ts">
import type { UserDraft, UserRow } from '@modules/AdminPanel/client/useAdminPanel'
import UserTable from './users/UserTable.vue'
import UserEditor from './users/UserEditor.vue'
defineProps<{ users:UserRow[]; search:string; selected:UserRow|null; draft:UserDraft; formatTimestamp:(value?:number|string)=>string }>()
const emit=defineEmits<{ 'update:search':[value:string]; search:[]; edit:[user:UserRow]; save:[] }>()
</script>
<template>
  <section class="admin-section admin-split">
    <UserTable :users="users" :search="search" :selected="selected" :format-timestamp="formatTimestamp" @update:search="emit('update:search',$event)" @search="emit('search')" @edit="emit('edit',$event)" />
    <UserEditor :selected="selected" :draft="draft" @save="emit('save')" />
  </section>
</template>
