<script setup lang="ts">
import UiCheckbox from '@/components/UiCheckbox.vue'
import { JsonFormEditor, collectJsonSamples } from '@/forms/json-form'
import type { JsonValue } from '@/forms/json-form'
import type { GroupOption, ServerDraft, ServerRow } from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  selected: ServerRow | null
  draft: ServerDraft
  groups: GroupOption[]
  samples: ServerRow[]
}>()

const emit = defineEmits<{ save: [] }>()

type StructuredServerField = 'ignoreDirs' | 'modsInfo'

function samplesFor(field: StructuredServerField): JsonValue[] {
  return collectJsonSamples(props.samples, field)
}
</script>

<template>
  <form class="admin-editor" @submit.prevent="emit('save')">
    <h2>{{ selected ? `Редактирование ${selected.serverName}` : 'Новый сервер' }}</h2>
    <label><span>Имя</span><input v-model="draft.serverName" type="text" required></label>
    <label><span>Host</span><input v-model="draft.host" type="text"></label>
    <label><span>Port</span><input v-model.number="draft.port" type="number" min="1" max="65535"></label>
    <div class="admin-checks">
      <UiCheckbox
        v-model="draft.enabled"
        variant="switch"
        label="Сервер включён"
        description="Разрешить отображение и подключение"
      />
      <UiCheckbox
        v-model="draft.checkLib"
        variant="switch"
        label="Проверять библиотеки"
        description="Проверять клиентские зависимости перед запуском"
      />
    </div>
    <label><span>Версия сервера</span><input v-model="draft.serverVersion" type="text"></label>
    <label><span>Java runtime</span><input v-model="draft.jreVersion" type="text"></label>
    <label><span>Изображение</span><input v-model="draft.serverImage" type="text"></label>
    <label><span>Описание</span><textarea v-model="draft.serverDescription" rows="4" /></label>

    <JsonFormEditor
      :model-value="draft.ignoreDirs"
      :samples="samplesFor('ignoreDirs')"
      label="Игнорируемые каталоги"
      root-kind="array"
      @update:model-value="draft.ignoreDirs = $event"
    />

    <label>
      <span>Группы доступа</span>
      <select v-model="draft.serverGroups" multiple :size="Math.min(Math.max(groups.length, 3), 8)">
        <option v-for="group in groups" :key="group.groupTag" :value="group.groupTag">
          {{ group.groupName }} — {{ group.groupTag }}
        </option>
      </select>
      <small>Можно выбрать несколько групп.</small>
    </label>

    <JsonFormEditor
      :model-value="draft.modsInfo"
      :samples="samplesFor('modsInfo')"
      label="Информация о модах"
      root-kind="array"
      @update:model-value="draft.modsInfo = $event"
    />

    <button class="button button--primary" type="submit">Сохранить сервер</button>
  </form>
</template>
