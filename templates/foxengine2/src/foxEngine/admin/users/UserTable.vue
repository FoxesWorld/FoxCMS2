<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue'
import type { UserRow } from '@modules/AdminPanel/client/useAdminPanel'
import UserAvatar from './UserAvatar.vue'
import { userBadgeAssignments } from '@/domain/userBadges'

const props = defineProps<{
  users: UserRow[]
  search: string
  selected: UserRow | null
  loading: boolean
  formatTimestamp: (value?: number | string) => string
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  search: []
  edit: [user: UserRow]
}>()

const groupCount = computed(() => new Set(props.users.map((user) => user.groupTag).filter(Boolean)).size)
const normalizedSearch = computed(() => props.search.trim())
let searchTimer: number | undefined

function update(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  emit('update:search', value)
  if (searchTimer) window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => emit('search'), 320)
}

function submitSearch(): void {
  if (searchTimer) window.clearTimeout(searchTimer)
  searchTimer = undefined
  emit('search')
}

function clearSearch(): void {
  if (searchTimer) window.clearTimeout(searchTimer)
  searchTimer = undefined
  emit('update:search', '')
  emit('search')
}

function badgeCount(user: UserRow): number {
  return userBadgeAssignments(user.badges).length
}

onBeforeUnmount(() => {
  if (searchTimer) window.clearTimeout(searchTimer)
})

function groupStyle(user: UserRow): Record<string, string> {
  return { '--admin-user-group': String(user.groupColor || 'var(--color-accent)') }
}
</script>

<template>
  <aside class="admin-users__master">
    <header class="admin-users__master-header">
      <div>
        <span class="eyebrow">User directory</span>
        <h2>Пользователи</h2>
        <p>Поиск и выбор учётной записи для редактирования.</p>
      </div>
      <div class="admin-users__counts" aria-label="Статистика результата">
        <span><strong>{{ users.length }}</strong> найдено</span>
        <span><strong>{{ groupCount }}</strong> групп</span>
      </div>
    </header>

    <form class="admin-user-search" role="search" @submit.prevent="submitSearch">
      <label>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
        <input
          :value="search"
          type="search"
          autocomplete="off"
          placeholder="Логин, email, имя, UUID или бейдж"
          aria-label="Поиск пользователей"
          @input="update"
        >
        <button v-if="search" type="button" title="Очистить поиск" aria-label="Очистить поиск" @click="clearSearch">
          <i class="fa-solid fa-xmark" aria-hidden="true" />
        </button>
      </label>
      <button class="button button--primary" type="submit" :disabled="loading">
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-magnifying-glass'" aria-hidden="true" />
        <span>{{ loading ? 'Поиск…' : 'Найти' }}</span>
      </button>
    </form>
    <div class="admin-user-search__status" aria-live="polite">
      <span v-if="normalizedSearch"><i class="fa-solid fa-magnifying-glass" aria-hidden="true" />По запросу «{{ normalizedSearch }}»</span>
      <span v-else><i class="fa-solid fa-users" aria-hidden="true" />Последние активные пользователи</span>
      <strong>{{ users.length }}</strong>
    </div>

    <div v-if="users.length" class="admin-user-list" role="list" :aria-busy="loading">
      <button
        v-for="user in users"
        :key="user.uuid"
        type="button"
        role="listitem"
        class="admin-user-card"
        :class="{ 'is-selected': selected?.uuid === user.uuid }"
        :style="groupStyle(user)"
        @click="emit('edit', user)"
      >
        <UserAvatar :src="user.profilePhoto" :name="user.realname" :login="user.login" size="medium" />
        <span class="admin-user-card__body">
          <span class="admin-user-card__identity">
            <strong>{{ user.realname || user.login }}</strong>
            <small>@{{ user.login }}</small>
          </span>
          <span class="admin-user-card__labels">
            <span class="admin-user-card__group">
              <i class="fa-solid fa-circle" aria-hidden="true" />
              {{ user.groupName || user.groupTag }}
            </span>
            <span v-if="badgeCount(user)" class="admin-user-card__badges">
              <i class="fa-solid fa-award" aria-hidden="true" />{{ badgeCount(user) }}
            </span>
          </span>
          <span class="admin-user-card__meta">
            <span><i class="fa-solid fa-envelope" aria-hidden="true" />{{ user.email || 'Email не указан' }}</span>
            <span><i class="fa-solid fa-clock" aria-hidden="true" />{{ formatTimestamp(user.last_date) }}</span>
          </span>
        </span>
        <i class="fa-solid fa-chevron-right admin-user-card__arrow" aria-hidden="true" />
      </button>
    </div>

    <div v-else class="admin-users__empty-list">
      <i class="fa-solid fa-users" aria-hidden="true" />
      <strong>Пользователи не найдены</strong>
      <p>Измените поисковый запрос или очистите фильтр.</p>
      <button v-if="search" class="button button--ghost" type="button" @click="clearSearch">Показать всех</button>
    </div>
  </aside>
</template>
