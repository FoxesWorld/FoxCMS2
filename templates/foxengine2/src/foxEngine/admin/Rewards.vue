<script setup lang="ts">
import { t } from '@/i18n'

import { computed, ref, watch } from 'vue'
import UiCheckbox from '@/components/UiCheckbox.vue'
import type {
  AdminBadgeOption,
  IssuedRewardClaimCode,
  RewardClaimAccessMode,
  RewardClaimKeyRow,
  RewardClaimUsageMode,
  RewardDefinitionRow,
  RewardDraft,
} from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  rewards: RewardDefinitionRow[]
  claimKeys: RewardClaimKeyRow[]
  issuedCode: IssuedRewardClaimCode | null
  badges: AdminBadgeOption[]
  draft: RewardDraft
  loading: boolean
  formatTimestamp: (value?: number | string) => string
}>()

const emit = defineEmits<{
  create: []
  edit: [reward: RewardDefinitionRow]
  save: []
  remove: [reward: RewardDefinitionRow]
  issueKey: [rewardId: number, usageMode: RewardClaimUsageMode, accessMode: RewardClaimAccessMode, publicPlacement: string]
  revokeKey: [keyId: number]
  clearIssuedCode: []
}>()

const usageMode = ref<RewardClaimUsageMode>('single')
const accessMode = ref<RewardClaimAccessMode>('code')
const publicPlacement = ref('')
const copied = ref(false)

const selectedReward = computed(() => props.rewards.find((entry) => entry.id === props.draft.id) ?? null)
const selectedKeys = computed(() => props.claimKeys
  .filter((entry) => entry.rewardId === props.draft.id)
  .sort((left, right) => right.createdAt - left.createdAt))
const hasBadge = computed(() => props.draft.badgeId > 0)
const hasCurrency = computed(() => props.draft.currencyAmount > 0 && props.draft.currencyCode !== '')
const validPayload = computed(() => hasBadge.value || hasCurrency.value)
const hasExistingClaims = computed(() => (selectedReward.value?.claimsCount ?? 0) > 0)
const validPlacement = computed(() => /^[a-z][a-z0-9._-]{0,63}$/.test(publicPlacement.value.trim()))

watch(() => props.issuedCode, () => { copied.value = false })
watch(() => props.draft.currencyCode, (code) => {
  if (!code) props.draft.currencyAmount = 0
})

function issueKey(): void {
  if (!selectedReward.value || props.loading) return
  const placement = accessMode.value === 'public' ? publicPlacement.value.trim() : ''
  if (accessMode.value === 'public' && !validPlacement.value) return
  emit('issueKey', selectedReward.value.id, usageMode.value, accessMode.value, placement)
}

function revokeKey(entry: RewardClaimKeyRow): void {
  if (!entry.enabled || !window.confirm(t('theme.foxengine.admin.rewards.067', [entry.rewardName]))) return
  emit('revokeKey', entry.id)
}

async function copyIssuedCode(): Promise<void> {
  const token = props.issuedCode?.token
  if (!token) return
  try {
    await navigator.clipboard.writeText(token)
    copied.value = true
  } catch {
    const area = document.createElement('textarea')
    area.value = token
    area.style.position = 'fixed'
    area.style.opacity = '0'
    document.body.appendChild(area)
    area.select()
    copied.value = document.execCommand('copy')
    area.remove()
  }
}

function compositionLabel(reward: RewardDefinitionRow | RewardClaimKeyRow): string {
  const parts: string[] = []
  if (reward.badgeId > 0) parts.push(t('theme.foxengine.admin.rewards.068', [reward.badgeName]))
  if (reward.currencyAmount > 0 && reward.currencyCode) parts.push(`${reward.currencyAmount} ${reward.currencyCode}`)
  return parts.join(' + ') || t('theme.foxengine.admin.rewards.069')
}
</script>

<template>
  <section class="admin-rewards">
    <header class="admin-rewards__header">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.rewards.001') }}</span>
        <h2>{{ t('theme.foxengine.admin.rewards.002') }}</h2>
        <p>{{ t('theme.foxengine.admin.rewards.003') }}</p>
      </div>
      <button class="button button--primary" type="button" @click="emit('create')">
        <i class="fa-solid fa-plus" aria-hidden="true" />
        <span>{{ t('theme.foxengine.admin.rewards.004') }}</span>
      </button>
    </header>

    <div class="admin-rewards__workspace">
      <aside class="admin-rewards__list">
        <button
          v-for="reward in rewards"
          :key="reward.id"
          type="button"
          :class="{ active: draft.id === reward.id, 'is-disabled': !reward.enabled }"
          @click="emit('edit', reward)"
        >
          <span class="admin-rewards__list-icon">
            <img v-if="reward.badgeImage" :src="reward.badgeImage" alt="">
            <i v-else-if="reward.badgeId" class="fa-solid fa-award" aria-hidden="true" />
            <i v-else class="fa-solid fa-coins" aria-hidden="true" />
          </span>
          <span>
            <strong>{{ reward.rewardName }}</strong>
            <small>{{ compositionLabel(reward) }}</small>
          </span>
          <span class="admin-rewards__status" :class="{ active: reward.enabled }">{{ reward.enabled ? t('theme.foxengine.admin.rewards.005') : t('theme.foxengine.admin.rewards.006') }}</span>
        </button>
        <p v-if="!rewards.length" class="admin-rewards__empty">{{ t('theme.foxengine.admin.rewards.007') }}</p>
      </aside>

      <div class="admin-rewards__editor">
        <form class="admin-reward-form" @submit.prevent="emit('save')">
          <header>
            <div>
              <span class="eyebrow">{{ t('theme.foxengine.admin.rewards.008') }}</span>
              <h3>{{ draft.id ? draft.rewardName || t('theme.foxengine.admin.rewards.009') : t('theme.foxengine.admin.rewards.004') }}</h3>
              <p>{{ t('theme.foxengine.admin.rewards.010') }}</p>
            </div>
            <UiCheckbox
              v-model="draft.enabled"
              class="admin-reward-form__enabled"
              variant="switch"
              :label="t('theme.foxengine.admin.rewards.011')"
              :description="t('theme.foxengine.admin.rewards.012')"
            />
          </header>

          <div class="admin-reward-form__grid">
            <label class="admin-reward-form__wide">
              <span>{{ t('theme.foxengine.admin.rewards.013') }}</span>
              <input v-model.trim="draft.rewardName" type="text" maxlength="160" required :placeholder="t('theme.foxengine.admin.rewards.014')">
            </label>
            <label class="admin-reward-form__wide">
              <span>{{ t('theme.foxengine.admin.rewards.015') }}</span>
              <textarea v-model.trim="draft.description" maxlength="4000" rows="3" :placeholder="t('theme.foxengine.admin.rewards.016')" />
            </label>
          </div>

          <fieldset class="admin-reward-components">
            <legend>{{ t('theme.foxengine.admin.rewards.017') }}</legend>
            <label>
              <span>{{ t('theme.foxengine.admin.rewards.018') }}</span>
              <select v-model.number="draft.badgeId">
                <option :value="0">{{ t('theme.foxengine.admin.rewards.019') }}</option>
                <option v-for="badge in badges" :key="badge.id" :value="badge.id">{{ badge.badgeName }}</option>
              </select>
              <small>{{ t('theme.foxengine.admin.rewards.020') }}</small>
            </label>
            <label>
              <span>{{ t('theme.foxengine.admin.rewards.021') }}</span>
              <select v-model="draft.currencyCode">
                <option value="">{{ t('theme.foxengine.admin.rewards.022') }}</option>
                <option value="units">{{ t('theme.foxengine.admin.rewards.023') }}</option>
                <option value="crystals">{{ t('theme.foxengine.admin.rewards.024') }}</option>
              </select>
            </label>
            <label v-if="draft.currencyCode">
              <span>{{ t('theme.foxengine.admin.rewards.025') }}</span>
              <input v-model.number="draft.currencyAmount" type="number" min="1" max="9007199254740991" step="1" inputmode="numeric">
              <small>{{ t('theme.foxengine.admin.rewards.026') }}</small>
            </label>
          </fieldset>

          <p v-if="hasExistingClaims" class="admin-reward-form__notice">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true" />
            <span>{{ t('theme.foxengine.admin.rewards.027', [selectedReward?.claimsCount ?? 0]) }}</span>
          </p>
          <p v-if="!validPayload" class="admin-reward-form__validation" role="alert"> {{ t('theme.foxengine.admin.rewards.028') }} </p>

          <footer>
            <button
              v-if="selectedReward"
              class="button admin-content-delete-page"
              type="button"
              :disabled="loading || selectedReward.claimsCount > 0"
              :title="selectedReward.claimsCount > 0 ? t('theme.foxengine.admin.rewards.029') : undefined"
              @click="emit('remove', selectedReward)"
            >
              <i class="fa-solid fa-trash-can" aria-hidden="true" />
              <span>{{ t('theme.foxengine.admin.rewards.030') }}</span>
            </button>
            <button class="button button--primary" type="submit" :disabled="loading || !validPayload || !draft.rewardName.trim()">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
              <span>{{ t('theme.foxengine.admin.rewards.031') }}</span>
            </button>
          </footer>
        </form>

        <section v-if="selectedReward" class="admin-reward-keys">
          <header>
            <div>
              <span class="eyebrow">{{ t('theme.foxengine.admin.rewards.032') }}</span>
              <h3>{{ t('theme.foxengine.admin.rewards.033') }}</h3>
              <p>{{ t('theme.foxengine.admin.rewards.034') }}</p>
            </div>
            <dl>
              <div><dt>{{ t('theme.foxengine.admin.rewards.035') }}</dt><dd>{{ selectedReward.claimsCount }}</dd></div>
              <div><dt>{{ t('theme.foxengine.admin.rewards.036') }}</dt><dd>{{ selectedReward.keysCount }}</dd></div>
            </dl>
          </header>

          <div class="admin-claim-issuer">
            <label>
              <span>{{ t('theme.foxengine.admin.rewards.037') }}</span>
              <select v-model="accessMode">
                <option value="code">{{ t('theme.foxengine.admin.rewards.038') }}</option>
                <option value="public">{{ t('theme.foxengine.admin.rewards.039') }}</option>
              </select>
            </label>
            <label v-if="accessMode === 'code'">
              <span>{{ t('theme.foxengine.admin.rewards.040') }}</span>
              <select v-model="usageMode">
                <option value="single">{{ t('theme.foxengine.admin.rewards.041') }}</option>
                <option value="reusable">{{ t('theme.foxengine.admin.rewards.042') }}</option>
              </select>
            </label>
            <label v-else>
              <span>{{ t('theme.foxengine.admin.rewards.043') }}</span>
              <input v-model.trim="publicPlacement" type="text" maxlength="64" pattern="[a-z][a-z0-9._-]{0,63}" placeholder="welcome-native" autocomplete="off">
              <small>{{ t('theme.foxengine.admin.rewards.044') }}</small>
            </label>
            <button class="button button--primary" type="button" :disabled="loading || !selectedReward.enabled || (accessMode === 'public' && !validPlacement)" @click="issueKey">
              <i class="fa-solid fa-key" aria-hidden="true" />
              <span>{{ accessMode === 'public' ? t('theme.foxengine.admin.rewards.045') : t('theme.foxengine.admin.rewards.046') }}</span>
            </button>
          </div>

          <section v-if="issuedCode && issuedCode.entry.rewardId === selectedReward.id" class="admin-issued-code" role="status">
            <header>
              <div>
                <strong>{{ issuedCode.entry.accessMode === 'public' ? t('theme.foxengine.admin.rewards.047') : t('theme.foxengine.admin.rewards.048') }}</strong>
                <small v-if="issuedCode.entry.accessMode === 'public'">{{ t('theme.foxengine.admin.rewards.049') }} {{ issuedCode.entry.publicPlacement }}.</small>
                <small v-else>{{ t('theme.foxengine.admin.rewards.050') }}</small>
              </div>
              <button type="button" :aria-label="t('theme.foxengine.admin.rewards.051')" @click="emit('clearIssuedCode')">×</button>
            </header>
            <div v-if="issuedCode.token" class="admin-issued-code__value">
              <code>{{ issuedCode.token }}</code>
              <button class="button button--ghost" type="button" @click="copyIssuedCode">
                <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true" />
                <span>{{ copied ? t('theme.foxengine.admin.rewards.052') : t('theme.foxengine.admin.rewards.053') }}</span>
              </button>
            </div>
            <div v-else class="admin-issued-code__value"><code>{{ t('theme.foxengine.admin.rewards.054') }}{{ issuedCode.entry.tokenHint }}</code></div>
          </section>

          <div v-if="selectedKeys.length" class="admin-claim-key-rows">
            <article v-for="entry in selectedKeys" :key="entry.id" class="admin-claim-key-row" :class="{ 'is-disabled': !entry.enabled }">
              <div class="admin-claim-key-row__identity">
                <span class="admin-claim-key-row__mode" :class="entry.accessMode === 'public' ? 'is-public' : `is-${entry.usageMode}`">
                  {{ entry.accessMode === 'public' ? t('theme.foxengine.admin.rewards.056') : entry.usageMode === 'reusable' ? t('theme.foxengine.admin.rewards.057') : t('theme.foxengine.admin.rewards.041') }}
                </span>
                <strong>••••••{{ entry.tokenHint }}</strong>
                <small>#{{ entry.id }} · {{ formatTimestamp(entry.createdAt) }}</small>
                <small v-if="entry.publicPlacement">placement: {{ entry.publicPlacement }}</small>
              </div>
              <dl>
                <div><dt>{{ t('theme.foxengine.admin.rewards.058') }}</dt><dd>{{ entry.usesCount }}</dd></div>
                <div><dt>{{ t('theme.foxengine.admin.rewards.059') }}</dt><dd>{{ formatTimestamp(entry.lastClaimedAt || undefined) }}</dd></div>
                <div><dt>{{ t('theme.foxengine.admin.rewards.060') }}</dt><dd>{{ entry.enabled ? t('theme.foxengine.admin.rewards.061') : t('theme.foxengine.admin.rewards.062') }}</dd></div>
                <div><dt>{{ t('theme.foxengine.admin.rewards.063') }}</dt><dd>{{ compositionLabel(entry) }}</dd></div>
              </dl>
              <button class="button admin-content-delete-page" type="button" :disabled="!entry.enabled || loading" @click="revokeKey(entry)">
                <i class="fa-solid fa-ban" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.rewards.064') }}</span>
              </button>
            </article>
          </div>
          <div v-else class="admin-content-empty-page admin-content-empty-page--compact">
            <i class="fa-solid fa-key" aria-hidden="true" />
            <strong>{{ t('theme.foxengine.admin.rewards.065') }}</strong>
            <p>{{ t('theme.foxengine.admin.rewards.066') }}</p>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>
