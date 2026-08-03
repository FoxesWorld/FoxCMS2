<script setup lang="ts">
import { t } from '@/i18n'

import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { foxesApi } from '@/api'

interface ServerStatus {
  serverName: string
  status: string
  version?: string
  playersOnline?: number
  playersMax?: number
  favicon?: string
}
interface MonitorResponse {
  servers?: ServerStatus[]
  totalPlayersOnline?: number
  totalPlayersMax?: number
  todaysRecord?: number
  message?: string
}

const router = useRouter()
const data = ref<MonitorResponse | null>(null)
const loading = ref(true)
const error = ref(false)
let timer: number | undefined

const servers = computed(() => data.value?.servers ?? [])
const total = computed(() => ({
  online: data.value?.totalPlayersOnline ?? servers.value.reduce((sum, server) => sum + (server.playersOnline ?? 0), 0),
  max: data.value?.totalPlayersMax ?? servers.value.reduce((sum, server) => sum + (server.playersMax ?? 0), 0),
}))
const emptyMessage = computed(() => data.value?.message?.trim()
  || t('engine.servermonitor.008'))

async function refresh(): Promise<void> {
  try {
    data.value = await foxesApi.post<MonitorResponse>({ sysRequest: 'parseMonitor' })
    error.value = false
  } catch (requestError) {
    console.warn('[FoxesCraft] Monitor request failed', requestError)
    error.value = true
  } finally {
    loading.value = false
  }
}

function openServer(serverName: string): void {
  void router.push({ name: 'server', params: { value: serverName } })
}

onMounted(() => {
  void refresh()
  timer = window.setInterval(() => void refresh(), 60_000)
})
onUnmounted(() => { if (timer) window.clearInterval(timer) })
</script>

<template>
  <div class="server-monitor">
    <div v-if="loading" class="sidebar-placeholder">{{ t('engine.servermonitor.001') }}</div>
    <div v-else-if="error && !servers.length" class="sidebar-placeholder">{{ t('engine.servermonitor.002') }}</div>
    <div v-else-if="!servers.length" class="sidebar-placeholder"><strong>{{ t('engine.servermonitor.003') }}</strong><br>{{ emptyMessage }}</div>
    <template v-else>
      <button v-for="server in servers" :key="server.serverName" class="server-row" type="button" @click="openServer(server.serverName)">
        <img v-if="server.favicon" :src="server.favicon" :alt="t('engine.servermonitor.004', [server.serverName])">
        <span v-else class="server-row__fallback">F</span>
        <span class="server-row__identity"><strong>{{ server.serverName }}</strong><small>{{ server.version || t('engine.servermonitor.005') }}</small></span>
        <span class="server-row__online" :class="{ 'server-row__online--offline': server.status !== 'online' }">{{ server.status === 'online' ? t('engine.servermonitor.006', [server.playersOnline ?? 0, server.playersMax ?? 0]) : 'offline' }}</span>
      </button>
      <div class="monitor-total"><span>{{ t('engine.servermonitor.007') }}</span><strong>{{ total.online }} / {{ total.max }}</strong></div>
    </template>
  </div>
</template>
