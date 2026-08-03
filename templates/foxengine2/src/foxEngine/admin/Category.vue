<script setup lang="ts">
import { t } from '@/i18n'

import type {
  AdminCategoryDefinition,
  AdminToolDefinition,
  AdminToolId,
  Hardware,
  Overview,
} from '@modules/AdminPanel/client/useAdminPanel'

type CategoryWithTools = AdminCategoryDefinition & { tools: AdminToolDefinition[] }

const props = defineProps<{
  category: CategoryWithTools
  overview: Overview | null
  hardware: Hardware | null
  loading: boolean
}>()

const emit = defineEmits<{ select: [tool: AdminToolId] }>()

function toolMetric(tool: AdminToolDefinition): string {
  if (!props.overview) return ''
  if (tool.id === 'overview') return t('theme.foxengine.admin.category.005', [props.hardware?.summary.totalSystems ?? 0])
  if (tool.id === 'users') return t('theme.foxengine.admin.category.006', [props.overview.users])
  if (tool.id === 'servers') return t('theme.foxengine.admin.category.007', [props.overview.enabledServers, props.overview.servers])
  if (tool.id === 'logs') return 'Live telemetry'
  if (tool.catalog === 'infobox') return t('theme.foxengine.admin.category.008')
  if (tool.catalog === 'badges') return t('theme.foxengine.admin.category.009')
  if (tool.catalog === 'groups') return t('theme.foxengine.admin.category.010')
  if (tool.id === 'rewards') return t('theme.foxengine.admin.category.011')
  return ''
}
</script>

<template>
  <section class="admin-category-view" :data-category="category.id">
    <header class="admin-category-view__hero">
      <span class="admin-category-view__icon" aria-hidden="true">
        <i class="fa-solid" :class="category.icon" />
      </span>
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.category.001') }}</span>
        <h1>{{ category.label }}</h1>
        <p>{{ category.description }}</p>
      </div>
      <span class="admin-category-view__count">
        <strong>{{ category.tools.length }}</strong>
        <small>{{ t('theme.foxengine.admin.category.002') }}</small>
      </span>
    </header>

    <div class="admin-category-view__grid">
      <button
        v-for="tool in category.tools"
        :key="tool.id"
        type="button"
        class="admin-category-tool"
        :aria-label="t('theme.foxengine.admin.category.003', [tool.label])"
        @click="emit('select', tool.id)"
      >
        <span class="admin-category-tool__icon" aria-hidden="true">
          <i class="fa-solid" :class="tool.icon" />
        </span>
        <span class="admin-category-tool__copy">
          <span class="admin-category-tool__title">
            <strong>{{ tool.label }}</strong>
            <small v-if="toolMetric(tool)">{{ toolMetric(tool) }}</small>
          </span>
          <span>{{ tool.description }}</span>
        </span>
        <i class="fa-solid fa-arrow-right admin-category-tool__arrow" aria-hidden="true" />
      </button>
    </div>

    <p v-if="loading" class="admin-dashboard__loading" role="status">
      <i class="fa-solid fa-spinner" aria-hidden="true" /> {{ t('theme.foxengine.admin.category.004') }} </p>
  </section>
</template>
