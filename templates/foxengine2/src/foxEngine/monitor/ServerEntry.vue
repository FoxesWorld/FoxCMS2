<script setup lang="ts">
import { computed } from 'vue'
import type { ServerStatus } from '@engine/contracts/sidebar'

const props = defineProps<{ server: ServerStatus }>()
const emit = defineEmits<{ open: [name: string] }>()

const online = computed(() => normalizeCount(props.server.playersOnline))
const max = computed(() => {
  const value = normalizeCount(props.server.playersMax)
  return value > 0 ? value : null
})
const isOnline = computed(() => props.server.status.trim().toLowerCase() === 'online')
const loadPercent = computed(() => max.value === null
  ? 0
  : Math.min(100, Math.round((online.value / max.value) * 100)))
const state = computed(() => {
  if (!isOnline.value) return 'offline'
  if (loadPercent.value >= 90) return 'critical'
  if (loadPercent.value >= 70) return 'busy'
  return 'online'
})
const stateLabel = computed(() => {
  switch (state.value) {
    case 'critical': return 'Почти заполнен'
    case 'busy': return 'Высокая нагрузка'
    case 'offline': return 'Недоступен'
    default: return 'Работает'
  }
})
const playersLabel = computed(() => {
  if (!isOnline.value) return 'offline'
  return max.value === null ? `${online.value} игроков` : `${online.value} / ${max.value}`
})

function normalizeCount(value: unknown): number {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? Math.max(0, Math.floor(parsed)) : 0
}
</script>

<template>
  <button
    class="monitor-server"
    :class="`monitor-server--${state}`"
    type="button"
    :aria-label="`Открыть сервер ${server.serverName}. ${stateLabel}. ${playersLabel}.`"
    @click="emit('open', server.serverName)"
  >
    <span class="monitor-server__header">
      <span class="monitor-server__icon" aria-hidden="true">
        <img v-if="server.favicon" :src="server.favicon" alt="">
        <span v-else>F</span>
      </span>

      <span class="monitor-server__identity">
        <strong>{{ server.serverName }}</strong>
        <small>{{ server.version || 'Версия уточняется' }}</small>
      </span>

      <span class="monitor-server__status">
        <i aria-hidden="true" />
        {{ stateLabel }}
      </span>
    </span>

    <span class="monitor-server__stats">
      <span>Игроки</span>
      <strong>{{ playersLabel }}</strong>
      <span v-if="isOnline && max !== null">{{ loadPercent }}%</span>
    </span>

    <span
      class="monitor-server__progress"
      role="progressbar"
      aria-valuemin="0"
      :aria-valuenow="isOnline && max !== null ? Math.min(online, max) : undefined"
      :aria-valuemax="isOnline && max !== null ? max : undefined"
      :aria-valuetext="isOnline
        ? (max === null ? playersLabel : `${loadPercent}% — ${playersLabel}`)
        : 'Сервер недоступен'"
    >
      <span
        v-if="isOnline"
        class="monitor-server__fill"
        :class="{ 'monitor-server__fill--unknown': max === null }"
        :style="{ width: max === null ? '34%' : `${loadPercent}%` }"
      />
    </span>
  </button>
</template>

<style scoped>
.monitor-server {
  --monitor-state: var(--color-success);

  position: relative;
  width: 100%;
  display: grid;
  gap: 10px;
  padding: 11px;
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-left: 3px solid var(--monitor-state);
  border-radius: 12px;
  color: var(--color-text);
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--monitor-state) 7%, transparent), transparent 56%),
    var(--color-surface-soft);
  text-align: left;
  cursor: pointer;
  transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
}

.monitor-server::after {
  position: absolute;
  inset: 0;
  pointer-events: none;
  content: '';
  background: linear-gradient(110deg, transparent 72%, rgba(255,255,255,.025));
}

.monitor-server:hover {
  border-color: color-mix(in srgb, var(--monitor-state) 45%, var(--color-border));
  background-color: var(--color-surface-hover);
  box-shadow: 0 8px 22px rgba(0,0,0,.16);
  transform: translateY(-1px);
}

.monitor-server:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.monitor-server--busy {
  --monitor-state: var(--color-warning);
}

.monitor-server--critical {
  --monitor-state: var(--color-danger);
}

.monitor-server--offline {
  --monitor-state: var(--color-text-subtle);

  opacity: .72;
  filter: saturate(.5);
}

.monitor-server__header {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) auto;
  align-items: center;
  gap: 9px;
  min-width: 0;
}

.monitor-server__icon {
  width: 36px;
  height: 36px;
  display: grid;
  overflow: hidden;
  place-items: center;
  border: 1px solid var(--color-border);
  border-radius: 9px;
  color: var(--color-accent-contrast);
  background: var(--color-accent);
  font-family: var(--font-heading);
  font-weight: 900;
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.18);
}

.monitor-server__icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.monitor-server__identity {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.monitor-server__identity strong,
.monitor-server__identity small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.monitor-server__identity strong {
  font-family: var(--font-heading);
  font-size: .82rem;
  line-height: 1.1;
}

.monitor-server__identity small {
  color: var(--color-text-muted);
  font-family: var(--font-mono);
  font-size: .62rem;
}

.monitor-server__status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  max-width: 112px;
  overflow: hidden;
  color: var(--monitor-state);
  font-size: .54rem;
  font-weight: 900;
  letter-spacing: .045em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.monitor-server__status i {
  width: 6px;
  height: 6px;
  flex: 0 0 auto;
  background: currentColor;
  border-radius: 50%;
  box-shadow: 0 0 8px currentColor;
}

.monitor-server__stats {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: baseline;
  gap: 7px;
  color: var(--color-text-muted);
  font-size: .58rem;
  font-weight: 800;
  letter-spacing: .055em;
  text-transform: uppercase;
}

.monitor-server__stats strong {
  overflow: hidden;
  color: var(--color-text);
  font-family: var(--font-mono);
  font-size: .68rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.monitor-server__stats > :last-child {
  color: var(--monitor-state);
  font-family: var(--font-mono);
}

.monitor-server__progress {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 8px;
  display: block;
  overflow: hidden;
  border: 1px solid color-mix(in srgb, var(--color-border) 86%, transparent);
  border-radius: 3px;
  background: color-mix(in srgb, var(--color-bg) 70%, black);
  box-shadow: inset 0 1px 3px rgba(0,0,0,.38);
}

.monitor-server__fill {
  position: absolute;
  inset: 0 auto 0 0;
  min-width: 2px;
  border-radius: inherit;
  background:
    repeating-linear-gradient(120deg, transparent 0 8px, rgba(255,255,255,.13) 8px 11px),
    linear-gradient(90deg, color-mix(in srgb, var(--monitor-state) 78%, black), var(--monitor-state));
  box-shadow: 0 0 10px color-mix(in srgb, var(--monitor-state) 38%, transparent);
  transition: width var(--transition-standard);
}

.monitor-server__fill--unknown {
  animation: monitor-server-scan 1.55s ease-in-out infinite;
}

@keyframes monitor-server-scan {
  from { transform: translateX(-120%); }
  to { transform: translateX(340%); }
}

@media (max-width: 430px) {
  .monitor-server__header {
    grid-template-columns: 34px minmax(0, 1fr);
  }

  .monitor-server__status {
    grid-column: 2;
    max-width: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .monitor-server,
  .monitor-server__fill {
    transition: none;
  }

  .monitor-server__fill--unknown {
    animation: none;
  }
}
</style>
