<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@/i18n'
import type { AchievementAdminPlayer, AchievementAdminServer } from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  available: boolean
  servers: AchievementAdminServer[]
  players: AchievementAdminPlayer[]
  serverId: string
  search: string
  loading: boolean
}>()

const emit = defineEmits<{
  selectServer: [serverId: string]
  'update:search': [value: string]
  search: []
  reload: []
  clearServer: []
  clearPlayer: [player: AchievementAdminPlayer]
}>()

const selectedServer = computed(() => props.servers.find((entry) => entry.serverId === props.serverId) ?? null)
const totalServerRows = computed(() => {
  const server = selectedServer.value
  return server ? server.definitions + server.progressRows + server.events : 0
})

function playerLabel(player: AchievementAdminPlayer): string {
  return player.login || player.realname || player.uuid
}
</script>

<template>
  <section class="admin-achievements">
    <header class="admin-achievements__hero">
      <div class="admin-achievements__hero-copy">
        <span class="eyebrow">{{ t('theme.foxengine.admin.achievements.001') }}</span>
        <h2>{{ t('theme.foxengine.admin.achievements.002') }}</h2>
        <p>{{ t('theme.foxengine.admin.achievements.003') }}</p>
      </div>
      <span class="admin-achievements__status" :class="{ 'is-unavailable': !available }">
        <i class="fa-solid" :class="available ? 'fa-database' : 'fa-triangle-exclamation'" aria-hidden="true" />
        {{ available ? t('theme.foxengine.admin.achievements.004') : t('theme.foxengine.admin.achievements.005') }}
      </span>
    </header>

    <div v-if="!available" class="admin-achievements__empty admin-achievements__empty--error" role="alert">
      <i class="fa-solid fa-database" aria-hidden="true" />
      <div>
        <strong>{{ t('theme.foxengine.admin.achievements.006') }}</strong>
        <p>{{ t('theme.foxengine.admin.achievements.007') }}</p>
      </div>
    </div>

    <template v-else>
      <section class="admin-achievements__context">
        <label>
          <span>{{ t('theme.foxengine.admin.achievements.008') }}</span>
          <select
            :value="serverId"
            :disabled="loading || servers.length === 0"
            @change="emit('selectServer', ($event.target as HTMLSelectElement).value)"
          >
            <option v-if="servers.length === 0" value="">{{ t('theme.foxengine.admin.achievements.009') }}</option>
            <option v-for="server in servers" :key="server.serverId" :value="server.serverId">
              {{ server.serverId }}
            </option>
          </select>
        </label>
        <button class="button button--ghost" type="button" :disabled="loading" @click="emit('reload')">
          <i class="fa-solid fa-rotate" :class="{ 'admin-achievements__spin': loading }" aria-hidden="true" />
          <span>{{ t('theme.foxengine.admin.achievements.010') }}</span>
        </button>
      </section>

      <div v-if="selectedServer" class="admin-achievements__metrics" :aria-label="t('theme.foxengine.admin.achievements.011')">
        <article>
          <i class="fa-solid fa-trophy" aria-hidden="true" />
          <span>{{ t('theme.foxengine.admin.achievements.012') }}</span>
          <strong>{{ selectedServer.definitions }}</strong>
        </article>
        <article>
          <i class="fa-solid fa-chart-simple" aria-hidden="true" />
          <span>{{ t('theme.foxengine.admin.achievements.013') }}</span>
          <strong>{{ selectedServer.progressRows }}</strong>
        </article>
        <article>
          <i class="fa-solid fa-users" aria-hidden="true" />
          <span>{{ t('theme.foxengine.admin.achievements.014') }}</span>
          <strong>{{ selectedServer.players }}</strong>
        </article>
        <article>
          <i class="fa-solid fa-clock-rotate-left" aria-hidden="true" />
          <span>{{ t('theme.foxengine.admin.achievements.015') }}</span>
          <strong>{{ selectedServer.events }}</strong>
        </article>
      </div>

      <div v-if="selectedServer" class="admin-achievements__notice">
        <i class="fa-solid fa-circle-info" aria-hidden="true" />
        <p>{{ t('theme.foxengine.admin.achievements.016') }}</p>
      </div>

      <div v-if="selectedServer" class="admin-achievements__workspace">
        <section class="admin-achievements__danger-card admin-achievements__danger-card--server">
          <header>
            <span class="admin-achievements__danger-icon"><i class="fa-solid fa-server" aria-hidden="true" /></span>
            <div>
              <span class="eyebrow">{{ t('theme.foxengine.admin.achievements.017') }}</span>
              <h3>{{ t('theme.foxengine.admin.achievements.018') }}</h3>
              <p>{{ t('theme.foxengine.admin.achievements.019') }}</p>
            </div>
          </header>
          <dl>
            <div><dt>{{ t('theme.foxengine.admin.achievements.020') }}</dt><dd>{{ selectedServer.serverId }}</dd></div>
            <div><dt>{{ t('theme.foxengine.admin.achievements.021') }}</dt><dd>{{ totalServerRows }}</dd></div>
          </dl>
          <button
            class="button admin-achievements__danger-button"
            type="button"
            :disabled="loading || totalServerRows === 0"
            @click="emit('clearServer')"
          >
            <i class="fa-solid fa-trash-can" aria-hidden="true" />
            <span>{{ t('theme.foxengine.admin.achievements.022') }}</span>
          </button>
        </section>

        <section class="admin-achievements__players">
          <header>
            <div>
              <span class="eyebrow">{{ t('theme.foxengine.admin.achievements.023') }}</span>
              <h3>{{ t('theme.foxengine.admin.achievements.024') }}</h3>
              <p>{{ t('theme.foxengine.admin.achievements.025') }}</p>
            </div>
            <form class="admin-achievements__search" @submit.prevent="emit('search')">
              <label>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true" />
                <input
                  :value="search"
                  type="search"
                  maxlength="160"
                  :placeholder="t('theme.foxengine.admin.achievements.026')"
                  @input="emit('update:search', ($event.target as HTMLInputElement).value)"
                >
              </label>
              <button class="button button--primary" type="submit" :disabled="loading || !serverId">
                {{ t('theme.foxengine.admin.achievements.027') }}
              </button>
            </form>
          </header>

          <div v-if="players.length" class="admin-achievements__player-list">
            <article v-for="player in players" :key="player.uuid" class="admin-achievements__player">
              <div class="admin-achievements__player-identity">
                <span class="admin-achievements__player-avatar" aria-hidden="true">
                  <i class="fa-solid fa-user" />
                </span>
                <div>
                  <strong>{{ playerLabel(player) }}</strong>
                  <small v-if="player.realname && player.realname !== player.login">{{ player.realname }}</small>
                  <code>{{ player.uuid }}</code>
                </div>
              </div>
              <dl>
                <div><dt>{{ t('theme.foxengine.admin.achievements.028') }}</dt><dd>{{ player.completedCount }}</dd></div>
                <div><dt>{{ t('theme.foxengine.admin.achievements.029') }}</dt><dd>{{ player.progressRows }}</dd></div>
                <div><dt>{{ t('theme.foxengine.admin.achievements.030') }}</dt><dd>{{ player.events }}</dd></div>
              </dl>
              <button
                class="button admin-achievements__danger-button admin-achievements__danger-button--compact"
                type="button"
                :disabled="loading"
                @click="emit('clearPlayer', player)"
              >
                <i class="fa-solid fa-user-slash" aria-hidden="true" />
                <span>{{ t('theme.foxengine.admin.achievements.031') }}</span>
              </button>
            </article>
          </div>

          <div v-else class="admin-achievements__empty">
            <i class="fa-solid fa-user-check" aria-hidden="true" />
            <div>
              <strong>{{ t('theme.foxengine.admin.achievements.032') }}</strong>
              <p>{{ search ? t('theme.foxengine.admin.achievements.033') : t('theme.foxengine.admin.achievements.034') }}</p>
            </div>
          </div>
        </section>
      </div>

      <div v-else class="admin-achievements__empty">
        <i class="fa-solid fa-trophy" aria-hidden="true" />
        <div>
          <strong>{{ t('theme.foxengine.admin.achievements.035') }}</strong>
          <p>{{ t('theme.foxengine.admin.achievements.036') }}</p>
        </div>
      </div>
    </template>
  </section>
</template>
