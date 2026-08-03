<script setup lang="ts">
import { computed } from 'vue'
import type {
  Hardware,
  HardwareDistribution,
  HardwareSystem,
  Overview,
} from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  overview: Overview | null
  hardware: Hardware | null
}>()

const distributionSections = computed(() => {
  const hardware = props.hardware
  if (!hardware) return []
  return [
    { key: 'platforms', title: 'Платформы', icon: 'fa-server', items: hardware.platforms },
    { key: 'operating-systems', title: 'Операционные системы', icon: 'fa-code', items: hardware.operatingSystems },
    { key: 'architectures', title: 'Архитектуры', icon: 'fa-code', items: hardware.architectures },
    { key: 'memory', title: 'Оперативная память', icon: 'fa-cube', items: hardware.memoryBuckets },
    { key: 'cpu-vendors', title: 'Производители CPU', icon: 'fa-chart-line', items: hardware.cpuVendors },
    { key: 'gpu-vendors', title: 'Производители GPU', icon: 'fa-image', items: hardware.gpuVendors },
    { key: 'updater-versions', title: 'Версии UpdaterNorth', icon: 'fa-code-branch', items: hardware.updaterVersions },
  ]
})

function formatBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return 'Не определено'
  const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB']
  const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const value = bytes / 1024 ** exponent
  return `${new Intl.NumberFormat('ru', { maximumFractionDigits: value >= 100 ? 0 : 1 }).format(value)} ${units[exponent]}`
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('ru', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function barWidth(items: HardwareDistribution[], count: number): string {
  const maximum = Math.max(1, ...items.map((item) => item.count))
  return `${Math.max(3, Math.min(100, (count / maximum) * 100))}%`
}

function systemOs(system: HardwareSystem): string {
  const version = system.osVersion?.trim()
  return version || system.osName || '—'
}

function gpuLabel(system: HardwareSystem): string {
  return system.gpuAdapters.length > 0 ? system.gpuAdapters.join(', ') : 'Не определён'
}
</script>

<template>
  <section class="admin-section admin-overview">
    <div v-if="overview" class="admin-metrics">
      <div><span>Пользователи</span><strong>{{ overview.users }}</strong></div>
      <div><span>Активны 24 часа</span><strong>{{ overview.recentUsers }}</strong></div>
      <div><span>Серверы</span><strong>{{ overview.enabledServers }}/{{ overview.servers }}</strong></div>
      <div><span>Зарегистрированные системы</span><strong>{{ overview.hardwareReports }}</strong></div>
    </div>

    <template v-if="hardware && hardware.summary.totalSystems > 0">
      <section class="hardware-summary" aria-label="Сводка по оборудованию">
        <article>
          <i class="fa-solid fa-server" aria-hidden="true" />
          <span>Уникальные системы</span>
          <strong>{{ hardware.summary.totalSystems }}</strong>
          <small>по systemHWID</small>
        </article>
        <article>
          <i class="fa-solid fa-cube" aria-hidden="true" />
          <span>Средний объём RAM</span>
          <strong>{{ formatBytes(hardware.summary.averageMemoryBytes) }}</strong>
          <small>суммарно {{ formatBytes(hardware.summary.totalMemoryBytes) }}</small>
        </article>
        <article>
          <i class="fa-solid fa-code" aria-hidden="true" />
          <span>Среднее число потоков</span>
          <strong>{{ hardware.summary.averageLogicalCpuCount }}</strong>
          <small>логических CPU</small>
        </article>
        <article>
          <i class="fa-solid fa-clock" aria-hidden="true" />
          <span>Последняя регистрация</span>
          <strong class="hardware-summary__date">{{ formatDate(hardware.summary.lastSeenAt) }}</strong>
          <small>первая: {{ formatDate(hardware.summary.firstSeenAt) }}</small>
        </article>
      </section>

      <div class="hardware-stat-grid">
        <section
          v-for="section in distributionSections"
          :key="section.key"
          class="hardware-card"
        >
          <header>
            <i class="fa-solid" :class="section.icon" aria-hidden="true" />
            <h2>{{ section.title }}</h2>
          </header>
          <div v-if="section.items.length > 0" class="hardware-bars">
            <div v-for="item in section.items" :key="item.label" class="hardware-bar">
              <div class="hardware-bar__label">
                <span :title="item.label">{{ item.label }}</span>
                <strong>{{ item.count }}</strong>
              </div>
              <div class="hardware-bar__track" aria-hidden="true">
                <i :style="{ width: barWidth(section.items, item.count) }" />
              </div>
              <small>{{ item.percentage }}%</small>
            </div>
          </div>
          <p v-else class="hardware-empty">Данных пока нет.</p>
        </section>
      </div>

      <div class="hardware-model-grid">
        <section class="hardware-card hardware-card--models">
          <header>
            <i class="fa-solid fa-code" aria-hidden="true" />
            <h2>Модели CPU</h2>
          </header>
          <ol>
            <li v-for="model in hardware.cpuModels" :key="model.label">
              <span :title="model.label">{{ model.label }}</span>
              <strong>{{ model.count }}</strong>
            </li>
          </ol>
        </section>
        <section class="hardware-card hardware-card--models">
          <header>
            <i class="fa-solid fa-image" aria-hidden="true" />
            <h2>Модели GPU</h2>
          </header>
          <ol>
            <li v-for="model in hardware.gpuModels" :key="model.label">
              <span :title="model.label">{{ model.label }}</span>
              <strong>{{ model.count }}</strong>
            </li>
          </ol>
        </section>
      </div>

      <section class="hardware-systems">
        <header class="hardware-systems__header">
          <div>
            <span class="eyebrow">Inventory</span>
            <h2>Последние зарегистрированные системы</h2>
          </div>
          <small>Показываются последние {{ hardware.systems.length }} записей. Полный systemHWID скрыт.</small>
        </header>
        <div class="hardware-table-wrap">
          <table class="hardware-table">
            <thead>
              <tr>
                <th>System ID</th>
                <th>ОС / платформа</th>
                <th>CPU</th>
                <th>GPU</th>
                <th>RAM</th>
                <th>Updater</th>
                <th>Добавлена</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="system in hardware.systems" :key="system.systemId">
                <td><code>{{ system.systemId }}</code></td>
                <td>
                  <strong>{{ systemOs(system) }}</strong>
                  <small>{{ system.platform }} · {{ system.architecture }}</small>
                </td>
                <td>
                  <strong :title="system.cpuBrand || 'Не определён'">{{ system.cpuBrand || 'Не определён' }}</strong>
                  <small>{{ system.logicalCpuCount }} логических потоков</small>
                </td>
                <td><span class="hardware-table__gpu" :title="gpuLabel(system)">{{ gpuLabel(system) }}</span></td>
                <td>{{ formatBytes(system.memoryBytes) }}</td>
                <td>
                  <strong>{{ system.updaterVersion }}</strong>
                  <small>schema {{ system.schemaVersion }}</small>
                </td>
                <td>{{ formatDate(system.firstSeenAt) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <section v-else-if="hardware" class="hardware-empty-state">
      <i class="fa-solid fa-server" aria-hidden="true" />
      <div>
        <h2>Статистика оборудования пока пуста</h2>
        <p>Данные появятся после первого POST-запроса UpdaterNorth с новым hardware report.</p>
      </div>
    </section>
  </section>
</template>
