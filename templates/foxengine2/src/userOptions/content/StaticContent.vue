<script setup lang="ts">
import type { StaticPageDefinition } from '@engine/content/contentData'
import StaticPage from './StaticPage.vue'

interface BadgeClaimFeedback {
  type: 'success' | 'warning' | 'error'
  message: string
}

defineProps<{
  pageId: string
  loading: boolean
  error: boolean
  page: StaticPageDefinition | null
  authenticated: boolean
  claimingBadge: boolean
  claimedBadgeOwned: boolean
  badgeClaimFeedback: BadgeClaimFeedback | null
}>()

const emit = defineEmits<{ claimBadge: [badgeName: string] }>()
const rulesBadgeName = 'Знаток правил'
</script>

<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <template v-else-if="page">
    <StaticPage :page="page" />

    <Teleport
      v-if="pageId === 'rules'"
      defer
      to=".static-content-page--rules"
    >
      <section class="rules-badge-claim" aria-labelledby="rules-badge-title">
        <div class="rules-badge-claim__mark" aria-hidden="true">
          <i class="fa-solid fa-award" />
        </div>
        <div class="rules-badge-claim__content">
          <p class="rules-badge-claim__eyebrow">Награда за ознакомление</p>
          <h2 id="rules-badge-title">{{ rulesBadgeName }}</h2>
          <p>Подтвердите ознакомление с правилами и получите бейдж в профиль. Сервер создаст и немедленно применит одноразовый код получения.</p>
        </div>
        <button
          class="button button--primary rules-badge-claim__button"
          type="button"
          :disabled="!authenticated || claimingBadge || claimedBadgeOwned"
          @click="emit('claimBadge', rulesBadgeName)"
        >
          <i
            class="fa-solid"
            :class="claimingBadge ? 'fa-spinner' : claimedBadgeOwned ? 'fa-circle-check' : 'fa-award'"
            aria-hidden="true"
          />
          <span v-if="claimingBadge">Получаем…</span>
          <span v-else-if="claimedBadgeOwned">Бейдж получен</span>
          <span v-else>Получить бейдж</span>
        </button>
        <p
          v-if="badgeClaimFeedback"
          class="rules-badge-claim__feedback"
          :class="`rules-badge-claim__feedback--${badgeClaimFeedback.type}`"
          role="status"
          aria-live="polite"
        >
          {{ badgeClaimFeedback.message }}
        </p>
        <p v-else-if="!authenticated" class="rules-badge-claim__hint">
          Для получения бейджа необходимо войти в аккаунт.
        </p>
      </section>
    </Teleport>
  </template>
  <div v-else-if="error" class="system-message system-message--error">
    <strong>Страница не найдена</strong>
    <p>Запрошенный материал отсутствует в runtime-реестре темы.</p>
  </div>
</template>
