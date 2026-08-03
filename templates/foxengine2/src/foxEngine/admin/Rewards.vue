<script setup lang="ts">
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
const payloadLocked = computed(() => (selectedReward.value?.claimsCount ?? 0) > 0)
const validPlacement = computed(() => /^[a-z][a-z0-9._-]{0,63}$/.test(publicPlacement.value.trim()))

watch(() => props.issuedCode, () => { copied.value = false })

function issueKey(): void {
  if (!selectedReward.value || props.loading) return
  const placement = accessMode.value === 'public' ? publicPlacement.value.trim() : ''
  if (accessMode.value === 'public' && !validPlacement.value) return
  emit('issueKey', selectedReward.value.id, usageMode.value, accessMode.value, placement)
}

function revokeKey(entry: RewardClaimKeyRow): void {
  if (!entry.enabled || !window.confirm(`Отозвать ключ награды «${entry.rewardName}»?`)) return
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
  if (reward.badgeId > 0) parts.push(`бейдж «${reward.badgeName}»`)
  if (reward.currencyAmount > 0 && reward.currencyCode) parts.push(`${reward.currencyAmount} ${reward.currencyCode}`)
  return parts.join(' + ') || 'конфигурация отсутствует'
}
</script>

<template>
  <section class="admin-rewards">
    <header class="admin-rewards__header">
      <div>
        <span class="eyebrow">Reward definitions</span>
        <h2>Награды</h2>
        <p>Награда — отдельная конфигурация выдачи. Она может содержать бейдж, валюту либо оба компонента. Бейджи сами по себе не участвуют в выдаче.</p>
      </div>
      <button class="button button--primary" type="button" @click="emit('create')">
        <i class="fa-solid fa-plus" aria-hidden="true" />
        <span>Новая награда</span>
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
          <span class="admin-rewards__status" :class="{ active: reward.enabled }">{{ reward.enabled ? 'Активна' : 'Отключена' }}</span>
        </button>
        <p v-if="!rewards.length" class="admin-rewards__empty">Награды ещё не созданы.</p>
      </aside>

      <div class="admin-rewards__editor">
        <form class="admin-reward-form" @submit.prevent="emit('save')">
          <header>
            <div>
              <span class="eyebrow">Точная конфигурация</span>
              <h3>{{ draft.id ? draft.rewardName || 'Награда' : 'Новая награда' }}</h3>
              <p>Сначала сохраните состав награды, затем выпустите для неё криптографический ключ.</p>
            </div>
            <UiCheckbox
              v-model="draft.enabled"
              class="admin-reward-form__enabled"
              variant="switch"
              label="Награда активна"
              description="Отключённую награду нельзя получить и для неё нельзя выпускать новые ключи."
            />
          </header>

          <div class="admin-reward-form__grid">
            <label class="admin-reward-form__wide">
              <span>Название награды</span>
              <input v-model.trim="draft.rewardName" type="text" maxlength="160" required placeholder="Награда раннего возрождения">
            </label>
            <label class="admin-reward-form__wide">
              <span>Описание</span>
              <textarea v-model.trim="draft.description" maxlength="4000" rows="3" placeholder="Что выдаётся и по какой причине" />
            </label>
          </div>

          <fieldset class="admin-reward-components">
            <legend>Состав награды</legend>
            <label>
              <span>Бейдж — необязательно</span>
              <select v-model.number="draft.badgeId" :disabled="payloadLocked">
                <option :value="0">Без бейджа</option>
                <option v-for="badge in badges" :key="badge.id" :value="badge.id">{{ badge.badgeName }}</option>
              </select>
              <small>Выбор бейджа здесь не изменяет сам каталог бейджей.</small>
            </label>
            <label>
              <span>Валюта — необязательно</span>
              <select v-model="draft.currencyCode" :disabled="payloadLocked">
                <option value="">Без валюты</option>
                <option value="units">Units</option>
                <option value="crystals">Crystals</option>
              </select>
            </label>
            <label>
              <span>Количество валюты</span>
              <input v-model.number="draft.currencyAmount" :disabled="payloadLocked" type="number" min="0" max="9007199254740991" step="1" inputmode="numeric">
              <small>При нуле валютная часть не выдаётся.</small>
            </label>
          </fieldset>

          <p v-if="payloadLocked" class="admin-reward-form__notice">
            Состав зафиксирован первой выдачей и хранится в журнале. Название, описание и состояние можно менять; для другого состава создайте новую награду.
          </p>
          <p v-else-if="!validPayload" class="admin-reward-form__validation" role="alert">
            Выберите хотя бы один компонент: бейдж или положительное количество валюты.
          </p>

          <footer>
            <button
              v-if="selectedReward"
              class="button admin-content-delete-page"
              type="button"
              :disabled="loading || selectedReward.claimsCount > 0"
              :title="selectedReward.claimsCount > 0 ? 'Награды с историей выдач можно только отключить' : undefined"
              @click="emit('remove', selectedReward)"
            >
              <i class="fa-solid fa-trash-can" aria-hidden="true" />
              <span>Удалить</span>
            </button>
            <button class="button button--primary" type="submit" :disabled="loading || !validPayload || !draft.rewardName.trim()">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
              <span>Сохранить награду</span>
            </button>
          </footer>
        </form>

        <section v-if="selectedReward" class="admin-reward-keys">
          <header>
            <div>
              <span class="eyebrow">Cryptographic access</span>
              <h3>Ключи выдачи</h3>
              <p>В БД сохраняется только SHA-256-хеш. Открытый code-ключ показывается один раз; placement-ключ никогда не передаётся браузеру.</p>
            </div>
            <dl>
              <div><dt>Выдач</dt><dd>{{ selectedReward.claimsCount }}</dd></div>
              <div><dt>Ключей</dt><dd>{{ selectedReward.keysCount }}</dd></div>
            </dl>
          </header>

          <div class="admin-claim-issuer">
            <label>
              <span>Тип доступа</span>
              <select v-model="accessMode">
                <option value="code">Открытый код — показать один раз</option>
                <option value="public">Скрытый placement — токен не раскрывать</option>
              </select>
            </label>
            <label v-if="accessMode === 'code'">
              <span>Режим кода</span>
              <select v-model="usageMode">
                <option value="single">Одноразовый</option>
                <option value="reusable">Многоразовый для разных профилей</option>
              </select>
            </label>
            <label v-else>
              <span>Placement</span>
              <input v-model.trim="publicPlacement" type="text" maxlength="64" pattern="[a-z][a-z0-9._-]{0,63}" placeholder="welcome-native" autocomplete="off">
              <small>Один placement может указывать только на одну активную награду.</small>
            </label>
            <button class="button button--primary" type="button" :disabled="loading || !selectedReward.enabled || (accessMode === 'public' && !validPlacement)" @click="issueKey">
              <i class="fa-solid fa-key" aria-hidden="true" />
              <span>{{ accessMode === 'public' ? 'Создать placement-ключ' : 'Выпустить код' }}</span>
            </button>
          </div>

          <section v-if="issuedCode && issuedCode.entry.rewardId === selectedReward.id" class="admin-issued-code" role="status">
            <header>
              <div>
                <strong>{{ issuedCode.entry.accessMode === 'public' ? 'Скрытый placement-ключ создан' : 'Новый код создан' }}</strong>
                <small v-if="issuedCode.entry.accessMode === 'public'">Открытое значение уничтожено. Placement: {{ issuedCode.entry.publicPlacement }}.</small>
                <small v-else>Скопируйте код сейчас. Восстановить его из SHA-256-хеша невозможно.</small>
              </div>
              <button type="button" aria-label="Закрыть" @click="emit('clearIssuedCode')">×</button>
            </header>
            <div v-if="issuedCode.token" class="admin-issued-code__value">
              <code>{{ issuedCode.token }}</code>
              <button class="button button--ghost" type="button" @click="copyIssuedCode">
                <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true" />
                <span>{{ copied ? 'Скопировано' : 'Копировать' }}</span>
              </button>
            </div>
            <div v-else class="admin-issued-code__value"><code>SHA-256 · …{{ issuedCode.entry.tokenHint }}</code></div>
          </section>

          <div v-if="selectedKeys.length" class="admin-claim-key-rows">
            <article v-for="entry in selectedKeys" :key="entry.id" class="admin-claim-key-row" :class="{ 'is-disabled': !entry.enabled }">
              <div class="admin-claim-key-row__identity">
                <span class="admin-claim-key-row__mode" :class="entry.accessMode === 'public' ? 'is-public' : `is-${entry.usageMode}`">
                  {{ entry.accessMode === 'public' ? 'Публичный' : entry.usageMode === 'reusable' ? 'Многоразовый' : 'Одноразовый' }}
                </span>
                <strong>••••••{{ entry.tokenHint }}</strong>
                <small>#{{ entry.id }} · {{ formatTimestamp(entry.createdAt) }}</small>
                <small v-if="entry.publicPlacement">placement: {{ entry.publicPlacement }}</small>
              </div>
              <dl>
                <div><dt>Использований</dt><dd>{{ entry.usesCount }}</dd></div>
                <div><dt>Последнее</dt><dd>{{ formatTimestamp(entry.lastClaimedAt || undefined) }}</dd></div>
                <div><dt>Состояние</dt><dd>{{ entry.enabled ? 'Активен' : 'Отозван' }}</dd></div>
                <div><dt>Состав</dt><dd>{{ compositionLabel(entry) }}</dd></div>
              </dl>
              <button class="button admin-content-delete-page" type="button" :disabled="!entry.enabled || loading" @click="revokeKey(entry)">
                <i class="fa-solid fa-ban" aria-hidden="true" /><span>Отозвать</span>
              </button>
            </article>
          </div>
          <div v-else class="admin-content-empty-page admin-content-empty-page--compact">
            <i class="fa-solid fa-key" aria-hidden="true" />
            <strong>Ключей пока нет</strong>
            <p>Сохраните награду и выпустите для неё code-ключ либо скрытый placement.</p>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>
