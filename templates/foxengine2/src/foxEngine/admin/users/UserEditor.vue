<script setup lang="ts">
import { JsonFormEditor, collectJsonSamples } from '@/forms/json-form'
import type { JsonValue } from '@/forms/json-form'
import type { GroupOption, UserDraft, UserRow } from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  selected: UserRow | null
  draft: UserDraft
  groups: GroupOption[]
  badgeOptions: string[]
  samples: UserRow[]
}>()

const emit = defineEmits<{ save: [] }>()

type StructuredUserField = 'balance' | 'badges' | 'serversOnline'

function samplesFor(field: StructuredUserField): JsonValue[] {
  return collectJsonSamples(props.samples, field)
}


</script>

<template>
  <form v-if="selected" class="admin-editor" @submit.prevent="emit('save')">
    <h2>{{ selected.login }}</h2>
    <label><span>UUID</span><input :value="selected.uuid" type="text" readonly></label>
    <label><span>Логин</span><input v-model="draft.login" type="text" minlength="3" maxlength="64" required></label>
    <label><span>Имя</span><input v-model="draft.realname" type="text"></label>
    <label><span>Email</span><input v-model="draft.email" type="email" required></label>
    <label><span>Статус</span><input v-model="draft.userStatus" type="text"></label>
    <label>
      <span>Группа</span>
      <select v-model="draft.groupTag" required>
        <option v-for="group in groups" :key="group.groupTag" :value="group.groupTag">
          {{ group.groupName }} — {{ group.groupTag }}
        </option>
      </select>
    </label>

    <JsonFormEditor
      :model-value="draft.balance"
      :samples="samplesFor('balance')"
      label="Баланс"
      @update:model-value="draft.balance = $event"
    />
    <JsonFormEditor
      :model-value="draft.badges"
      :samples="samplesFor('badges')"
      label="Бейджи"
      root-kind="array"
      :field-options="{ badgeName: badgeOptions }"
      @update:model-value="draft.badges = $event"
    />
    <JsonFormEditor
      :model-value="draft.serversOnline"
      :samples="samplesFor('serversOnline')"
      label="Игровая активность"
      @update:model-value="draft.serversOnline = $event"
    />

    <button class="button button--primary" type="submit">Сохранить пользователя</button>
  </form>
  <div v-else class="empty-state">Выберите пользователя для редактирования.</div>
</template>
