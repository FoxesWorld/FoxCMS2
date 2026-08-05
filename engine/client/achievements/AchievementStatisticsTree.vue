<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { t } from '@/i18n'
import AchievementTreeNode from './AchievementTreeNode.vue'
import { buildAchievementTrees, filterAchievementTree, type AchievementTreeNodeModel } from './achievementStatisticsTree'
import {
  loadAchievementStatistics,
  type AchievementStatistic,
  type AchievementStatisticsSummary,
} from './playerAchievements'

const items = ref<AchievementStatistic[]>([])
const summary = ref<AchievementStatisticsSummary>({
  achievementCount: 0,
  earnedAchievementCount: 0,
  playerCount: 0,
  unlockCount: 0,
})
const loading = ref(true)
const error = ref('')
const search = ref('')
const server = ref('all')
const category = ref('all')
let controller: AbortController | null = null

const servers = computed(() => [...new Set(items.value.map((item) => item.serverId).filter(Boolean))].sort())
const categories = computed(() => [...new Set(items.value.map((item) => item.category).filter(Boolean))].sort())
const trees = computed(() => buildAchievementTrees(items.value))
const filteredTrees = computed(() => trees.value
  .filter((tree) => server.value === 'all' || tree.serverId === server.value)
  .map((tree) => ({
    ...tree,
    roots: tree.roots
      .map((root) => filterAchievementTree(root, search.value, category.value))
      .filter((root): root is NonNullable<typeof root> => root !== null),
  }))
  .filter((tree) => tree.roots.length > 0))
const visibleCount = computed(() => {
  const countNode = (node: AchievementTreeNodeModel): number => node.children.reduce(
    (total, child) => total + countNode(child),
    1,
  )
  return filteredTrees.value.reduce(
    (total, tree) => total + tree.roots.reduce((subtotal, root) => subtotal + countNode(root), 0),
    0,
  )
})

onMounted(() => void refresh())
onBeforeUnmount(() => controller?.abort())

async function refresh(): Promise<void> {
  controller?.abort()
  const request = new AbortController()
  controller = request
  loading.value = true
  error.value = ''
  try {
    const response = await loadAchievementStatistics(request.signal)
    items.value = response.items
    summary.value = response.summary
  } catch (reason) {
    if (reason instanceof DOMException && reason.name === 'AbortError') return
    error.value = reason instanceof Error ? reason.message : t('engine.views.achievements.052')
  } finally {
    if (controller === request) {
      controller = null
      loading.value = false
    }
  }
}

function resetFilters(): void {
  search.value = ''
  server.value = 'all'
  category.value = 'all'
}
</script>

<template>
  <section class="achievement-statistics" aria-labelledby="achievement-statistics-title">
    <header class="achievement-statistics__header">
      <div>
        <span class="achievement-statistics__eyebrow">
          <i class="fa-solid fa-chart-simple" aria-hidden="true" />
          {{ t('engine.views.achievements.039') }}
        </span>
        <h2 id="achievement-statistics-title">{{ t('engine.views.achievements.040') }}</h2>
        <p>{{ t('engine.views.achievements.060') }}</p>
      </div>
      <span v-if="!loading && items.length > 0" class="achievement-statistics__visible">
        {{ t('engine.views.achievements.014', [visibleCount, summary.achievementCount]) }}
      </span>
    </header>

    <div class="achievement-statistics__metrics" :aria-label="t('engine.views.achievements.039')">
      <article>
        <i class="fa-solid fa-sitemap" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.041') }}</small><strong>{{ summary.achievementCount }}</strong></span>
      </article>
      <article>
        <i class="fa-solid fa-circle-check" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.042') }}</small><strong>{{ summary.earnedAchievementCount }}</strong></span>
      </article>
      <article>
        <i class="fa-solid fa-users" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.043') }}</small><strong>{{ summary.playerCount }}</strong></span>
      </article>
      <article>
        <i class="fa-solid fa-trophy" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.044') }}</small><strong>{{ summary.unlockCount }}</strong></span>
      </article>
    </div>

    <div class="achievement-statistics__toolbar">
      <label class="achievement-statistics__search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
        <input v-model="search" type="search" :placeholder="t('engine.views.achievements.047')">
      </label>
      <select v-model="server" :aria-label="t('engine.views.achievements.048')">
        <option value="all">{{ t('engine.views.achievements.048') }}</option>
        <option v-for="value in servers" :key="value" :value="value">{{ value }}</option>
      </select>
      <select v-model="category" :aria-label="t('engine.views.achievements.049')">
        <option value="all">{{ t('engine.views.achievements.049') }}</option>
        <option v-for="value in categories" :key="value" :value="value">{{ value }}</option>
      </select>
    </div>

    <div v-if="loading" class="achievement-statistics__state" aria-live="polite">
      <i class="fa-solid fa-spinner achievement-statistics__spin" aria-hidden="true" />
      <strong>{{ t('engine.views.achievements.050') }}</strong>
      <span>{{ t('engine.views.achievements.051') }}</span>
    </div>

    <div v-else-if="error" class="achievement-statistics__state achievement-statistics__state--error" role="alert">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
      <strong>{{ t('engine.views.achievements.052') }}</strong>
      <span>{{ error }}</span>
      <button class="button button--ghost" type="button" @click="refresh">{{ t('engine.views.achievements.026') }}</button>
    </div>

    <div v-else-if="items.length === 0" class="achievement-statistics__state">
      <i class="fa-solid fa-sitemap" aria-hidden="true" />
      <strong>{{ t('engine.views.achievements.053') }}</strong>
      <span>{{ t('engine.views.achievements.054') }}</span>
    </div>

    <div v-else-if="filteredTrees.length === 0" class="achievement-statistics__state">
      <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true" />
      <strong>{{ t('engine.views.achievements.029') }}</strong>
      <span>{{ t('engine.views.achievements.030') }}</span>
      <button class="button button--ghost" type="button" @click="resetFilters">{{ t('engine.views.achievements.032') }}</button>
    </div>

    <div v-else class="achievement-statistics__servers">
      <section
        v-for="tree in filteredTrees"
        :key="tree.serverId"
        class="achievement-statistics__server"
      >
        <header>
          <span>
            <i class="fa-solid fa-server" aria-hidden="true" />
            <span><small>{{ t('engine.views.achievements.061') }}</small><strong>{{ tree.serverId }}</strong></span>
          </span>
          <div>
            <b>{{ tree.achievementCount }}</b>
            <small>{{ t('engine.views.achievements.062') }}</small>
            <b>{{ tree.unlockCount }}</b>
            <small>{{ t('engine.views.achievements.063') }}</small>
          </div>
        </header>
        <ol class="achievement-statistics__tree">
          <AchievementTreeNode
            v-for="root in tree.roots"
            :key="`${root.serverId}:${root.achievementKey}`"
            :node="root"
          />
        </ol>
      </section>
    </div>
  </section>
</template>

<style scoped>
.achievement-statistics {
  gap: 16px;
  display: grid;
  padding: 10px;
  background: #ece6e0;
  border-radius: 10px;
}
.achievement-statistics__header {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 20px;
  padding: 22px;
  border: 1px solid var(--color-border);
  border-radius: 21px;
  background:
    radial-gradient(circle at 100% 0, color-mix(in srgb, var(--achievement-accent) 11%, transparent), transparent 43%),
    var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}
.achievement-statistics__header > div { min-width: 0; }
.achievement-statistics__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: var(--achievement-accent);
  font-size: .63rem;
  font-weight: 900;
  letter-spacing: .06em;
  text-transform: uppercase;
}
.achievement-statistics__header h2 {
  margin: 7px 0 0;
  color: var(--color-text);
  font: 900 clamp(1.45rem, 3vw, 2rem)/1.05 var(--font-display);
}
.achievement-statistics__header p {
  max-width: 760px;
  margin: 8px 0 0;
  color: var(--color-text-muted);
  font-size: .78rem;
  line-height: 1.55;
}
.achievement-statistics__visible {
  flex: 0 0 auto;
  padding: 7px 10px;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  color: var(--color-text-muted);
  background: var(--color-surface-soft);
  font-size: .68rem;
  font-weight: 800;
}
.achievement-statistics__metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 11px;
}
.achievement-statistics__metrics article {
  min-width: 0;
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr);
  align-items: center;
  gap: 11px;
  padding: 15px;
  border: 1px solid var(--color-border);
  border-radius: 16px;
  background: var(--color-surface-strong);
  box-shadow: var(--shadow-soft);
}
.achievement-statistics__metrics article > i {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border-radius: 13px;
  color: var(--achievement-accent);
  background: color-mix(in srgb, var(--achievement-accent) 9%, var(--color-surface-soft));
}
.achievement-statistics__metrics article span { min-width: 0; display: grid; gap: 2px; }
.achievement-statistics__metrics small {
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: .58rem;
  font-weight: 850;
  letter-spacing: .04em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}
.achievement-statistics__metrics strong {
  color: var(--color-text);
  font: 900 1.35rem/1 var(--font-display);
}
.achievement-statistics__toolbar {
  display: grid;
  grid-template-columns: minmax(240px, 1fr) minmax(160px, .3fr) minmax(160px, .3fr);
  gap: 9px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: 17px;
  background: var(--color-surface-strong);
}
.achievement-statistics__search {
  min-width: 0;
  height: 43px;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 9px;
  padding: 0 13px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-surface-soft);
}
.achievement-statistics__search i { color: var(--achievement-accent); }
.achievement-statistics__search input {
  min-width: 0;
  border: 0;
  outline: 0;
  color: var(--color-text);
  background: transparent;
}
.achievement-statistics__toolbar select {
  min-width: 0;
  height: 43px;
  padding: 0 34px 0 12px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  color: var(--color-text);
  background: var(--color-surface-soft);
}
.achievement-statistics__servers { display: grid; gap: 16px; }
.achievement-statistics__server {
  display: grid;
  gap: 12px;
  padding: 18px;
  border: 1px solid var(--color-border);
  border-radius: 21px;
  background: color-mix(in srgb, var(--achievement-accent) 2%, var(--color-surface-soft));
}
.achievement-statistics__server > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 13px;
  border-bottom: 1px solid var(--color-border);
}
.achievement-statistics__server > header > span {
  display: flex;
  align-items: center;
  gap: 10px;
}
.achievement-statistics__server > header > span > i {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  color: var(--achievement-accent);
  background: color-mix(in srgb, var(--achievement-accent) 9%, var(--color-surface-strong));
}
.achievement-statistics__server > header > span > span { display: grid; gap: 1px; }
.achievement-statistics__server > header small {
  color: var(--color-text-muted);
  font-size: .58rem;
  font-weight: 850;
  letter-spacing: .04em;
  text-transform: uppercase;
}
.achievement-statistics__server > header strong { color: var(--color-text); font-size: .86rem; }
.achievement-statistics__server > header > div {
  display: grid;
  grid-template-columns: auto auto auto auto;
  align-items: baseline;
  gap: 5px 9px;
}
.achievement-statistics__server > header b { color: var(--achievement-accent); font-size: .8rem; }
.achievement-statistics__tree { display: grid; gap: 11px; margin: 0; padding: 0; list-style: none; }
.achievement-statistics__state {
  min-height: 280px;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 9px;
  padding: 32px;
  border: 1px dashed var(--color-border-strong);
  border-radius: 20px;
  color: var(--color-text-muted);
  background: var(--color-surface-strong);
  text-align: center;
}
.achievement-statistics__state > i { color: var(--achievement-accent); font-size: 1.6rem; }
.achievement-statistics__state strong { color: var(--color-text); font: 900 1.15rem/1.2 var(--font-display); }
.achievement-statistics__state span { max-width: 580px; line-height: 1.5; }
.achievement-statistics__state--error { border-color: color-mix(in srgb, var(--color-danger) 42%, var(--color-border)); }
.achievement-statistics__state--error > i { color: var(--color-danger); }
.achievement-statistics__spin { animation: achievement-statistics-spin .8s linear infinite; }
@keyframes achievement-statistics-spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
  .achievement-statistics__metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .achievement-statistics__toolbar { grid-template-columns: 1fr 1fr; }
  .achievement-statistics__search { grid-column: 1 / -1; }
}
@media (max-width: 680px) {
  .achievement-statistics__header,
  .achievement-statistics__server > header { align-items: flex-start; flex-direction: column; }
  .achievement-statistics__metrics,
  .achievement-statistics__toolbar { grid-template-columns: 1fr; }
  .achievement-statistics__search { grid-column: auto; }
  .achievement-statistics__server { padding: 12px; }
}
@media (prefers-reduced-motion: reduce) {
  .achievement-statistics__spin { animation: none; }
}
</style>
