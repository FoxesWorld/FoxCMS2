<script setup lang="ts">
import { computed, ref } from 'vue'
import { appBootstrap } from '@/app/context'
import { themeAsset } from '@/domain/bootstrap'
import { normalizedBadgeKey, userBadgeAssignments } from '@/domain/userBadges'
import type { JsonValue } from '@/forms/json-form'
import type { AdminBadgeOption } from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  modelValue: JsonValue
  options: AdminBadgeOption[]
  disabled?: boolean
}>()
const emit = defineEmits<{ grant: [badgeId: number] }>()
const search = ref('')
const showAll = ref(false)
const assignments = computed(() => userBadgeAssignments(props.modelValue))
const assigned = computed(() => new Set(assignments.value.map((badge) => normalizedBadgeKey(badge.badgeName))))
const badges = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('ru')
  const catalog = new Set(props.options.map((badge) => normalizedBadgeKey(badge.badgeName)))
  const legacy: AdminBadgeOption[] = assignments.value
    .filter((badge) => !catalog.has(normalizedBadgeKey(badge.badgeName)))
    .map((badge) => ({
      id: 0,
      badgeName: badge.badgeName,
      title: badge.badgeName,
      description: 'Получен ранее, но отсутствует в текущем каталоге.',
      image: null,
    }))

  return [...props.options, ...legacy]
    .map((badge) => ({
      ...badge,
      selected: assigned.value.has(normalizedBadgeKey(badge.badgeName)),
      legacy: badge.id <= 0,
    }))
    .filter((badge) => (showAll.value || badge.selected)
      && (!query || `${badge.title} ${badge.description}`.toLocaleLowerCase('ru').includes(query)))
    .sort((left, right) => Number(right.selected) - Number(left.selected) || left.title.localeCompare(right.title, 'ru'))
})

function grant(badgeId: number, selected: boolean, legacy: boolean): void {
  if (props.disabled || selected || legacy || badgeId <= 0) return
  emit('grant', badgeId)
}
function imageUrl(value: string | null): string {
  const path = String(value ?? '').trim().replaceAll('\\', '/')
  if (!path) return ''
  if (/^(?:https?:|data:|blob:)/i.test(path) || path.startsWith('//') || path.startsWith('/')) return path
  if (path.startsWith('uploads/') || path.startsWith('templates/')) return `/${path}`
  const relative = path.replace(/^assets\//, '')
  return themeAsset(appBootstrap, relative.includes('/') ? relative : `img/badges/${relative}`)
}
function hideImage(event: Event): void {
  ;(event.currentTarget as HTMLImageElement).hidden = true
}
function initial(value: string): string { return Array.from(value.trim())[0]?.toLocaleUpperCase('ru') ?? 'Б' }
</script>

<template>
  <div class="admin-badge-editor">
    <div class="admin-badge-editor__notice">
      <i class="fa-solid fa-shield-halved" aria-hidden="true" />
      <div>
        <strong>Выдача только через одноразовый код</strong>
        <span>При нажатии сервер создаёт уникальный код, сохраняет только SHA-256-хеш и сразу применяет код к выбранному пользователю.</span>
      </div>
    </div>
    <div class="admin-badge-editor__tabs">
      <button class="button button--ghost" type="button" :class="{ active: !showAll }" @click="showAll = false">
        Полученные <b>{{ assignments.length }}</b>
      </button>
      <button class="button button--ghost" type="button" :class="{ active: showAll }" @click="showAll = true">
        Каталог <b>{{ options.length }}</b>
      </button>
    </div>
    <label class="admin-badge-editor__search">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
      <input v-model.trim="search" type="search" autocomplete="off" placeholder="Поиск бейджа">
      <button v-if="search" type="button" title="Очистить поиск" @click="search = ''"><i class="fa-solid fa-xmark" aria-hidden="true" /></button>
    </label>
    <div v-if="badges.length" class="admin-badge-grid">
      <button
        v-for="badge in badges"
        :key="badge.badgeName"
        class="admin-badge-card"
        :class="{ 'is-assigned': badge.selected, 'is-legacy': badge.legacy }"
        type="button"
        :disabled="disabled || badge.selected || badge.legacy"
        :aria-pressed="badge.selected"
        @click="grant(badge.id, badge.selected, badge.legacy)"
      >
        <span class="admin-badge-card__visual">
          <img v-if="imageUrl(badge.image)" :src="imageUrl(badge.image)" :alt="badge.title" loading="lazy" @error="hideImage">
          <span v-else>{{ initial(badge.title) }}</span>
        </span>
        <span class="admin-badge-card__copy">
          <strong>{{ badge.title }} <i v-if="badge.selected" class="fa-solid fa-circle-check" aria-hidden="true" /></strong>
          <small>{{ badge.description || 'Описание бейджа не задано.' }}</small>
          <em v-if="badge.legacy"><i class="fa-solid fa-circle-exclamation" aria-hidden="true" /> Нет в каталоге</em>
        </span>
        <span class="admin-badge-card__action">
          <i class="fa-solid" :class="badge.selected ? 'fa-check' : badge.legacy ? 'fa-triangle-exclamation' : 'fa-key'" aria-hidden="true" />
          {{ badge.selected ? 'Уже получен' : badge.legacy ? 'Недоступен' : 'Выдать разовым кодом' }}
        </span>
      </button>
    </div>
    <div v-else class="admin-badge-editor__empty">
      <i class="fa-solid fa-award" aria-hidden="true" />
      <strong>{{ showAll ? 'Бейджи не найдены' : 'У пользователя нет полученных бейджей' }}</strong>
    </div>
  </div>
</template>
