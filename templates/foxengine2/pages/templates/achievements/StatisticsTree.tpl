<fox-page-template id="achievement-statistics" schema="1" revision="5" updated-at="2026-08-08T05:20:00Z">
  <fox-template-body>
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
      <select
        v-model="server"
        :aria-label="t('engine.views.achievements.061')"
        :title="t('engine.views.achievements.061')"
        :disabled="loading || servers.length <= 1"
      >
        <option v-for="value in servers" :key="value" :value="value">{{ value }}</option>
      </select>
      <button
        v-if="activeCategorySummary"
        class="button button--ghost achievements-category-back"
        type="button"
        @click="closeCategory"
      >
        <i class="fa-solid fa-arrow-left" aria-hidden="true" />
        {{ t('engine.views.achievements.067') }}
      </button>
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

    <div v-else-if="categoryIndex && visibleCategorySummaries.length === 0" class="achievement-statistics__state">
      <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true" />
      <strong>{{ t('engine.views.achievements.029') }}</strong>
      <span>{{ t('engine.views.achievements.030') }}</span>
      <button class="button button--ghost" type="button" @click="resetFilters">{{ t('engine.views.achievements.032') }}</button>
    </div>

    <div v-else-if="categoryIndex" class="achievement-category-grid achievement-statistics__categories" :aria-label="t('engine.views.achievements.049')">
      <button
        v-for="entry in visibleCategorySummaries"
        :key="entry.id"
        class="achievement-category-card"
        :class="{ 'is-complete': entry.isCompleted }"
        :style="{ '--category-progress': `${entry.completionPercent}%` }"
        type="button"
        @click="openCategory(entry.id)"
      >
        <span
          v-if="entry.isCompleted"
          class="achievement-category-card__complete"
          :title="t('engine.views.achievements.070')"
          :aria-label="t('engine.views.achievements.070')"
        >
          <i class="fa-solid fa-check" aria-hidden="true" />
        </span>
        <span class="achievement-category-card__icon">
          <img
            v-if="entry.iconDataUrl"
            :src="entry.iconDataUrl"
            :title="entry.iconItem || entry.label"
            alt=""
            loading="lazy"
            decoding="async"
          >
          <i v-else class="fa-solid fa-trophy" aria-hidden="true" />
        </span>
        <span class="achievement-category-card__body">
          <span class="achievement-category-card__meta">
            <small>{{ t('engine.views.achievements.064') }}</small>
            <b>{{ entry.completedCount }} / {{ entry.totalCount }}</b>
          </span>
          <strong>{{ entry.label }}</strong>
          <span class="achievement-category-card__progress-row">
            <span class="achievement-category-card__progress" aria-hidden="true"><i /></span>
            <b>{{ entry.completionPercent }}%</b>
          </span>
          <em>{{ t('engine.views.achievements.065', [entry.completedCount, entry.totalCount]) }}</em>
          <em class="achievement-category-card__unlocks">
            <i class="fa-solid fa-trophy" aria-hidden="true" />
            {{ entry.unlockCount }} {{ t('engine.views.achievements.063') }}
          </em>
        </span>
        <span class="achievement-category-card__action">
          {{ t('engine.views.achievements.066') }}
          <i class="fa-solid fa-arrow-right" aria-hidden="true" />
        </span>
      </button>
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
  </fox-template-body>
</fox-page-template>
