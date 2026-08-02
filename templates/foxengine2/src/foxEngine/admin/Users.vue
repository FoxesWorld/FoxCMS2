<script setup lang="ts">
import type { AdminBadgeOption, GroupOption, UserDraft, UserRow } from '@modules/AdminPanel/client/useAdminPanel'
import UserTable from './users/UserTable.vue'
import UserEditor from './users/UserEditor.vue'

defineProps<{
  users: UserRow[]
  groups: GroupOption[]
  badgeOptions: AdminBadgeOption[]
  search: string
  selected: UserRow | null
  draft: UserDraft
  formatTimestamp: (value?: number | string) => string
  loading: boolean
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  search: []
  edit: [user: UserRow]
  save: []
}>()
</script>

<template>
  <section class="admin-section admin-users">
    <UserTable
      :users="users"
      :search="search"
      :selected="selected"
      :format-timestamp="formatTimestamp"
      :loading="loading"
      @update:search="emit('update:search', $event)"
      @search="emit('search')"
      @edit="emit('edit', $event)"
    />
    <UserEditor
      :selected="selected"
      :draft="draft"
      :groups="groups"
      :badge-options="badgeOptions"
      :samples="users"
      :loading="loading"
      @save="emit('save')"
    />
  </section>
</template>
