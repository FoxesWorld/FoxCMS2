<script setup lang="ts">
import { onMounted, ref } from 'vue'
import ServerPage from '@theme/foxEngine/serverPage/ServerPage.vue'
import { foxesApi } from '@/api'

interface Props { value: string }
interface MonitorServer { serverName: string; status: string; version?: string; playersOnline?: number; playersMax?: number; favicon?: string }
interface MonitorResponse { servers?: MonitorServer[] }
interface ServerDetails { serverName: string; serverVersion?: string; serverImage?: string; serverDescription?: string; checkLib?: string; modsInfo?: string | Array<ServerMod> }
interface ServerMod { modName: string; modPicture?: string; modDesc?: string }

const props = defineProps<Props>()
const loading = ref(true)
const error = ref('')
const monitor = ref<MonitorServer | null>(null)
const details = ref<ServerDetails | null>(null)
const mods = ref<ServerMod[]>([])

function normalizeMods(value: ServerDetails['modsInfo']): ServerMod[] {
  if (Array.isArray(value)) return value
  if (!value) return []
  try { const parsed = JSON.parse(value); return Array.isArray(parsed) ? parsed : [] }
  catch { return [] }
}

onMounted(async () => {
  const serverName = decodeURIComponent(props.value).trim()
  if (!/^[\p{L}\p{N}_ -]{1,64}$/u.test(serverName)) {
    error.value = 'Некорректное имя сервера.'; loading.value = false; return
  }
  try {
    const [monitorResponse, detailsResponse] = await Promise.all([
      foxesApi.post<MonitorResponse>({ sysRequest: 'parseMonitor' }),
      foxesApi.post<ServerDetails[]>({ sysRequest: 'parseServers', serverName }),
    ])
    monitor.value = monitorResponse.servers?.find((server) => server.serverName === serverName) ?? null
    details.value = detailsResponse[0] ?? null
    if (!details.value) error.value = 'Сервер не найден.'
    else mods.value = normalizeMods(details.value.modsInfo)
  } catch (requestError) {
    console.error('[FoxesCraft] Server request failed', requestError)
    error.value = 'Не удалось получить сведения о сервере.'
  } finally { loading.value = false }
})
</script>
<template><ServerPage :loading="loading" :error="error" :monitor="monitor" :details="details" :mods="mods" /></template>
