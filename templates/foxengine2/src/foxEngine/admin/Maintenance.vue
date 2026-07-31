<script setup lang="ts">
import type { GroupOption, MaintenanceSettings } from '@modules/AdminPanel/client/useAdminPanel'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'

const stylesheet = themeAsset(appBootstrap, 'css/admin-maintenance.css')

const props = defineProps<{
  settings: MaintenanceSettings
  groups: GroupOption[]
  loading: boolean
}>()
const emit = defineEmits<{ save: [] }>()

function toggleGroup(group: number, checked: boolean): void {
  if (group === 1) return
  const values = new Set(props.settings.allowedGroups)
  checked ? values.add(group) : values.delete(group)
  values.add(1)
  props.settings.allowedGroups = [...values].sort((left, right) => left - right)
}
</script>

<template>
  <Teleport to="head"><link rel="stylesheet" :href="stylesheet"></Teleport>
  <section class="admin-section maintenance-admin">
    <header class="maintenance-admin__header">
      <div>
        <span class="eyebrow">Access control</span>
        <h2>Режим технических работ</h2>
        <p>Серверная заглушка блокирует сайт и API для групп без доступа. Администраторы допускаются всегда.</p>
      </div>
      <span class="maintenance-admin__status" :class="{ active: settings.enabled }">
        {{ settings.enabled ? 'Режим активен' : 'Сайт открыт' }}
      </span>
    </header>

    <div class="maintenance-admin__grid">
      <section class="maintenance-admin__panel">
        <label class="maintenance-switch">
          <input v-model="settings.enabled" type="checkbox">
          <span><strong>Включить заглушку</strong><small>Изменение применяется сразу после сохранения.</small></span>
        </label>

        <label class="maintenance-field">
          <span>Заголовок</span>
          <input v-model="settings.title" maxlength="160" placeholder="Ведутся технические работы">
        </label>

        <label class="maintenance-field">
          <span>Сообщение</span>
          <textarea v-model="settings.message" maxlength="1200" rows="5" placeholder="Опишите причину и ожидаемый срок работ." />
          <small>{{ settings.message.length }}/1200</small>
        </label>
      </section>

      <section class="maintenance-admin__panel">
        <div class="maintenance-admin__panel-heading">
          <strong>Группы с доступом</strong>
          <span>Гости относятся к группе 5.</span>
        </div>
        <div class="maintenance-groups">
          <label v-for="group in groups" :key="group.groupNum" class="maintenance-group">
            <input
              type="checkbox"
              :checked="group.groupNum === 1 || settings.allowedGroups.includes(group.groupNum)"
              :disabled="group.groupNum === 1"
              @change="toggleGroup(group.groupNum, ($event.target as HTMLInputElement).checked)"
            >
            <i :style="{ background: group.groupColor || '#888' }" />
            <span><strong>{{ group.groupName }}</strong><small>Группа {{ group.groupNum }} · {{ group.groupType }}</small></span>
          </label>
        </div>
      </section>
    </div>

    <div class="maintenance-admin__footer">
      <span v-if="settings.updatedAt">Последнее изменение: {{ settings.updatedAt }}</span>
      <span v-else>Настройки ещё не сохранялись.</span>
      <button type="button" class="button button--primary" :disabled="loading" @click="emit('save')">
        {{ loading ? 'Сохранение…' : 'Сохранить режим' }}
      </button>
    </div>
  </section>
</template>
