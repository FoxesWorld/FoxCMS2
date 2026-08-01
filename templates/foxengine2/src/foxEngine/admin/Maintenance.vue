<script setup lang="ts">
import UiCheckbox from '@/components/UiCheckbox.vue'
import type { GroupOption, MaintenanceSettings } from '@modules/AdminPanel/client/useAdminPanel'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'

const stylesheet = themeAsset(appBootstrap, 'css/admin-maintenance.css')
const props = defineProps<{ settings: MaintenanceSettings; groups: GroupOption[]; loading: boolean }>()
const emit = defineEmits<{ save: [] }>()

function toggleGroup(groupTag: string, checked: boolean): void {
  if (groupTag === 'admin') return
  const values = new Set(props.settings.allowedGroups)
  checked ? values.add(groupTag) : values.delete(groupTag)
  values.add('admin')
  props.settings.allowedGroups = [...values].sort()
}
</script>

<template>
  <Teleport to="head"><link rel="stylesheet" :href="stylesheet"></Teleport>
  <section class="admin-section maintenance-admin">
    <header class="maintenance-admin__header">
      <div>
        <span class="eyebrow">Access control</span>
        <h2>Режим технических работ</h2>
        <p>Серверная заглушка блокирует сайт и API для групп без доступа. Администраторы и отмеченные ниже группы могут войти через форму на заглушке.</p>
      </div>
      <span class="maintenance-admin__status" :class="{ active: settings.enabled }">
        {{ settings.enabled ? 'Режим активен' : 'Сайт открыт' }}
      </span>
    </header>

    <div class="maintenance-admin__grid">
      <section class="maintenance-admin__panel">
        <UiCheckbox
          v-model="settings.enabled"
          class="maintenance-switch"
          variant="switch"
          label="Включить заглушку"
          description="Изменение применяется сразу после сохранения."
        />
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
          <span>Отмеченные группы смогут авторизоваться прямо на странице технических работ. Гостевой доступ определяется тегом guest.</span>
        </div>
        <div class="maintenance-groups">
          <UiCheckbox
            v-for="group in groups"
            :key="group.groupTag"
            class="maintenance-group"
            :model-value="group.groupTag === 'admin' || settings.allowedGroups.includes(group.groupTag)"
            :disabled="group.groupTag === 'admin'"
            @update:model-value="toggleGroup(group.groupTag, $event)"
          >
            <i class="maintenance-group__dot" :style="{ background: group.groupColor || '#888' }" />
            <span class="maintenance-group__copy"><strong>{{ group.groupName }}</strong><small>{{ group.groupTag }}</small></span>
          </UiCheckbox>
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
