<script setup lang="ts">
import type { JsonRow } from '@modules/AdminPanel/client/useAdminPanel'
type CatalogName='infobox'|'badges'|'groups'
defineProps<{ name:CatalogName; rows:JsonRow[]; keyField:string; originalKey:string; draft:string }>()
const emit=defineEmits<{ 'update:name':[value:CatalogName]; 'update:draft':[value:string]; create:[]; edit:[row:JsonRow]; remove:[row:JsonRow]; save:[] }>()
function updateName(event:Event):void{emit('update:name',(event.target as HTMLSelectElement).value as CatalogName)}
function updateDraft(event:Event):void{emit('update:draft',(event.target as HTMLTextAreaElement).value)}
</script>
<template>
  <section class="admin-section admin-split">
    <div>
      <div class="admin-toolbar"><select :value="name" @change="updateName"><option value="infobox">InfoBox</option><option value="badges">Бейджи</option><option value="groups">Группы</option></select><button class="button button--ghost" type="button" @click="emit('create')">Новая запись</button></div>
      <div class="admin-list"><div v-for="row in rows" :key="String(row[keyField])" class="admin-server-row"><button type="button" :class="{ active:originalKey===String(row[keyField]) }" @click="emit('edit',row)"><span>{{ String(row[keyField]??'?').slice(0,1).toUpperCase() }}</span><div><strong>{{ row[keyField] }}</strong><small>{{ Object.keys(row).length }} полей</small></div></button><button type="button" aria-label="Удалить запись" @click="emit('remove',row)">×</button></div></div>
    </div>
    <form class="admin-editor" @submit.prevent="emit('save')"><h2>{{ originalKey || 'Новая запись' }}</h2><label><span>JSON объекта</span><textarea :value="draft" rows="24" spellcheck="false" @input="updateDraft" /></label><button class="button button--primary" type="submit">Сохранить запись</button></form>
  </section>
</template>
