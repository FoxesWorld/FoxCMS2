<script setup lang="ts">
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import { useServerMonitor } from '@engine/shell/useServerMonitor'
import ServerEntry from './ServerEntry.vue'
import TotalOnline from './TotalOnline.vue'

const icon = themeAsset(appBootstrap, 'icons/monitor.png')
const { servers, total, emptyMessage, loading, error, openServer } = useServerMonitor()
</script>

<template>
  <section
    class="sidebar-card legacy-sidebar-card monitoring-card"
    :class="{ 'monitoring-card--refreshing': loading && servers.length > 0 }"
  >
    <div class="sidebar-card__heading legacy-card-title monitoring-card__heading">
      <img :src="icon" alt="" aria-hidden="true">
      <div>
        <strong>Мониторинг</strong>
        <small>Состояние игровых миров</small>
      </div>
      <span class="monitoring-card__live" :class="{ 'monitoring-card__live--loading': loading }">
        <i aria-hidden="true" />
        {{ loading ? 'Обновление' : 'Live' }}
      </span>
    </div>

    <div class="server-monitor">
      <div v-if="loading && !servers.length" class="monitoring-state" role="status">
        <span class="monitoring-state__spinner" aria-hidden="true" />
        <span>
          <strong>Получаем состояние серверов</strong>
          <small>Проверяем доступность и текущий онлайн…</small>
        </span>
      </div>

      <div v-else-if="error && !servers.length" class="monitoring-state monitoring-state--error" role="alert">
        <span class="monitoring-state__mark" aria-hidden="true">!</span>
        <span>
          <strong>Мониторинг временно недоступен</strong>
          <small>Не удалось получить актуальное состояние серверов.</small>
        </span>
      </div>

      <template v-else>
        <div v-if="servers.length" class="monitoring-card__servers">
          <ServerEntry
            v-for="server in servers"
            :key="server.serverName"
            :server="server"
            @open="openServer"
          />
        </div>

        <div v-else class="monitoring-state">
          <span class="monitoring-state__mark" aria-hidden="true">—</span>
          <span>
            <strong>Нет доступных серверов</strong>
            <small>{{ emptyMessage }}</small>
          </span>
        </div>

        <TotalOnline
          v-if="servers.length"
          :online="total.online"
          :max="total.max"
        />

        <div v-if="error && servers.length" class="monitoring-card__warning" role="status">
          <span aria-hidden="true">!</span>
          Показаны последние полученные данные.
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.monitoring-card {
  position: relative;
  overflow: hidden;
}

.monitoring-card::before {
  position: absolute;
  inset: 0 0 auto;
  height: 2px;
  content: '';
  background: linear-gradient(90deg, transparent, var(--color-accent), transparent);
  opacity: .58;
}

.monitoring-card__heading {
  grid-template-columns: auto minmax(0, 1fr) auto;
}

.monitoring-card__live {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 7px;
  border: 1px solid color-mix(in srgb, var(--color-success) 34%, var(--color-border));
  border-radius: 999px;
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 8%, transparent);
  font-family: var(--font-mono);
  font-size: .56rem;
  font-weight: 900;
  letter-spacing: .09em;
  text-transform: uppercase;
}

.monitoring-card__live i {
  width: 6px;
  height: 6px;
  background: currentColor;
  border-radius: 50%;
  box-shadow: 0 0 8px currentColor;
}

.monitoring-card__live--loading {
  color: var(--color-warning);
  border-color: color-mix(in srgb, var(--color-warning) 34%, var(--color-border));
  background: color-mix(in srgb, var(--color-warning) 8%, transparent);
}

.monitoring-card__live--loading i {
  animation: monitoring-pulse 1s ease-in-out infinite;
}

.monitoring-card__servers {
  display: grid;
  gap: 9px;
}

.monitoring-state {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 11px;
  min-height: 74px;
  padding: 13px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  color: var(--color-text-muted);
  background: var(--color-surface-soft);
}

.monitoring-state > span:last-child {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.monitoring-state strong {
  color: var(--color-text);
  font-size: .78rem;
}

.monitoring-state small {
  font-size: .67rem;
  line-height: 1.35;
}

.monitoring-state__spinner,
.monitoring-state__mark {
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  border: 1px solid var(--color-border-strong);
  border-radius: 9px;
  color: var(--color-accent);
  background: var(--color-surface);
  font-family: var(--font-heading);
  font-weight: 900;
}

.monitoring-state__spinner {
  border: 2px solid color-mix(in srgb, var(--color-accent) 18%, var(--color-border));
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: monitoring-spin .8s linear infinite;
}

.monitoring-state--error {
  border-color: color-mix(in srgb, var(--color-danger) 28%, var(--color-border));
  background: color-mix(in srgb, var(--color-danger) 7%, var(--color-surface-soft));
}

.monitoring-state--error .monitoring-state__mark {
  color: var(--color-danger);
}

.monitoring-card__warning {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 10px;
  border: 1px solid color-mix(in srgb, var(--color-warning) 25%, var(--color-border));
  border-radius: 9px;
  color: var(--color-warning);
  background: color-mix(in srgb, var(--color-warning) 7%, transparent);
  font-size: .67rem;
  font-weight: 700;
}

.monitoring-card__warning span {
  width: 17px;
  height: 17px;
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid currentColor;
  border-radius: 50%;
  font-size: .58rem;
}

@keyframes monitoring-spin {
  to { transform: rotate(360deg); }
}

@keyframes monitoring-pulse {
  0%, 100% { opacity: .35; }
  50% { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .monitoring-card__live--loading i,
  .monitoring-state__spinner {
    animation: none;
  }
}
</style>
