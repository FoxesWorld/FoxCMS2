<script setup lang="ts">
import type { ServerRow } from '@modules/AdminPanel/client/useAdminPanel'
import ServerTable from './servers/ServerTable.vue'
import ServerEditor from './servers/ServerEditor.vue'
defineProps<{ servers:ServerRow[]; selected:ServerRow|null; draft:ServerRow }>()
const emit=defineEmits<{ create:[]; edit:[server:ServerRow]; remove:[server:ServerRow]; save:[] }>()
</script>
<template>
  <section class="admin-section admin-split">
    <ServerTable :servers="servers" :selected="selected" @create="emit('create')" @edit="emit('edit',$event)" @remove="emit('remove',$event)" />
    <ServerEditor :selected="selected" :draft="draft" @save="emit('save')" />
  </section>
</template>
