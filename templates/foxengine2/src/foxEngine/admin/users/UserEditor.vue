<script setup lang="ts">
import { computed } from 'vue'
import { appBootstrap } from '@/app/context'
import { themeAsset } from '@/domain/bootstrap'
import { JsonFormEditor, collectJsonSamples } from '@/forms/json-form'
import type { JsonValue } from '@/forms/json-form'
import type { AdminBadgeOption, GroupOption, UserDraft, UserRow } from '@modules/AdminPanel/client/useAdminPanel'
import UserAvatar from './UserAvatar.vue'
import UserBadgeEditor from './UserBadgeEditor.vue'
import { userBadgeAssignments } from '@/domain/userBadges'
import { balanceCurrencyIconPath, formatBalanceAmount, normalizeBalanceMatrix, type BalanceCurrency } from '@/domain/userBalance'

const props = defineProps<{
  selected: UserRow | null
  draft: UserDraft
  groups: GroupOption[]
  badgeOptions: AdminBadgeOption[]
  samples: UserRow[]
  loading: boolean
}>()

const emit = defineEmits<{
  save: []
  grantBadge: [payload: { badgeId: number; reason: string }]
  revokeBadge: [payload: { badgeName: string; reason: string }]
}>()

type StructuredUserField = 'serversOnline'

const selectedGroup = computed(() => props.groups.find((group) => group.groupTag === props.draft.groupTag) ?? null)
const badgeCount = computed(() => userBadgeAssignments(props.draft.badges).length)
const serverCount = computed(() => valueCount(props.draft.serversOnline))
const balanceMatrix = computed(() => normalizeBalanceMatrix(props.draft.balance))
const balanceIcons = {
  units: themeAsset(appBootstrap, balanceCurrencyIconPath('units')),
  crystals: themeAsset(appBootstrap, balanceCurrencyIconPath('crystals')),
} as const

function samplesFor(field: StructuredUserField): JsonValue[] {
  return collectJsonSamples(props.samples, field)
}

function valueCount(value: JsonValue): number {
  if (Array.isArray(value)) return value.length
  if (value && typeof value === 'object') return Object.keys(value).length
  if (typeof value === 'string' && value.trim() !== '') return 1
  return 0
}

function updateBalanceAmount(code: BalanceCurrency['code'], event: Event): void {
  const input = event.currentTarget as HTMLInputElement
  const amount = Number(input.value)
  const safeAmount = Number.isSafeInteger(amount) && amount >= 0 ? amount : 0
  const matrix = normalizeBalanceMatrix(props.draft.balance)
  props.draft.balance = {
    ...matrix,
    currencies: matrix.currencies.map((currency) => ({
      ...currency,
      amount: currency.code === code ? safeAmount : currency.amount,
    })),
  } as unknown as JsonValue
}

function groupStyle(color?: string): Record<string, string> {
  return { '--admin-user-group': color || 'var(--color-accent)' }
}
</script>

<template>
  <form v-if="selected" class="admin-user-editor" @submit.prevent="emit('save')">
    <header class="admin-user-editor__hero" :style="groupStyle(selectedGroup?.groupColor || selected.groupColor)">
      <UserAvatar :src="selected.profilePhoto" :name="draft.realname" :login="draft.login" size="large" />
      <div class="admin-user-editor__hero-copy">
        <span class="eyebrow">Редактирование пользователя</span>
        <h2>{{ draft.realname || draft.login }}</h2>
        <p>@{{ draft.login }}</p>
        <div class="admin-user-editor__chips">
          <span class="admin-user-editor__group-chip">
            <i class="fa-solid fa-circle" aria-hidden="true" />
            {{ selectedGroup?.groupName || draft.groupTag }}
          </span>
          <span><i class="fa-solid fa-award" aria-hidden="true" />{{ badgeCount }} бейджей</span>
          <span><i class="fa-solid fa-server" aria-hidden="true" />{{ serverCount }} серверов</span>
        </div>
      </div>
      <div class="admin-user-editor__uuid">
        <span>UUID</span>
        <code>{{ selected.uuid }}</code>
      </div>
    </header>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-address-card" aria-hidden="true" /></span>
        <div>
          <h3>Основная информация</h3>
          <p>Данные учётной записи и публичное отображение пользователя.</p>
        </div>
      </header>

      <div class="admin-user-editor__fields">
        <label>
          <span>Логин</span>
          <input v-model.trim="draft.login" type="text" minlength="3" maxlength="64" autocomplete="off" required>
          <small>Используется для входа и упоминаний.</small>
        </label>
        <label>
          <span>Отображаемое имя</span>
          <input v-model.trim="draft.realname" type="text" maxlength="120" autocomplete="off" placeholder="Имя пользователя">
          <small>Если поле пустое, на сайте отображается логин.</small>
        </label>
        <label>
          <span>Email</span>
          <input v-model.trim="draft.email" type="email" maxlength="254" autocomplete="off" required>
        </label>
        <label>
          <span>Статус профиля</span>
          <input v-model.trim="draft.userStatus" type="text" maxlength="255" placeholder="Публичный статус пользователя">
        </label>
      </div>
    </section>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-user-check" aria-hidden="true" /></span>
        <div>
          <h3>Группа и права</h3>
          <p>Группа определяет доступ к функциям сайта и административным возможностям.</p>
        </div>
      </header>

      <div class="admin-user-editor__group-layout">
        <label class="admin-user-editor__group-select">
          <span>Группа пользователя</span>
          <select v-model="draft.groupTag" required>
            <option v-for="group in groups" :key="group.groupTag" :value="group.groupTag">
              {{ group.groupName }} — {{ group.groupTag }}
            </option>
          </select>
        </label>
        <div class="admin-user-editor__group-preview" :style="groupStyle(selectedGroup?.groupColor)">
          <i class="fa-solid fa-shield-halved" aria-hidden="true" />
          <div>
            <strong>{{ selectedGroup?.groupName || draft.groupTag }}</strong>
            <small>{{ selectedGroup?.groupTag || draft.groupTag }}</small>
          </div>
        </div>
      </div>
    </section>

    <section class="admin-user-editor__section admin-user-editor__section--badges">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-award" aria-hidden="true" /></span>
        <div>
          <h3>Персональные бейджи</h3>
          <p>Администратор может выдать или отозвать знак профиля напрямую. Это действие не начисляет валюту, не создаёт награду и не изменяет журнал погашения ключей.</p>
        </div>
      </header>
      <UserBadgeEditor
        :model-value="draft.badges"
        :options="badgeOptions"
        :disabled="loading"
        @grant="emit('grantBadge', $event)"
        @revoke="emit('revokeBadge', $event)"
      />
    </section>

    <section class="admin-user-editor__section admin-user-editor__section--structured">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-table-list" aria-hidden="true" /></span>
        <div>
          <h3>Системные данные</h3>
          <p>Баланс и игровая активность сохранены как структурированные данные профиля.</p>
        </div>
      </header>

      <div class="admin-user-editor__structured-grid">
        <article class="admin-user-data-card admin-user-data-card--balance">
          <header>
            <div><strong>Матрица баланса</strong><small>Units и Crystals закреплены за UUID пользователя</small></div>
            <span>{{ balanceMatrix.currencies.length }}</span>
          </header>
          <div class="admin-balance-matrix">
            <label
              v-for="currency in balanceMatrix.currencies"
              :key="currency.code"
              class="admin-balance-currency"
              :class="`admin-balance-currency--${currency.code}`"
            >
              <span class="admin-balance-currency__icon">
                <img :src="balanceIcons[currency.code]" alt="" aria-hidden="true">
              </span>
              <span class="admin-balance-currency__copy">
                <strong>{{ currency.name }}</strong>
                <small>{{ currency.primary ? 'Основная валюта' : 'Премиальная валюта' }}</small>
              </span>
              <input
                :value="currency.amount"
                type="number"
                min="0"
                max="9007199254740991"
                step="1"
                inputmode="numeric"
                :aria-label="`Баланс ${currency.name}`"
                @input="updateBalanceAmount(currency.code, $event)"
              >
              <output>{{ formatBalanceAmount(currency.amount) }} {{ currency.symbol }}</output>
            </label>
          </div>
        </article>

        <article class="admin-user-data-card admin-user-data-card--wide">
          <header>
            <div><strong>Игровая активность</strong><small>{{ serverCount }} записей серверов</small></div>
            <span>{{ serverCount }}</span>
          </header>
          <JsonFormEditor
            :model-value="draft.serversOnline"
            :samples="samplesFor('serversOnline')"
            label="Игровая активность"
            @update:model-value="draft.serversOnline = $event"
          />
        </article>
      </div>
    </section>

    <footer class="admin-user-editor__footer">
      <div>
        <i class="fa-solid fa-circle-info" aria-hidden="true" />
        <span>Изменения применяются только после сохранения.</span>
      </div>
      <button class="button button--primary" type="submit" :disabled="loading">
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" aria-hidden="true" />
        <span>{{ loading ? 'Сохранение…' : 'Сохранить пользователя' }}</span>
      </button>
    </footer>
  </form>

  <div v-else class="admin-user-editor-empty">
    <span><i class="fa-solid fa-user" aria-hidden="true" /></span>
    <h2>Выберите пользователя</h2>
    <p>Нажмите на учётную запись в списке слева, чтобы открыть её данные.</p>
  </div>
</template>
