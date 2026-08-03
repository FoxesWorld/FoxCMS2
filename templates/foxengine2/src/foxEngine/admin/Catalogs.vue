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
const fieldControls = computed<JsonFieldControls>(() => props.name === 'groups' ? { groupColor: 'color' } : {})
const emit = defineEmits<{
  'update:draft': [value: JsonObject]
  create: []
  edit: [row: JsonRow]
  remove: [row: JsonRow]
  save: []
}>()
function updateDraft(value: JsonValue): void { emit('update:draft', value as JsonObject) }
function updateBadgeField(field: string, value: string | number): void {
  emit('update:draft', { ...props.draft, [field]: value })
}
function stringField(field: string): string { return String(props.draft[field] ?? '') }
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
            <img v-if="name === 'badges' && row.img" :src="String(row.img)" alt="">
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

      <div v-if="name === 'badges'" class="admin-badge-catalog-form">
        <label><span>Название бейджа</span><input :value="stringField('badgeName')" type="text" maxlength="120" required @input="updateBadgeField('badgeName', ($event.target as HTMLInputElement).value)"></label>
        <label><span>Описание</span><textarea :value="stringField('description')" maxlength="4000" rows="5" @input="updateBadgeField('description', ($event.target as HTMLTextAreaElement).value)" /></label>
        <label><span>Изображение</span><input :value="stringField('img')" type="text" maxlength="1024" placeholder="/uploads/badges/example.png" @input="updateBadgeField('img', ($event.target as HTMLInputElement).value)"></label>
      </div>

      <JsonFormEditor v-else :model-value="draft" :samples="rows" label="Поля записи" root-kind="object" :field-controls="fieldControls" @update:model-value="updateDraft" />
      <button class="button button--primary" type="submit">Сохранить запись</button>
    </form>
  </section>
</template>
