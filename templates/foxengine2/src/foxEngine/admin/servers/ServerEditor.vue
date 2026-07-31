<script setup lang="ts">
import type { ServerRow } from '@modules/AdminPanel/client/useAdminPanel'
defineProps<{ selected:ServerRow|null; draft:ServerRow }>()
const emit=defineEmits<{ save:[] }>()
</script>
<template>
  <form class="admin-editor" @submit.prevent="emit('save')">
    <h2>{{ selected ? `Редактирование ${selected.serverName}` : 'Новый сервер' }}</h2>
    <label><span>Имя</span><input v-model="draft.serverName" type="text" required></label>
    <label><span>Host</span><input v-model="draft.host" type="text"></label>
    <label><span>Port</span><input v-model.number="draft.port" type="number" min="1" max="65535"></label>
    <div class="admin-checks"><label><input v-model="draft.enabled" type="checkbox"><span>Enabled</span></label><label><input v-model="draft.checkLib" type="checkbox"><span>Check libraries</span></label></div>
    <label><span>Версия сервера</span><input v-model="draft.serverVersion" type="text"></label>
    <label><span>Java runtime</span><input v-model="draft.jreVersion" type="text"></label>
    <label><span>Изображение</span><input v-model="draft.serverImage" type="text"></label>
    <label><span>Описание</span><textarea v-model="draft.serverDescription" rows="4" /></label>
    <label><span>Ignore dirs</span><textarea v-model="draft.ignoreDirs as string" rows="4" /></label>
    <label><span>Server groups</span><textarea v-model="draft.serverGroups as string" rows="4" /></label>
    <label><span>Mods info</span><textarea v-model="draft.modsInfo as string" rows="7" /></label>
    <button class="button button--primary" type="submit">Сохранить сервер</button>
  </form>
</template>
