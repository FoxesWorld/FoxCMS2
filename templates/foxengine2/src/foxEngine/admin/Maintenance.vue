<script setup lang="ts">
import { t } from '@/i18n'

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
        <span class="eyebrow">{{ t('theme.foxengine.admin.maintenance.001') }}</span>
        <h2>{{ t('theme.foxengine.admin.maintenance.002') }}</h2>
        <p>{{ t('theme.foxengine.admin.maintenance.003') }}</p>
      </div>
      <span class="maintenance-admin__status" :class="{ active: settings.enabled }">
        {{ settings.enabled ? t('theme.foxengine.admin.maintenance.004') : t('theme.foxengine.admin.maintenance.005') }}
      </span>
    </header>

    <div class="maintenance-admin__grid">
      <section class="maintenance-admin__panel">
        <UiCheckbox
          v-model="settings.enabled"
          class="maintenance-switch"
          variant="switch"
          :label="t('theme.foxengine.admin.maintenance.006')"
          :description="t('theme.foxengine.admin.maintenance.007')"
        />
        <label class="maintenance-field">
          <span>{{ t('theme.foxengine.admin.maintenance.008') }}</span>
          <input v-model="settings.title" maxlength="160" :placeholder="t('theme.foxengine.admin.maintenance.009')">
        </label>
        <label class="maintenance-field">
          <span>{{ t('theme.foxengine.admin.maintenance.010') }}</span>
          <textarea v-model="settings.message" maxlength="1200" rows="5" :placeholder="t('theme.foxengine.admin.maintenance.011')" />
          <small>{{ settings.message.length }}/1200</small>
        </label>
      </section>

      <section class="maintenance-admin__panel">
        <div class="maintenance-admin__panel-heading">
          <strong>{{ t('theme.foxengine.admin.maintenance.012') }}</strong>
          <span>{{ t('theme.foxengine.admin.maintenance.013') }}</span>
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
      <span v-if="settings.updatedAt">{{ t('theme.foxengine.admin.maintenance.014') }} {{ settings.updatedAt }}</span>
      <span v-else>{{ t('theme.foxengine.admin.maintenance.015') }}</span>
      <button type="button" class="button button--primary" :disabled="loading" @click="emit('save')">
        {{ loading ? t('theme.foxengine.admin.maintenance.016') : t('theme.foxengine.admin.maintenance.017') }}
      </button>
    </div>
  </section>
</template>
