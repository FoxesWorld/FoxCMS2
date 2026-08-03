<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ online: number; max: number }>()

const online = computed(() => normalizeCount(props.online))
const max = computed(() => {
  const value = normalizeCount(props.max)
  return value > 0 ? value : null
})
const percent = computed(() => max.value === null
  ? 0
  : Math.min(100, Math.round((online.value / max.value) * 100)))
const playersLabel = computed(() => max.value === null
  ? `${online.value} игроков`
  : `${online.value} / ${max.value}`)

function normalizeCount(value: unknown): number {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? Math.max(0, Math.floor(parsed)) : 0
}
</script>

<template>
  <div class="monitor-total-card">
    <div class="monitor-total-card__header">
      <span>
        <small>Общий онлайн</small>
        <strong>{{ playersLabel }}</strong>
      </span>
      <b v-if="max !== null">{{ percent }}%</b>
    </div>

    <span
      class="monitor-total-card__progress"
      role="progressbar"
      aria-label="Общая заполненность серверов"
      aria-valuemin="0"
      :aria-valuenow="max === null ? undefined : Math.min(online, max)"
      :aria-valuemax="max ?? undefined"
      :aria-valuetext="max === null ? playersLabel : `${percent}% — ${playersLabel}`"
    >
      <span
        class="monitor-total-card__fill"
        :class="{ 'monitor-total-card__fill--unknown': max === null }"
        :style="{ width: max === null ? '30%' : `${percent}%` }"
      />
    </span>
  </div>
</template>

<style scoped>
.monitor-total-card {
  display: grid;
  gap: 8px;
  margin-top: 2px;
  padding: 11px 12px;
  border: 1px solid color-mix(in srgb, var(--color-accent) 24%, var(--color-border));
  border-radius: 11px;
  background:
    linear-gradient(110deg, color-mix(in srgb, var(--color-accent) 9%, transparent), transparent 62%),
    var(--color-surface-soft);
}

.monitor-total-card__header {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 10px;
}

.monitor-total-card__header > span {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.monitor-total-card__header small {
  color: var(--color-text-muted);
  font-size: .58rem;
  font-weight: 900;
  letter-spacing: .07em;
  text-transform: uppercase;
}

.monitor-total-card__header strong {
  color: var(--color-text);
  font-family: var(--font-mono);
  font-size: .78rem;
}

.monitor-total-card__header b {
  color: var(--color-accent);
  font-family: var(--font-mono);
  font-size: .7rem;
}

.monitor-total-card__progress {
  position: relative;
  width: 100%;
  height: 7px;
  display: block;
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: 3px;
  background: color-mix(in srgb, var(--color-bg) 70%, black);
  box-shadow: inset 0 1px 3px rgba(0,0,0,.38);
}

.monitor-total-card__fill {
  position: absolute;
  inset: 0 auto 0 0;
  min-width: 2px;
  border-radius: inherit;
  background:
    repeating-linear-gradient(120deg, transparent 0 8px, rgba(255,255,255,.13) 8px 11px),
    linear-gradient(90deg, color-mix(in srgb, var(--color-accent) 72%, black), var(--color-accent));
  box-shadow: 0 0 10px color-mix(in srgb, var(--color-accent) 38%, transparent);
  transition: width var(--transition-standard);
}

.monitor-total-card__fill--unknown {
  animation: monitor-total-scan 1.55s ease-in-out infinite;
}

@keyframes monitor-total-scan {
  from { transform: translateX(-120%); }
  to { transform: translateX(360%); }
}

@media (prefers-reduced-motion: reduce) {
  .monitor-total-card__fill {
    transition: none;
  }

  .monitor-total-card__fill--unknown {
    animation: none;
  }
}
</style>
