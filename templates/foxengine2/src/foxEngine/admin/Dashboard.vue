<script setup lang="ts">
import { t } from '@/i18n'

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
  if (tab === 'overview') return t('theme.foxengine.admin.dashboard.015', [props.hardware?.summary.totalSystems ?? 0])
  if (tab === 'users') return t('theme.foxengine.admin.dashboard.016', [props.overview.users])
  if (tab === 'servers') return t('theme.foxengine.admin.dashboard.017', [props.overview.enabledServers, props.overview.servers])
  if (tab === 'logs') return t('theme.foxengine.admin.dashboard.018')
  return ''
}
</script>

<template>
  <section class="admin-dashboard" aria-labelledby="admin-dashboard-title">
    <header class="admin-dashboard__hero">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.dashboard.001') }}</span>
        <h1 id="admin-dashboard-title">{{ t('theme.foxengine.admin.dashboard.002') }}</h1>
        <p> {{ t('theme.foxengine.admin.dashboard.003') }} </p>
      </div>
      <div class="admin-dashboard__summary" :aria-label="t('theme.foxengine.admin.dashboard.004')">
        <article>
          <span>{{ t('theme.foxengine.admin.dashboard.005') }}</span>
          <strong>{{ overview?.users ?? '—' }}</strong>
          <small>{{ overview?.recentUsers ?? '—' }} {{ t('theme.foxengine.admin.dashboard.006') }}</small>
        </article>
        <article>
          <span>{{ t('theme.foxengine.admin.dashboard.007') }}</span>
          <strong>{{ overview ? t('theme.foxengine.admin.dashboard.008', [overview.enabledServers, overview.servers]) : '—' }}</strong>
          <small>{{ t('theme.foxengine.admin.dashboard.009') }}</small>
        </article>
        <article>
          <span>{{ t('theme.foxengine.admin.dashboard.010') }}</span>
          <strong>{{ hardware?.summary.totalSystems ?? overview?.hardwareReports ?? '—' }}</strong>
          <small>{{ t('theme.foxengine.admin.dashboard.011') }}</small>
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
          :aria-label="t('theme.foxengine.admin.dashboard.012', [category.label])"
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
            :aria-label="t('theme.foxengine.admin.dashboard.013', [tool.label])"
            @click="emit('select', tool.id)"
          >
            <span class="admin-dashboard-card__icon" aria-hidden="true">
              <i class="fa-solid" :class="tool.icon" />
            </span>
            <span class="admin-dashboard-card__copy">
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
      <i class="fa-solid fa-spinner" aria-hidden="true" /> {{ t('theme.foxengine.admin.dashboard.014') }} </p>
  </section>
</template>
