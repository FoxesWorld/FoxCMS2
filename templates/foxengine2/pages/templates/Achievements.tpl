<fox-page-template id="achievements" schema="1" revision="5" updated-at="2026-08-08T05:20:00Z">
  <fox-template-body>
<section class="achievements-page" aria-labelledby="achievements-page-title">
    <header class="achievements-hero" :class="{ 'achievements-hero--global': statisticsMode }">
      <div class="achievements-hero__content">
        <span class="achievements-hero__eyebrow">
          <i class="fa-solid fa-trophy" aria-hidden="true" />
          {{ t('engine.views.achievements.001') }}
        </span>
        <h1 id="achievements-page-title">{{ t('engine.views.achievements.013') }}</h1>
        <p>{{ t('engine.views.achievements.003') }}</p>

        <form class="achievements-player-search" @submit.prevent="openPlayer">
          <label for="achievement-player-search">{{ t('engine.views.achievements.004') }}</label>
          <span class="achievements-player-search__field">
            <i class="fa-solid fa-user-magnifying-glass" aria-hidden="true" />
            <input
              id="achievement-player-search"
              v-model="playerInput"
              type="search"
              autocomplete="off"
              :placeholder="t('engine.views.achievements.005')"
            >
          </span>
          <button class="button button--primary" type="submit" :disabled="!playerInput.trim()">
            <i class="fa-solid fa-arrow-right" aria-hidden="true" />
            {{ t('engine.views.achievements.006') }}
          </button>
          <button
            v-if="isLogged && (statisticsMode || playerIdentity !== currentLogin && playerIdentity !== currentUuid)"
            class="button button--ghost"
            type="button"
            @click="openMyAchievements"
          >
            {{ t('engine.views.achievements.007') }}
          </button>
          <button
            v-if="!statisticsMode"
            class="button button--ghost"
            type="button"
            @click="openStatistics"
          >
            <i class="fa-solid fa-chart-simple" aria-hidden="true" />
            {{ t('engine.views.achievements.039') }}
          </button>
        </form>
      </div>

      <div v-if="!statisticsMode && hasPlayerContext" class="achievements-hero__progress" aria-hidden="true">
        <svg viewBox="0 0 160 160">
          <circle class="achievements-hero__progress-track" cx="80" cy="80" r="66" />
          <circle
            class="achievements-hero__progress-value"
            cx="80"
            cy="80"
            r="66"
            :style="{ '--achievement-progress': `${completionPercent}` }"
          />
        </svg>
        <span>
          <strong>{{ completionPercent }}%</strong>
          <small>{{ playerName }}</small>
        </span>
      </div>
    </header>

    <div v-if="!statisticsMode && hasPlayerContext" class="achievements-metrics" :aria-label="t('engine.views.achievements.008')">
      <article class="achievement-metric achievement-metric--completed">
        <i class="fa-solid fa-circle-check" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.009') }}</small><strong>{{ summary.completedCount }}</strong></span>
      </article>
      <article class="achievement-metric achievement-metric--remaining">
        <i class="fa-solid fa-lock" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.010') }}</small><strong>{{ remainingCount }}</strong></span>
      </article>
      <article class="achievement-metric achievement-metric--points">
        <i class="fa-solid fa-star" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.011') }}</small><strong>{{ summary.points }}</strong></span>
      </article>
      <article class="achievement-metric achievement-metric--challenge">
        <i class="fa-solid fa-crown" aria-hidden="true" />
        <span><small>{{ t('engine.views.achievements.012') }}</small><strong>{{ completedChallengeCount }} / {{ challengeCount }}</strong></span>
      </article>
    </div>

    <section
      v-if="!statisticsMode && hasPlayerContext"
      class="achievements-overall-progress"
      :aria-label="t('engine.views.achievements.008')"
    >
      <div class="achievements-overall-progress__copy">
        <span>
          <small>{{ t('engine.views.achievements.009') }}</small>
          <strong>{{ summary.completedCount }} / {{ summary.trackedCount }}</strong>
        </span>
        <b>{{ completionPercent }}%</b>
      </div>
      <span class="achievements-overall-progress__track" aria-hidden="true">
        <i :style="{ width: `${completionPercent}%` }" />
      </span>
    </section>

    <div v-if="!statisticsMode" class="achievements-workspace" :class="{ 'achievements-workspace--catalog-only': !hasPlayerContext }">
      <main class="achievements-catalog">
        <section class="achievements-server-context" :aria-label="t('engine.views.achievements.061')">
          <span class="achievements-server-context__identity">
            <i class="fa-solid fa-server" aria-hidden="true" />
            <span>
              <small>{{ t('engine.views.achievements.013') }}</small>
              <strong>{{ server || '—' }}</strong>
            </span>
          </span>
          <label class="achievements-server-context__select">
            <small>{{ t('engine.views.achievements.061') }}</small>
            <select
              v-model="server"
              :aria-label="t('engine.views.achievements.061')"
              :disabled="servers.length <= 1"
            >
              <option v-for="value in servers" :key="value" :value="value">{{ value }}</option>
            </select>
          </label>
        </section>

        <div class="achievements-toolbar">
          <div class="achievements-toolbar__heading">
            <span>
              <small>{{ hasPlayerContext ? playerName : server }}</small>
              <strong>{{ categoryIndex ? t('engine.views.achievements.064') : activeCategorySummary?.label || t('engine.views.achievements.013') }}</strong>
            </span>
            <em v-if="categoryIndex">{{ t('engine.views.achievements.069', [visibleCategorySummaries.length]) }}</em>
            <em v-else>{{ t('engine.views.achievements.014', [filteredItems.length, activeCategorySummary?.totalCount || selectedItems.length]) }}</em>
          </div>

          <div class="achievements-toolbar__controls achievements-toolbar__controls--catalog">
            <button
              v-if="activeCategorySummary"
              class="button button--ghost achievements-category-back"
              type="button"
              @click="closeCategory"
            >
              <i class="fa-solid fa-arrow-left" aria-hidden="true" />
              {{ t('engine.views.achievements.067') }}
            </button>
            <label class="achievements-search">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
              <input v-model="search" type="search" :placeholder="t('engine.views.achievements.015')">
            </label>
          </div>

          <div v-if="hasPlayerContext" class="achievements-status-tabs" role="group" :aria-label="t('engine.views.achievements.019')">
            <button type="button" :class="{ 'is-active': status === 'all' }" @click="status = 'all'">{{ t('engine.views.achievements.020') }}</button>
            <button type="button" :class="{ 'is-active': status === 'completed' }" @click="status = 'completed'">{{ t('engine.views.achievements.021') }}</button>
            <button type="button" :class="{ 'is-active': status === 'locked' }" @click="status = 'locked'">{{ t('engine.views.achievements.022') }}</button>
            <button type="button" :class="{ 'is-active': status === 'challenge' }" @click="status = 'challenge'">{{ t('engine.views.achievements.023') }}</button>
          </div>
        </div>

        <div v-if="loading" class="achievements-state" aria-live="polite">
          <i class="fa-solid fa-spinner achievements-spin" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.024') }}</strong>
          <span>{{ t('engine.views.achievements.025') }}</span>
        </div>

        <div v-else-if="error" class="achievements-state achievements-state--error" role="alert">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.018') }}</strong>
          <span>{{ error }}</span>
          <button class="button button--ghost" type="button" @click="refresh()">{{ t('engine.views.achievements.026') }}</button>
        </div>

        <div v-else-if="items.length === 0" class="achievements-state">
          <i class="fa-solid fa-medal" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.027') }}</strong>
          <span>{{ t('engine.views.achievements.028') }}</span>
        </div>

        <div v-else-if="categoryIndex && visibleCategorySummaries.length === 0" class="achievements-state">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.029') }}</strong>
          <span>{{ t('engine.views.achievements.030') }}</span>
          <button class="button button--ghost" type="button" @click="resetFilters">{{ t('engine.views.achievements.032') }}</button>
        </div>

        <div v-else-if="categoryIndex" class="achievement-category-grid" :aria-label="t('engine.views.achievements.017')">
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
            </span>
            <span class="achievement-category-card__action">
              {{ t('engine.views.achievements.066') }}
              <i class="fa-solid fa-arrow-right" aria-hidden="true" />
            </span>
          </button>
        </div>

        <div v-else-if="filteredItems.length === 0" class="achievements-state">
          <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.029') }}</strong>
          <span>{{ t('engine.views.achievements.030') }}</span>
          <button class="button button--ghost" type="button" @click="resetFilters">{{ t('engine.views.achievements.032') }}</button>
        </div>

        <div v-else class="achievements-grid">
          <article
            v-for="item in filteredItems"
            :key="`${item.serverId}:${item.achievementKey}`"
            class="achievement-tile"
            :class="{
              'is-completed': item.completed,
              'is-challenge': item.frameType === 'challenge',
              'is-locked': hasPlayerContext && !item.completed,
            }"
          >
            <div class="achievement-tile__top">
              <span class="achievement-tile__icon-wrap">
                <img :src="item.iconDataUrl" alt="" loading="lazy" decoding="async">
                <i :class="item.completed ? 'fa-solid fa-check' : 'fa-solid fa-lock'" aria-hidden="true" />
              </span>
              <div class="achievement-tile__badges">
                <span v-if="hasPlayerContext" class="achievement-tile__state">
                  <i :class="item.completed ? 'fa-solid fa-circle-check' : 'fa-solid fa-lock'" aria-hidden="true" />
                  {{ item.completed ? t('engine.views.achievements.021') : t('engine.views.achievements.022') }}
                </span>
                <span class="achievement-tile__points">+{{ item.points }}</span>
              </div>
            </div>
            <div class="achievement-tile__body">
              <small>{{ item.categoryLabel || item.category }} · {{ item.serverId }}</small>
              <h2>{{ item.title }}</h2>
              <p>{{ item.description || t('engine.views.achievements.033') }}</p>
            </div>
            <div v-if="hasPlayerContext" class="achievement-tile__progress">
              <span><i :style="{ width: `${progressPercent(item)}%` }" /></span>
              <small>{{ item.progress }} / {{ item.target }}</small>
            </div>
            <footer>
              <span>
                <i :class="item.frameType === 'challenge' ? 'fa-solid fa-crown' : 'fa-solid fa-medal'" aria-hidden="true" />
                {{ item.frameType === 'challenge' ? t('engine.views.achievements.023') : t('engine.views.achievements.035') }}
              </span>
              <time>{{ achievementDate(item) }}</time>
            </footer>
          </article>
        </div>
      </main>

      <aside v-if="hasPlayerContext" class="achievements-sidebar">
        <section class="achievements-sidebar__panel">
          <header>
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true" />
            <span><small>{{ t('engine.views.achievements.036') }}</small><strong>{{ t('engine.views.achievements.037') }}</strong></span>
          </header>
          <p v-if="recentAchievements.length === 0" class="achievements-sidebar__empty">{{ t('engine.views.achievements.038') }}</p>
          <ol v-else class="achievements-recent">
            <li v-for="item in recentAchievements" :key="`recent:${item.serverId}:${item.achievementKey}`">
              <img :src="item.iconDataUrl" alt="" loading="lazy">
              <span><strong>{{ item.title }}</strong><small>{{ achievementDate(item) }}</small></span>
              <b>+{{ item.points }}</b>
            </li>
          </ol>
        </section>

      </aside>
    </div>

    <Suspense v-else>
      <AchievementStatisticsTree />
      <template #fallback>
        <div class="achievements-state" aria-live="polite">
          <i class="fa-solid fa-spinner achievements-spin" aria-hidden="true" />
          <strong>{{ t('engine.views.achievements.050') }}</strong>
          <span>{{ t('engine.views.achievements.051') }}</span>
        </div>
      </template>
    </Suspense>
  </section>
  </fox-template-body>
</fox-page-template>
