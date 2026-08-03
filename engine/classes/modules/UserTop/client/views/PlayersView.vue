<script setup lang="ts">
import { t } from '@/i18n'

import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PlayerTop from '@theme/userOptions/PlayerTop.vue'
import { foxesApi } from '@/api'

interface ServerSession { serverName: string; totalTime: number; lastPlayed: number }
interface Player {
  uuid: string
  login: string
  serversOnline: string | ServerSession[] | { servers?: Record<string, Omit<ServerSession, 'serverName'>> }
  colorScheme?: string
  skinHead?: string
}
interface ServerInfo { serverName: string }

const router = useRouter()
const loading = ref(true)
const error = ref('')
const players = ref<Player[]>([])
const activeServers = ref(new Set<string>())
const selectedServer = ref('all')
const colors: Record<string, string> = { Prodigium: '#3498db', Amber: '#c17d22', Celeste: '#37bbd0', Industrial: '#d79c1c' }
const skinHeadConcurrency = 4

function parseSessions(value: Player['serversOnline']): ServerSession[] {
  let raw: unknown = value
  if (typeof raw === 'string') {
    try { raw = JSON.parse(raw || '[]') } catch { return [] }
  }
  if (Array.isArray(raw)) {
    return raw.flatMap((entry) => entry && typeof entry === 'object' && 'serverName' in entry ? [{ serverName: String(entry.serverName), totalTime: Number(entry.totalTime) || 0, lastPlayed: Number(entry.lastPlayed) || 0 }] : [])
  }
  if (raw && typeof raw === 'object' && 'servers' in raw && raw.servers && typeof raw.servers === 'object') {
    return Object.entries(raw.servers as Record<string, Omit<ServerSession, 'serverName'>>).map(([serverName, entry]) => ({ serverName, totalTime: Number(entry.totalTime) || 0, lastPlayed: Number(entry.lastPlayed) || 0 }))
  }
  return []
}
function totalSeconds(player: Player): number {
  const sessions = parseSessions(player.serversOnline)
  return sessions.filter((session) => selectedServer.value === 'all' || session.serverName === selectedServer.value).reduce((sum, session) => sum + session.totalTime, 0)
}
function latestPlay(player: Player): number {
  return parseSessions(player.serversOnline).filter((session) => selectedServer.value === 'all' || session.serverName === selectedServer.value).reduce((latest, session) => Math.max(latest, session.lastPlayed), 0)
}
function formatDuration(seconds: number): string {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  return hours > 0 ? t('modules.usertop.playersview.001', [hours.toLocaleString('ru'), minutes]) : t('modules.usertop.playersview.002', [minutes])
}
function formatDate(timestamp: number): string {
  if (!timestamp) return t('modules.usertop.playersview.003')
  const milliseconds = timestamp < 1e12 ? timestamp * 1000 : timestamp
  const date = new Date(milliseconds)
  return Number.isNaN(date.getTime()) ? t('modules.usertop.playersview.003') : new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'short', year: 'numeric' }).format(date)
}
function safeAccent(value?: string): string { return value && /^#[0-9a-f]{3,8}$/i.test(value) ? value : '#5bd08b' }

const serverNames = computed(() => {
  const names = new Set<string>()
  for (const player of players.value) for (const session of parseSessions(player.serversOnline)) names.add(session.serverName)
  return [...names].sort((a, b) => a.localeCompare(b, 'ru'))
})
const ranking = computed(() => players.value
  .map((player) => ({ player, seconds: totalSeconds(player), lastPlayed: latestPlay(player) }))
  .filter((entry) => selectedServer.value === 'all' || entry.seconds > 0)
  .sort((a, b) => b.seconds - a.seconds))
function segments(player: Player): Array<{ name: string; width: number; color: string }> {
  const sessions = parseSessions(player.serversOnline).filter((session) => selectedServer.value === 'all' || session.serverName === selectedServer.value)
  const total = sessions.reduce((sum, session) => sum + session.totalTime, 0)
  return total > 0 ? sessions.map((session) => ({ name: session.serverName, width: session.totalTime / total * 100, color: colors[session.serverName] ?? '#82928b' })) : []
}

async function loadSkinHead(player: Player): Promise<void> {
  try {
    const encoded = await foxesApi.postText({
      sysRequest: 'skin',
      show: 'head',
      ...(player.uuid ? { userUuid: player.uuid } : { login: player.login }),
    })
    const content = encoded.trim()
    if (content !== '') player.skinHead = `data:image/png;base64,${content}`
  } catch (skinHeadError) {
    console.warn(`[FoxesCraft] Skin head failed for ${player.login}`, skinHeadError)
  }
}

async function loadSkinHeads(items: Player[]): Promise<void> {
  let cursor = 0
  const workerCount = Math.min(skinHeadConcurrency, items.length)
  await Promise.all(Array.from({ length: workerCount }, async () => {
    while (cursor < items.length) {
      const player = items[cursor++]
      if (player) await loadSkinHead(player)
    }
  }))
}

onMounted(async () => {
  try {
    const [playerData, serverData] = await Promise.all([
      foxesApi.post<Player[]>({ sysRequest: 'topPlayers' }),
      foxesApi.post<ServerInfo[]>({ sysRequest: 'parseServers' }),
    ])
    players.value = Array.isArray(playerData) ? playerData.map((player) => ({ ...player, skinHead: '' })) : []
    void loadSkinHeads(players.value)
    activeServers.value = new Set(Array.isArray(serverData) ? serverData.map((server) => server.serverName) : [])
  } catch (requestError) {
    console.error('[FoxesCraft] Player ranking failed', requestError)
    error.value = t('modules.usertop.playersview.004')
  } finally { loading.value = false }
})
</script>
<template><PlayerTop :loading="loading" :error="error" :server-names="serverNames" :active-servers="activeServers" v-model:selected-server="selectedServer" :ranking="ranking" :format-duration="formatDuration" :format-date="formatDate" :safe-accent="safeAccent" :segments="segments" @profile="router.push({ name: 'profile', params: { value: $event } })" /></template>
