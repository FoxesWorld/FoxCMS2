<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import type { BadgeDefinition } from '@engine/content/contentData'

interface RewardClaimFeedback {
  type: 'success' | 'warning' | 'error'
  message: string
}
interface ClaimedRewardBadge {
  id: number
  badgeName: string
  title: string
  description: string
  image: string | null
  acquiredAt: number
}
interface ClaimedRewardCurrency {
  currencyCode: string
  currencyName: string
  currencySymbol: string
  amount: number
}
interface ClaimedReward {
  id: number
  rewardName: string
  title: string
  description: string
  badge: ClaimedRewardBadge | null
  currency: ClaimedRewardCurrency | null
}

const props = defineProps<{
  badges: readonly BadgeDefinition[]
  loading: boolean
  error: boolean
  claimCode: string
  authenticated: boolean
  claiming: boolean
  claimFeedback: RewardClaimFeedback | null
  claimedReward: ClaimedReward | null
}>()

const emit = defineEmits<{
  'update:claimCode': [value: string]
  claim: []
}>()

const router = useRouter()
const search = ref('')

const normalizedSearch = computed(() => search.value.trim().toLocaleLowerCase('ru'))
const filteredBadges = computed(() => {
  const query = normalizedSearch.value
  if (!query) return props.badges
  return props.badges.filter((badge) =>
    badge.title.toLocaleLowerCase('ru').includes(query)
    || badge.description.toLocaleLowerCase('ru').includes(query),
  )
})
const configuredCount = computed(() => props.badges.filter((badge) => badge.pageConfigured).length)

function updateClaimCode(event: Event): void {
  emit('update:claimCode', (event.target as HTMLInputElement).value)
}

function openBadge(badge: BadgeDefinition): void {
  if (!badge.pageConfigured) return
  void router.push({ name: 'badge', params: { id: badge.id } })
}

function handleRowKeydown(event: KeyboardEvent, badge: BadgeDefinition): void {
  if (event.key !== 'Enter' && event.key !== ' ') return
  event.preventDefault()
  openBadge(badge)
}
</script>

<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>

  <article v-else-if="!error" class="content-surface badges-directory">
    <header class="badges-directory__header">
      <div>
        <span class="eyebrow">FoxesCraft collection</span>
        <h1>Бейджи</h1>
        <p class="lead">
          Каталог всех бейджей проекта. Название, изображение и краткое описание загружаются напрямую из базы данных.
        </p>
      </div>
      <dl class="badges-directory__summary">
        <div><dt>Всего</dt><dd>{{ badges.length }}</dd></div>
        <div><dt>Полные страницы</dt><dd>{{ configuredCount }}</dd></div>
      </dl>
    </header>

    <section class="badge-claim-panel" :class="{ 'is-guest': !authenticated }">
      <div class="badge-claim-panel__intro">
        <span class="badge-claim-panel__icon" aria-hidden="true"><i class="fa-solid fa-key" /></span>
        <div>
          <span class="eyebrow">Получение награды</span>
          <h2>Активировать код</h2>
          <p v-if="authenticated">Введите одноразовый или многоразовый криптографический код. Награда может содержать бейдж, валюту либо оба компонента.</p>
          <p v-else>Для применения кода необходимо войти в аккаунт.</p>
        </div>
      </div>

      <form class="badge-claim-panel__form" @submit.prevent="emit('claim')">
        <label>
          <span class="visually-hidden">Код получения награды</span>
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
          <span>{{ claiming ? 'Проверяем код' : 'Получить награду' }}</span>
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
          <small>Полученная награда</small>
          <strong>{{ claimedReward.title }}</strong>
          <p>{{ claimedReward.description || 'Награда успешно применена к профилю.' }}</p>
          <ul class="badge-claim-result__components">
            <li v-if="claimedReward.badge"><i class="fa-solid fa-award" aria-hidden="true" /> Бейдж «{{ claimedReward.badge.title }}»</li>
            <li v-if="claimedReward.currency"><i class="fa-solid fa-coins" aria-hidden="true" /> +{{ claimedReward.currency.amount }} {{ claimedReward.currency.currencyName }}</li>
          </ul>
        </div>
      </article>
    </section>

    <label class="badges-directory__search">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
      <input v-model="search" type="search" placeholder="Найти бейдж по имени или описанию" autocomplete="off">
      <span>{{ filteredBadges.length }} из {{ badges.length }}</span>
    </label>

    <div v-if="filteredBadges.length" class="badges-table-wrap">
      <table class="badges-table">
        <thead>
          <tr>
            <th scope="col">Изображение</th>
            <th scope="col">Название</th>
            <th scope="col">Краткое описание</th>
            <th scope="col"><span class="visually-hidden">Открыть</span></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="badge in filteredBadges"
            :key="badge.databaseId || badge.id"
            :class="{ 'is-clickable': badge.pageConfigured, 'is-unavailable': !badge.pageConfigured }"
            :tabindex="badge.pageConfigured ? 0 : undefined"
            :role="badge.pageConfigured ? 'link' : undefined"
            :aria-label="badge.pageConfigured ? `Открыть страницу бейджа ${badge.title}` : undefined"
            @click="openBadge(badge)"
            @keydown="handleRowKeydown($event, badge)"
          >
            <td data-label="Изображение">
              <span class="badges-table__image">
                <img v-if="badge.image" :src="badge.image" :alt="badge.title" loading="lazy" decoding="async">
                <i v-else class="fa-solid fa-award" aria-hidden="true" />
              </span>
            </td>
            <td data-label="Название">
              <strong>{{ badge.title }}</strong>
              <small v-if="!badge.pageConfigured">Полная страница ещё не создана</small>
            </td>
            <td data-label="Краткое описание">
              <p>{{ badge.description || 'Описание для этого бейджа пока не заполнено.' }}</p>
            </td>
            <td class="badges-table__action">
              <i v-if="badge.pageConfigured" class="fa-solid fa-chevron-right" aria-hidden="true" />
              <span v-else>Нет страницы</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="badges-directory__empty">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
      <strong>Бейджи не найдены</strong>
      <p>Измените поисковый запрос.</p>
    </div>
  </article>

  <div v-else class="system-message system-message--error">
    <strong>Каталог бейджей недоступен</strong>
    <p>Не удалось получить список бейджей из базы данных.</p>
  </div>
</template>
