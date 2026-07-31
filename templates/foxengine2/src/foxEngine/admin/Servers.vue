<script setup lang="ts">
import type { GroupOption, ServerDraft, ServerRow } from '@modules/AdminPanel/client/useAdminPanel'
import ServerTable from './servers/ServerTable.vue'
import ServerEditor from './servers/ServerEditor.vue'

defineProps<{
  servers: ServerRow[]
  selected: ServerRow | null
  draft: ServerDraft
  groups: GroupOption[]
}>()

const emit = defineEmits<{
  create: []
  edit: [server: ServerRow]
  remove: [server: ServerRow]
  save: []
}>()
</script>

<template>
  <section class="admin-section admin-split">
    <ServerTable
      :servers="servers"
      :selected="selected"
      @create="emit('create')"
      @edit="emit('edit', $event)"
      @remove="emit('remove', $event)"
    />
    <ServerEditor
      :selected="selected"
      :draft="draft"
      :groups="groups"
      :samples="servers"
      @save="emit('save')"
    />
  </section>
</template>
