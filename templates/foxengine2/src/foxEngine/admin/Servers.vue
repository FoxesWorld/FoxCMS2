<script setup lang="ts">
import type { GameVersionCatalogStatus, GameVersionOption, GroupOption, JdkCatalogStatus, JdkRuntimeOption, ServerDraft, ServerRow } from '@modules/AdminPanel/client/useAdminPanel'
import ServerTable from './servers/ServerTable.vue'
import ServerEditor from './servers/ServerEditor.vue'

defineProps<{
  servers: ServerRow[]
  selected: ServerRow | null
  draft: ServerDraft
  groups: GroupOption[]
  jdkOptions: JdkRuntimeOption[]
  jdkCatalog: JdkCatalogStatus
  gameVersionOptions: GameVersionOption[]
  gameVersionCatalog: GameVersionCatalogStatus
  loading: boolean
  imageUploading: boolean
  imageError: string
}>()

const emit = defineEmits<{
  create: []
  edit: [server: ServerRow]
  remove: [server: ServerRow]
  uploadImage: [file: File]
  clearImage: []
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
      :jdk-options="jdkOptions"
      :jdk-catalog="jdkCatalog"
      :game-version-options="gameVersionOptions"
      :game-version-catalog="gameVersionCatalog"
      :loading="loading"
      :image-uploading="imageUploading"
      :image-error="imageError"
      @upload-image="emit('uploadImage', $event)"
      @clear-image="emit('clearImage')"
      @remove="emit('remove', $event)"
      @save="emit('save')"
    />
  </section>
</template>
