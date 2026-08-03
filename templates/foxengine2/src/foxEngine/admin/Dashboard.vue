<script setup lang="ts">
import type {
  AdminCategoryDefinition,
  AdminCategoryId,
  AdminToolDefinition,
  Hardware,
  Overview,
  AdminToolId,
} from '@modules/AdminPanel/client/useAdminPanel'

type CategoryWithTools = AdminCategoryDefinition & { tools: AdminToolDefinition[] }

const props = defineProps<{
  categories: CategoryWithTools[]
  overview: Overview | null
  hardware: Hardware | null
  loading: boolean
}>()

const emit = defineEmits<{ select: [tool: AdminToolId]; selectCategory: [category: AdminCategoryId] }>()

function toolMetric(tab: AdminToolId): string {
  if (!props.overview) return ''
  if (tab === 'overview') return `${props.hardware?.summary.totalSystems ?? 0} систем`
  if (tab === 'users') return `${props.overview.users} игроков`
  if (tab === 'servers') return `${props.overview.enabledServers}/${props.overview.servers} активно`
  if (tab === 'logs') return 'Live telemetry'
  return ''
}
</script>

<template>
  <section class="admin-dashboard" aria-labelledby="admin-dashboard-title">
    <header class="admin-dashboard__hero">
      <div>
        <span class="eyebrow">Control center</span>
        <h1 id="admin-dashboard-title">Центр управления FoxesCraft</h1>
        <p>
          Инструменты сгруппированы по назначению. Выберите карточку, чтобы перейти к рабочему интерфейсу.
        </p>
      </div>
      <div class="admin-dashboard__summary" aria-label="Краткая сводка проекта">
        <article>
          <span>Игроки</span>
          <strong>{{ overview?.users ?? '—' }}</strong>
          <small>{{ overview?.recentUsers ?? '—' }} активны за 24 часа</small>
        </article>
        <article>
          <span>Серверы</span>
          <strong>{{ overview ? `${overview.enabledServers}/${overview.servers}` : '—' }}</strong>
          <small>активно / всего</small>
        </article>
        <article>
          <span>Системы</span>
          <strong>{{ hardware?.summary.totalSystems ?? overview?.hardwareReports ?? '—' }}</strong>
          <small>аппаратных профилей</small>
        </article>
      </div>
    </header>

    <div class="admin-dashboard__groups">
      <section
        v-for="category in categories"
        :key="category.id"
        class="admin-dashboard-group"
        :data-category="category.id"
      >
        <button
          type="button"
          class="admin-dashboard-group__header"
          :aria-label="`Открыть группу «${category.label}»`"
          @click="emit('selectCategory', category.id)"
        >
          <span class="admin-dashboard-group__icon" aria-hidden="true">
            <i class="fa-solid" :class="category.icon" />
          </span>
          <span class="admin-dashboard-group__copy">
            <strong>{{ category.label }}</strong>
            <small>{{ category.description }}</small>
          </span>
          <span class="admin-dashboard-group__count">{{ category.tools.length }}</span>
          <i class="fa-solid fa-arrow-right admin-dashboard-group__arrow" aria-hidden="true" />
        </button>

        <div class="admin-dashboard-grid">
          <button
            v-for="tool in category.tools"
            :key="tool.id"
            type="button"
            class="admin-dashboard-card"
            :aria-label="`Открыть раздел «${tool.label}»`"
            @click="emit('select', tool.id)"
          >
            <span class="admin-dashboard-card__icon" aria-hidden="true">
              <i class="fa-solid" :class="tool.icon" />
            </span>
            <span class="admin-dashboard-card__copy">
              <small v-if="tool.parentLabel" class="admin-dashboard-card__parent">
                <i class="fa-solid" :class="tool.parentIcon" aria-hidden="true" />
                {{ tool.parentLabel }}
              </small>
              <span class="admin-dashboard-card__title">
                <strong>{{ tool.label }}</strong>
                <small v-if="toolMetric(tool.id)">{{ toolMetric(tool.id) }}</small>
              </span>
              <span>{{ tool.description }}</span>
            </span>
            <i class="fa-solid fa-arrow-right admin-dashboard-card__arrow" aria-hidden="true" />
          </button>
        </div>
      </section>
    </div>

    <p v-if="loading" class="admin-dashboard__loading" role="status">
      <i class="fa-solid fa-spinner" aria-hidden="true" />
      Обновляется сводка проекта…
    </p>
  </section>
</template>
