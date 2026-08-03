<script setup lang="ts">
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
  if (tool.id === 'overview') return `${props.hardware?.summary.totalSystems ?? 0} систем`
  if (tool.id === 'users') return `${props.overview.users} игроков`
  if (tool.id === 'servers') return `${props.overview.enabledServers}/${props.overview.servers} активны`
  if (tool.id === 'logs') return 'Live telemetry'
  if (tool.catalog === 'infobox') return 'Справочник'
  if (tool.catalog === 'badges') return 'Награды'
  if (tool.catalog === 'groups') return 'Роли и доступ'
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
        <span class="eyebrow">Группа инструментов</span>
        <h1>{{ category.label }}</h1>
        <p>{{ category.description }}</p>
      </div>
      <span class="admin-category-view__count">
        <strong>{{ category.tools.length }}</strong>
        <small>инструментов</small>
      </span>
    </header>

    <div class="admin-category-view__grid">
      <button
        v-for="tool in category.tools"
        :key="tool.id"
        type="button"
        class="admin-category-tool"
        :aria-label="`Открыть раздел «${tool.label}»`"
        @click="emit('select', tool.id)"
      >
        <span class="admin-category-tool__icon" aria-hidden="true">
          <i class="fa-solid" :class="tool.icon" />
        </span>
        <span class="admin-category-tool__copy">
          <small v-if="tool.parentLabel" class="admin-category-tool__parent">
            <i class="fa-solid" :class="tool.parentIcon" aria-hidden="true" />
            {{ tool.parentLabel }}
          </small>
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
      <i class="fa-solid fa-spinner" aria-hidden="true" />
      Загружается состояние группы
    </p>
  </section>
</template>
