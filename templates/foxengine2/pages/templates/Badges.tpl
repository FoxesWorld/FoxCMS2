<fox-page-template id="badges" schema="1" revision="1" updated-at="">
  <fox-template-body>
<div v-if="loading" class="content-skeleton"><span /><span /><span /></div>

  <article v-else-if="!error" class="content-surface badges-directory">
    <header class="badges-directory__header">
      <div>
        <span class="eyebrow">{{ t('theme.useroptions.pages.badges.badges.001') }}</span>
        <h1>{{ t('theme.useroptions.pages.badges.badges.002') }}</h1>
        <p class="lead"> {{ t('theme.useroptions.pages.badges.badges.003') }} </p>
      </div>
      <dl class="badges-directory__summary">
        <div><dt>{{ t('theme.useroptions.pages.badges.badges.004') }}</dt><dd>{{ badges.length }}</dd></div>
        <div><dt>{{ t('theme.useroptions.pages.badges.badges.005') }}</dt><dd>{{ configuredCount }}</dd></div>
      </dl>
    </header>

    <section class="badge-claim-panel" :class="{ 'is-guest': !authenticated }">
      <div class="badge-claim-panel__intro">
        <span class="badge-claim-panel__icon" aria-hidden="true"><i class="fa-solid fa-key" /></span>
        <div>
          <span class="eyebrow">{{ t('theme.useroptions.pages.badges.badges.006') }}</span>
          <h2>{{ t('theme.useroptions.pages.badges.badges.007') }}</h2>
          <p v-if="authenticated">{{ t('theme.useroptions.pages.badges.badges.008') }}</p>
          <p v-else>{{ t('theme.useroptions.pages.badges.badges.009') }}</p>
        </div>
      </div>

      <form class="badge-claim-panel__form" @submit.prevent="emit('claim')">
        <label>
          <span class="visually-hidden">{{ t('theme.useroptions.pages.badges.badges.010') }}</span>
          <i class="fa-solid fa-key" aria-hidden="true" />
          <input
            :value="claimCode"
            type="text"
            inputmode="text"
            autocomplete="off"
            spellcheck="false"
            placeholder="fcr_..."
            :disabled="!authenticated || claiming"
            @input="updateClaimCode"
          >
        </label>
        <button class="button button--primary" type="submit" :disabled="!authenticated || claiming || !claimCode.trim()">
          <i class="fa-solid" :class="claiming ? 'fa-spinner' : 'fa-coins'" aria-hidden="true" />
          <span>{{ claiming ? t('theme.useroptions.pages.badges.badges.011') : t('theme.useroptions.pages.badges.badges.012') }}</span>
        </button>
      </form>

      <div
        v-if="claimFeedback"
        class="badge-claim-feedback"
        :class="`is-${claimFeedback.type}`"
        role="status"
      >
        <i
          class="fa-solid"
          :class="claimFeedback.type === 'success' ? 'fa-circle-check' : claimFeedback.type === 'warning' ? 'fa-circle-exclamation' : 'fa-circle-xmark'"
          aria-hidden="true"
        />
        <span>{{ claimFeedback.message }}</span>
      </div>

      <article v-if="claimedReward" class="badge-claim-result">
        <span class="badge-claim-result__image">
          <img v-if="claimedReward.badge?.image" :src="claimedReward.badge.image" :alt="claimedReward.badge.title" decoding="async">
          <i v-else-if="claimedReward.badge" class="fa-solid fa-award" aria-hidden="true" />
          <i v-else class="fa-solid fa-coins" aria-hidden="true" />
        </span>
        <div>
          <small>{{ t('theme.useroptions.pages.badges.badges.014') }}</small>
          <strong>{{ claimedReward.title }}</strong>
          <p>{{ claimedReward.description || t('theme.useroptions.pages.badges.badges.015') }}</p>
          <ul class="badge-claim-result__components">
            <li v-if="claimedReward.badge"><i class="fa-solid fa-award" aria-hidden="true" /> {{ t('theme.useroptions.pages.badges.badges.016') }}{{ claimedReward.badge.title }}»</li>
            <li v-if="claimedReward.currency"><i class="fa-solid fa-coins" aria-hidden="true" /> +{{ claimedReward.currency.amount }} {{ claimedReward.currency.currencyName }}</li>
          </ul>
        </div>
      </article>
    </section>

    <label class="badges-directory__search">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
      <input v-model="search" type="search" :placeholder="t('theme.useroptions.pages.badges.badges.017')" autocomplete="off">
      <span>{{ filteredBadges.length }} {{ t('theme.useroptions.pages.badges.badges.018') }} {{ badges.length }}</span>
    </label>

    <div v-if="filteredBadges.length" class="badges-table-wrap">
      <table class="badges-table">
        <thead>
          <tr>
            <th scope="col">{{ t('theme.useroptions.pages.badges.badges.019') }}</th>
            <th scope="col">{{ t('theme.useroptions.pages.badges.badges.020') }}</th>
            <th scope="col">{{ t('theme.useroptions.pages.badges.badges.021') }}</th>
            <th scope="col"><span class="visually-hidden">{{ t('theme.useroptions.pages.badges.badges.022') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="badge in filteredBadges"
            :key="badge.databaseId || badge.id"
            :class="{ 'is-clickable': badge.pageConfigured, 'is-unavailable': !badge.pageConfigured }"
            :tabindex="badge.pageConfigured ? 0 : undefined"
            :role="badge.pageConfigured ? 'link' : undefined"
            :aria-label="badge.pageConfigured ? t('theme.useroptions.pages.badges.badges.023', [badge.title]) : undefined"
            @click="openBadge(badge)"
            @keydown="handleRowKeydown($event, badge)"
          >
            <td :data-label="t('theme.useroptions.pages.badges.badges.019')">
              <span class="badges-table__image">
                <img v-if="badge.image" :src="badge.image" :alt="badge.title" loading="lazy" decoding="async">
                <i v-else class="fa-solid fa-award" aria-hidden="true" />
              </span>
            </td>
            <td :data-label="t('theme.useroptions.pages.badges.badges.020')">
              <strong>{{ badge.title }}</strong>
              <small v-if="!badge.pageConfigured">{{ t('theme.useroptions.pages.badges.badges.024') }}</small>
            </td>
            <td :data-label="t('theme.useroptions.pages.badges.badges.021')">
              <p>{{ badge.description || t('theme.useroptions.pages.badges.badges.025') }}</p>
            </td>
            <td class="badges-table__action">
              <i v-if="badge.pageConfigured" class="fa-solid fa-chevron-right" aria-hidden="true" />
              <span v-else>{{ t('theme.useroptions.pages.badges.badges.026') }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="badges-directory__empty">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
      <strong>{{ t('theme.useroptions.pages.badges.badges.027') }}</strong>
      <p>{{ t('theme.useroptions.pages.badges.badges.028') }}</p>
    </div>
  </article>

  <div v-else class="system-message system-message--error">
    <strong>{{ t('theme.useroptions.pages.badges.badges.029') }}</strong>
    <p>{{ t('theme.useroptions.pages.badges.badges.030') }}</p>
  </div>
  </fox-template-body>
</fox-page-template>
