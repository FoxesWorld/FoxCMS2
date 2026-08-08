<fox-page-template id="achievement-profile-panel" schema="1" revision="3" updated-at="2026-08-08T05:20:00Z">
  <fox-template-body>
<section class="profile-panel profile-achievements" :aria-label="t('theme.profileachievements.001')">
    <header class="profile-achievements__header">
      <span class="profile-achievements__heading-icon" aria-hidden="true">
        <i class="fa-solid fa-trophy" />
      </span>
      <span class="profile-achievements__heading-copy">
        <small>{{ t('theme.profileachievements.002') }}</small>
        <strong>{{ t('theme.profileachievements.001') }}</strong>
        <span>{{ t('theme.profileachievements.003') }}</span>
      </span>
      <span class="profile-achievements__actions">
        <label class="profile-achievements__server-select">
          <i class="fa-solid fa-server" aria-hidden="true" />
          <select
            v-model="server"
            :aria-label="t('engine.views.achievements.061')"
            :title="t('engine.views.achievements.061')"
            :disabled="servers.length <= 1"
          >
            <option v-for="value in servers" :key="value" :value="value">{{ value }}</option>
          </select>
        </label>
        <button
          class="profile-achievements__refresh"
          type="button"
          :disabled="loading"
          :aria-label="t('theme.profileachievements.004')"
          @click="refresh()"
        >
          <i class="fa-solid fa-rotate" :class="{ 'profile-achievements__spin': loading }" aria-hidden="true" />
        </button>
      </span>
    </header>

    <div v-if="loading && items.length === 0" class="profile-achievements__state">
      <i class="fa-solid fa-spinner profile-achievements__spin" aria-hidden="true" />
      <span>{{ t('theme.profileachievements.005') }}</span>
    </div>

    <div v-else-if="error" class="profile-achievements__state profile-achievements__state--error" role="alert">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" />
      <strong>{{ t('theme.profileachievements.010') }}</strong>
      <span>{{ error }}</span>
    </div>

    <div v-else-if="items.length === 0" class="profile-achievements__state">
      <i class="fa-solid fa-medal" aria-hidden="true" />
      <strong>{{ t('theme.profileachievements.006') }}</strong>
      <span>{{ t('theme.profileachievements.007') }}</span>
    </div>

    <template v-else>
      <div class="profile-achievements__summary">
        <article>
          <small>{{ t('theme.profileachievements.012') }}</small>
          <strong>{{ summary.completedCount }} / {{ summary.trackedCount }}</strong>
          <span>{{ completionPercent }}%</span>
        </article>
        <article>
          <small>{{ t('theme.profileachievements.013') }}</small>
          <strong>{{ summary.points }}</strong>
          <span>{{ t('theme.profileachievements.014') }}</span>
        </article>
      </div>

      <div class="profile-achievements__list">
        <article
          v-for="item in selectedItems"
          :key="`${item.serverId}:${item.achievementKey}`"
          class="profile-achievement-card"
          :class="{
            'is-completed': item.completed,
            'is-challenge': item.frameType === 'challenge',
          }"
        >
          <img
            class="profile-achievement-card__icon"
            :src="item.iconDataUrl"
            :alt="''"
            loading="lazy"
            decoding="async"
          >
          <span class="profile-achievement-card__content">
            <small>{{ item.categoryLabel || item.category }} · {{ item.serverId }}</small>
            <strong>{{ item.title }}</strong>
            <span>{{ item.description || t('theme.profileachievements.015') }}</span>
            <span class="profile-achievement-card__progress" aria-hidden="true">
              <span :style="{ width: `${progressPercent(item)}%` }" />
            </span>
          </span>
          <span class="profile-achievement-card__meta">
            <strong>+{{ item.points }}</strong>
            <span>{{ t('theme.profileachievements.014') }}</span>
            <small>{{ timestamp(item.completedAt || item.updatedAt) }}</small>
          </span>
        </article>
      </div>
    </template>
  </section>
  </fox-template-body>
</fox-page-template>
