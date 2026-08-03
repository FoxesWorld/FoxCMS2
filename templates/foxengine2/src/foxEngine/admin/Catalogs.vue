<script setup lang="ts">
import { computed } from 'vue'
import { JsonFormEditor } from '@/forms/json-form'
import type { JsonFieldControls, JsonObject, JsonValue } from '@/forms/json-form'
import type { CatalogName, JsonRow } from '@modules/AdminPanel/client/useAdminPanel'


const props = defineProps<{
  name: CatalogName
  rows: JsonRow[]
  keyField: string
  originalKey: string
  draft: JsonObject
}>()

const fieldControls = computed<JsonFieldControls>(() => props.name === 'groups'
  ? { groupColor: 'color' }
  : {})

const emit = defineEmits<{
  'update:draft': [value: JsonObject]
  create: []
  edit: [row: JsonRow]
  remove: [row: JsonRow]
  save: []
}>()


function updateDraft(value: JsonValue): void {
  emit('update:draft', value as JsonObject)
}

</script>

<template>
  <section class="admin-section admin-split">
    <div>
      <div class="admin-toolbar admin-catalog-toolbar">
        <span class="admin-catalog-toolbar__context">
          <i class="fa-solid" :class="name === 'infobox' ? 'fa-circle-info' : name === 'badges' ? 'fa-award' : 'fa-user-group'" aria-hidden="true" />
          <strong>{{ name === 'infobox' ? 'InfoBox' : name === 'badges' ? 'Бейджи' : 'Группы' }}</strong>
        </span>
        <button class="button button--ghost" type="button" @click="emit('create')">Новая запись</button>
      </div>

      <div class="admin-list">
        <div v-for="row in rows" :key="String(row[keyField])" class="admin-server-row">
          <button type="button" :class="{ active: originalKey === String(row[keyField]) }" @click="emit('edit', row)">
            <img
              v-if="name === 'badges' && row.img"
             
              :src="String(row.img)"
              alt=""
            >
            <span v-else>{{ String(row[keyField] ?? '?').slice(0, 1).toUpperCase() }}</span>
            <div>
              <strong>{{ row[keyField] }}</strong>
              <small>{{ Object.keys(row).length }} полей</small>
            </div>
          </button>
          <button type="button" aria-label="Удалить запись" @click="emit('remove', row)">×</button>
        </div>
      </div>
    </div>

    <form class="admin-editor" @submit.prevent="emit('save')">
      <h2>{{ originalKey || 'Новая запись' }}</h2>


      <JsonFormEditor
        :model-value="draft"
        :samples="rows"
        label="Поля записи"
        root-kind="object"
        :field-controls="fieldControls"
        @update:model-value="updateDraft"
      />
      <button class="button button--primary" type="submit">Сохранить запись</button>
    </form>
  </section>
</template>


