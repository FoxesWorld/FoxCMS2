<script setup lang="ts">
import { computed, ref } from 'vue'
import { appBootstrap } from '@/app/context'
import { themeAsset } from '@/domain/bootstrap'
import { normalizedBadgeKey, userBadgeAssignments, type UserBadgeAssignment } from '@/domain/userBadges'
import type { JsonValue } from '@/forms/json-form'
import type { AdminBadgeOption } from '@modules/AdminPanel/client/useAdminPanel'

interface ManagedBadge extends AdminBadgeOption {
  assignment: UserBadgeAssignment | null
  legacy: boolean
}

const props = defineProps<{
  modelValue: JsonValue
  options: AdminBadgeOption[]
  disabled?: boolean
}>()
const emit = defineEmits<{
  grant: [payload: { badgeId: number; reason: string }]
  revoke: [payload: { badgeName: string; reason: string }]
}>()

const tab = ref<'assigned' | 'available'>('assigned')
const search = ref('')
const reason = ref('')
const reasonError = ref('')
const reasonField = ref<HTMLTextAreaElement | null>(null)
const assignments = computed(() => userBadgeAssignments(props.modelValue))
const reasonValue = computed(() => reason.value.trim())
const canAct = computed(() => reasonValue.value.length >= 3 && reasonValue.value.length <= 500)

const assignmentMap = computed(() => new Map(
  assignments.value.map((assignment) => [normalizedBadgeKey(assignment.badgeName), assignment]),
))
const catalogMap = computed(() => new Map(
  props.options.map((badge) => [normalizedBadgeKey(badge.badgeName), badge]),
))
const assignedBadges = computed<ManagedBadge[]>(() => assignments.value.map((assignment) => {
  const configured = catalogMap.value.get(normalizedBadgeKey(assignment.badgeName))
  return configured
    ? { ...configured, assignment, legacy: false }
    : {
        id: 0,
        badgeName: assignment.badgeName,
        title: assignment.badgeName,
        description: 'Бейдж сохранён в профиле, но отсутствует в текущем каталоге.',
        image: null,
        assignment,
        legacy: true,
      }
}))
const availableBadges = computed<ManagedBadge[]>(() => props.options
  .filter((badge) => !assignmentMap.value.has(normalizedBadgeKey(badge.badgeName)))
  .map((badge) => ({ ...badge, assignment: null, legacy: false })))
const visibleBadges = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('ru')
  const source = tab.value === 'assigned' ? assignedBadges.value : availableBadges.value
  return source
    .filter((badge) => !query || `${badge.title} ${badge.description}`.toLocaleLowerCase('ru').includes(query))
    .sort((left, right) => left.title.localeCompare(right.title, 'ru'))
})

function imageUrl(value: string | null): string {
  const path = String(value ?? '').trim().replaceAll('\\', '/')
  if (!path) return ''
  if (/^(?:https?:|data:|blob:)/i.test(path) || path.startsWith('//') || path.startsWith('/')) return path
  if (path.startsWith('uploads/') || path.startsWith('templates/')) return `/${path}`
  const relative = path.replace(/^assets\//, '')
  return themeAsset(appBootstrap, relative.includes('/') ? relative : `img/badges/${relative}`)
}
function hideImage(event: Event): void { (event.currentTarget as HTMLImageElement).hidden = true }
function initial(value: string): string { return Array.from(value.trim())[0]?.toLocaleUpperCase('ru') ?? 'Б' }
function assignmentLabel(assignment: UserBadgeAssignment | null): string {
  if (!assignment) return 'Доступен для выдачи'
  if (assignment.source === 'admin') return 'Выдан администратором'
  if (assignment.source?.startsWith('public-offer:')) return 'Получен через публичную награду'
  return 'Получен как награда'
}
function acquiredLabel(assignment: UserBadgeAssignment | null): string {
  if (!assignment?.acquiredAt) return ''
  return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' })
    .format(new Date(assignment.acquiredAt * 1000))
}
function validateReason(): boolean {
  if (canAct.value) {
    reasonError.value = ''
    return true
  }
  reasonError.value = reasonValue.value.length < 3
    ? 'Укажите причину операции: минимум 3 символа.'
    : 'Причина операции не должна превышать 500 символов.'
  reasonField.value?.focus()
  return false
}
function grant(badge: ManagedBadge): void {
  if (badge.id <= 0 || !validateReason()) return
  emit('grant', { badgeId: badge.id, reason: reasonValue.value })
}
function revoke(badge: ManagedBadge): void {
  if (!validateReason()) return
  if (!window.confirm(`Отозвать бейдж «${badge.title}»? Валютные начисления и история наград останутся без изменений.`)) return
  emit('revoke', { badgeName: badge.badgeName, reason: reasonValue.value })
}
</script>

<template>
  <div class="admin-badge-editor" :aria-busy="disabled || undefined">
    <div class="admin-badge-editor__notice">
      <i class="fa-solid fa-shield-halved" aria-hidden="true" />
      <div>
        <strong>Административное управление бейджами</strong>
        <span>Выдача и отзыв изменяют только знаки профиля. Баланс, ключи, определения наград и журнал их погашения не изменяются.</span>
      </div>
    </div>

    <label class="admin-badge-editor__reason">
      <span>Причина операции</span>
      <textarea
        ref="reasonField"
        v-model="reason"
        rows="2"
        minlength="3"
        maxlength="500"
        :disabled="disabled"
        placeholder="Например: награждение за участие в мероприятии или отзыв ошибочно выданного бейджа"
        :aria-invalid="reasonError ? 'true' : undefined"
        @input="reasonError = ''"
      />
      <small :class="{ 'is-invalid': Boolean(reasonError) || (reason.length > 0 && !canAct) }">
        <strong v-if="reasonError">{{ reasonError }}</strong>
        <span v-else>Причина обязательна и попадёт в административный журнал.</span>
        {{ reason.length }}/500
      </small>
    </label>

    <div class="admin-badge-editor__tabs" role="tablist" aria-label="Состояние бейджей пользователя">
      <button
        type="button"
        class="button button--ghost"
        :class="{ active: tab === 'assigned' }"
        role="tab"
        :aria-selected="tab === 'assigned'"
        @click="tab = 'assigned'"
      >
        Полученные <b>{{ assignedBadges.length }}</b>
      </button>
      <button
        type="button"
        class="button button--ghost"
        :class="{ active: tab === 'available' }"
        role="tab"
        :aria-selected="tab === 'available'"
        @click="tab = 'available'"
      >
        Доступные <b>{{ availableBadges.length }}</b>
      </button>
    </div>

    <label class="admin-badge-editor__search">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
      <input v-model.trim="search" type="search" autocomplete="off" placeholder="Поиск бейджа по названию или описанию">
      <button v-if="search" type="button" title="Очистить поиск" @click="search = ''"><i class="fa-solid fa-xmark" aria-hidden="true" /></button>
    </label>

    <div v-if="visibleBadges.length" class="admin-badge-grid">
      <article
        v-for="badge in visibleBadges"
        :key="`${tab}-${badge.badgeName}`"
        class="admin-badge-card"
        :class="{ 'is-assigned': badge.assignment, 'is-legacy': badge.legacy }"
      >
        <span class="admin-badge-card__visual">
          <img v-if="imageUrl(badge.image)" :src="imageUrl(badge.image)" :alt="badge.title" loading="lazy" @error="hideImage">
          <span v-else>{{ initial(badge.title) }}</span>
        </span>
        <span class="admin-badge-card__copy">
          <strong>{{ badge.title }} <i v-if="badge.assignment" class="fa-solid fa-circle-check" aria-hidden="true" /></strong>
          <small>{{ badge.description || 'Описание бейджа не задано.' }}</small>
        </span>
        <span class="admin-badge-card__status">
          <span>{{ assignmentLabel(badge.assignment) }}</span>
          <small v-if="acquiredLabel(badge.assignment)">{{ acquiredLabel(badge.assignment) }}</small>
        </span>
        <button
          v-if="badge.assignment"
          type="button"
          class="button button--ghost admin-badge-card__operation admin-badge-card__operation--revoke"
          :disabled="disabled"
          @click="revoke(badge)"
        >
          <i class="fa-solid fa-trash-can" aria-hidden="true" />
          Отозвать
        </button>
        <button
          v-else
          type="button"
          class="button button--primary admin-badge-card__operation"
          :disabled="disabled || badge.id <= 0"
          @click="grant(badge)"
        >
          <i class="fa-solid fa-plus" aria-hidden="true" />
          Выдать
        </button>
      </article>
    </div>
    <div v-else class="admin-badge-editor__empty">
      <i class="fa-solid fa-award" aria-hidden="true" />
      <strong>{{ search ? 'Бейджи не найдены' : tab === 'assigned' ? 'У пользователя нет бейджей' : 'Все каталоговые бейджи уже выданы' }}</strong>
    </div>
  </div>
</template>
