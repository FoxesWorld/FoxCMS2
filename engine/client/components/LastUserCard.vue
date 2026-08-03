<script setup lang="ts">
import { t } from '@/i18n'

import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { foxesApi } from '@/api'

interface LastUser {
  login: string
  realname?: string
  profilePhoto?: string
  reg_date?: string | number
  colorScheme?: string
}

const router = useRouter()
const user = ref<LastUser | null>(null)
const loading = ref(true)
const error = ref(false)

function formatDate(value?: string | number): string {
  if (!value) return t('engine.lastusercard.003')
  const numeric = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value
  const date = new Date(typeof numeric === 'number' && numeric < 10_000_000_000 ? numeric * 1000 : numeric)
  return Number.isNaN(date.getTime()) ? t('engine.lastusercard.003') : new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'long' }).format(date)
}

onMounted(async () => {
  try {
    user.value = await foxesApi.post<LastUser>({ userAction: 'lastUser' })
  } catch (requestError) {
    console.warn('[FoxesCraft] Last-user request failed', requestError)
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="sidebar-placeholder">{{ t('engine.lastusercard.001') }}</div>
  <div v-else-if="error || !user" class="sidebar-placeholder">{{ t('engine.lastusercard.002') }}</div>
  <button v-else class="last-user" type="button" @click="router.push({ name: 'profile', params: { value: user.login } })">
    <img v-if="user.profilePhoto" :src="user.profilePhoto" :alt="user.login">
    <span v-else class="last-user__fallback">{{ user.login.slice(0, 1).toUpperCase() }}</span>
    <span><strong>{{ user.realname || user.login }}</strong><small>@{{ user.login }} · {{ formatDate(user.reg_date) }}</small></span>
  </button>
</template>
