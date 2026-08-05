<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@/i18n'
import type { AchievementTreeNodeModel } from './achievementStatisticsTree'

const props = withDefaults(defineProps<{
  node: AchievementTreeNodeModel
  depth?: number
}>(), {
  depth: 0,
})

const nodeTone = computed(() => {
  if (props.node.frameType === 'challenge') return 'challenge'
  if (props.node.earnedCount > 0) return 'earned'
  return 'unearned'
})

function playerLabel(player: AchievementTreeNodeModel['players'][number]): string {
  return player.playerName || player.login || t('engine.views.achievements.034')
}

function playerInitial(player: AchievementTreeNodeModel['players'][number]): string {
  return playerLabel(player).trim().slice(0, 1).toLocaleUpperCase('ru-RU') || '?'
}

function playerRoute(player: AchievementTreeNodeModel['players'][number]): Record<string, unknown> {
  return { name: 'achievements', params: { value: player.login || player.uuid }, query: {} }
}

function completedDate(timestamp: number | null): string {
  if (!timestamp) return t('engine.views.achievements.031')
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(timestamp * 1000))
}
</script>

<template>
  <li
    class="achievement-tree-node"
    :class="[`achievement-tree-node--${nodeTone}`, { 'has-children': node.children.length > 0 }]"
  >
    <article class="achievement-tree-node__card">
      <span class="achievement-tree-node__connector" aria-hidden="true" />
      <div class="achievement-tree-node__identity">
        <span class="achievement-tree-node__icon">
          <img :src="node.iconDataUrl" alt="" loading="lazy" decoding="async">
          <i
            :class="node.earnedCount > 0 ? 'fa-solid fa-check' : 'fa-solid fa-lock'"
            aria-hidden="true"
          />
        </span>
        <span class="achievement-tree-node__copy">
          <small>{{ node.category }} · {{ node.serverId }}</small>
          <strong>{{ node.title }}</strong>
          <span>{{ node.description || t('engine.views.achievements.033') }}</span>
        </span>
      </div>

      <div class="achievement-tree-node__meta">
        <span class="achievement-tree-node__points">+{{ node.points }}</span>
        <span class="achievement-tree-node__earned">
          <i class="fa-solid fa-users" aria-hidden="true" />
          {{ t('engine.views.achievements.055', [node.earnedCount]) }}
        </span>
      </div>

      <details v-if="node.players.length > 0" class="achievement-tree-node__players">
        <summary>
          <span>
            <i class="fa-solid fa-user-group" aria-hidden="true" />
            {{ t('engine.views.achievements.057') }}
          </span>
          <i class="fa-solid fa-chevron-down" aria-hidden="true" />
        </summary>
        <div class="achievement-tree-node__player-list">
          <RouterLink
            v-for="player in node.players"
            :key="`${node.serverId}:${node.achievementKey}:${player.uuid}`"
            class="achievement-tree-player"
            :to="playerRoute(player)"
          >
            <span class="achievement-tree-player__avatar">{{ playerInitial(player) }}</span>
            <span>
              <strong>{{ playerLabel(player) }}</strong>
              <small>{{ completedDate(player.completedAt) }}</small>
            </span>
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true" />
          </RouterLink>
          <p v-if="node.playersTruncated" class="achievement-tree-node__truncated">
            {{ t('engine.views.achievements.058', [Math.max(0, node.earnedCount - node.players.length)]) }}
          </p>
        </div>
      </details>
      <p v-else class="achievement-tree-node__nobody">
        <i class="fa-solid fa-circle" aria-hidden="true" />
        {{ t('engine.views.achievements.056') }}
      </p>
    </article>

    <ol v-if="node.children.length > 0" class="achievement-tree-node__children">
      <AchievementTreeNode
        v-for="child in node.children"
        :key="`${child.serverId}:${child.achievementKey}`"
        :node="child"
        :depth="depth + 1"
      />
    </ol>
  </li>
</template>

<style scoped>
.achievement-tree-node {
  --node-color: #747b77;
  position: relative;
  min-width: 0;
  list-style: none;
}
.achievement-tree-node--earned { --node-color: var(--achievement-success, #3f9468); }
.achievement-tree-node--challenge { --node-color: var(--achievement-gold, #bd8d39); }
.achievement-tree-node__card {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 13px 18px;
  padding: 17px 18px;
  border: 1px solid color-mix(in srgb, var(--node-color) 22%, var(--color-border));
  border-radius: 18px;
  background:
    radial-gradient(circle at 100% 0, color-mix(in srgb, var(--node-color) 8%, transparent), transparent 38%),
    var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}
.achievement-tree-node__connector {
  position: absolute;
  left: -23px;
  top: 34px;
  width: 23px;
  height: 1px;
  background: color-mix(in srgb, var(--node-color) 30%, var(--color-border));
}
.achievement-tree-node__identity {
  min-width: 0;
  display: grid;
  grid-template-columns: 58px minmax(0, 1fr);
  align-items: center;
  gap: 13px;
}
.achievement-tree-node__icon {
  position: relative;
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  padding: 7px;
  border: 1px solid color-mix(in srgb, var(--node-color) 36%, var(--color-border));
  border-radius: 16px;
  background: color-mix(in srgb, var(--node-color) 9%, var(--color-surface-soft));
}
.achievement-tree-node__icon img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  image-rendering: pixelated;
}
.achievement-tree-node--unearned .achievement-tree-node__icon img {
  filter: grayscale(.9) opacity(.64);
}
.achievement-tree-node__icon i {
  position: absolute;
  right: -5px;
  bottom: -5px;
  width: 23px;
  height: 23px;
  display: grid;
  place-items: center;
  border: 3px solid var(--color-surface-strong);
  border-radius: 50%;
  color: #fff;
  background: var(--node-color);
  font-size: .53rem;
}
.achievement-tree-node__copy { min-width: 0; display: grid; gap: 3px; }
.achievement-tree-node__copy small {
  overflow: hidden;
  color: var(--node-color);
  font-size: .59rem;
  font-weight: 900;
  letter-spacing: .045em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}
.achievement-tree-node__copy strong {
  color: var(--color-text);
  font: 900 1rem/1.2 var(--font-display);
}
.achievement-tree-node__copy > span {
  display: -webkit-box;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: .72rem;
  line-height: 1.45;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.achievement-tree-node__meta {
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 7px;
}
.achievement-tree-node__points,
.achievement-tree-node__earned {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 9px;
  border: 1px solid color-mix(in srgb, var(--node-color) 18%, transparent);
  border-radius: 999px;
  color: var(--node-color);
  background: color-mix(in srgb, var(--node-color) 8%, var(--color-surface-soft));
  font-size: .62rem;
  font-weight: 900;
  white-space: nowrap;
}
.achievement-tree-node__points { color: var(--achievement-gold, #bd8d39); }
.achievement-tree-node__players,
.achievement-tree-node__nobody {
  grid-column: 1 / -1;
  margin: 0;
  border-top: 1px solid var(--color-border);
  padding-top: 10px;
}
.achievement-tree-node__players summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--color-text-muted);
  cursor: pointer;
  font-size: .68rem;
  font-weight: 800;
  list-style: none;
}
.achievement-tree-node__players summary::-webkit-details-marker { display: none; }
.achievement-tree-node__players summary span { display: inline-flex; align-items: center; gap: 7px; }
.achievement-tree-node__players summary > i { transition: transform .18s ease; }
.achievement-tree-node__players[open] summary > i { transform: rotate(180deg); }
.achievement-tree-node__player-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
  gap: 7px;
  padding-top: 10px;
}
.achievement-tree-player {
  min-width: 0;
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  padding: 7px 8px;
  border: 1px solid var(--color-border);
  border-radius: 11px;
  color: inherit;
  background: var(--color-surface-soft);
  text-decoration: none;
}
.achievement-tree-player:hover,
.achievement-tree-player:focus-visible {
  border-color: color-mix(in srgb, var(--node-color) 42%, var(--color-border));
  outline: none;
}
.achievement-tree-player__avatar {
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  color: var(--node-color);
  background: color-mix(in srgb, var(--node-color) 10%, var(--color-surface));
  font-size: .7rem;
  font-weight: 900;
}
.achievement-tree-player > span:nth-child(2) { min-width: 0; display: grid; gap: 1px; }
.achievement-tree-player strong {
  overflow: hidden;
  color: var(--color-text);
  font-size: .69rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.achievement-tree-player small { color: var(--color-text-muted); font-size: .57rem; }
.achievement-tree-player > i { color: var(--color-text-muted); font-size: .58rem; }
.achievement-tree-node__truncated {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--color-text-muted);
  font-size: .64rem;
  text-align: center;
}
.achievement-tree-node__nobody {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--color-text-muted);
  font-size: .66rem;
}
.achievement-tree-node__children {
  position: relative;
  display: grid;
  gap: 10px;
  margin: 10px 0 0 24px;
  padding: 0 0 0 23px;
  list-style: none;
}
.achievement-tree-node__children::before {
  position: absolute;
  left: 0;
  top: -10px;
  bottom: 34px;
  width: 1px;
  content: '';
  background: color-mix(in srgb, var(--node-color) 26%, var(--color-border));
}

@media (max-width: 680px) {
  .achievement-tree-node__card { grid-template-columns: 1fr; padding: 14px; }
  .achievement-tree-node__meta { justify-content: flex-start; }
  .achievement-tree-node__players,
  .achievement-tree-node__nobody { grid-column: auto; }
  .achievement-tree-node__children { margin-left: 8px; padding-left: 16px; }
  .achievement-tree-node__connector { left: -16px; width: 16px; }
}
</style>
