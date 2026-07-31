import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { foxesApi } from '@/api'
import type { MonitorResponse } from '@/contracts/sidebar'

export function useServerMonitor() {
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
  async function refresh(): Promise<void> {
    try { data.value = await foxesApi.post<MonitorResponse>({ sysRequest: 'parseMonitor' }); error.value = false }
    catch (requestError) { console.warn('[FoxesCraft] Monitor request failed', requestError); error.value = true }
    finally { loading.value = false }
  }
  function openServer(serverName: string): void { void router.push({ name: 'server', params: { value: serverName } }) }
  onMounted(() => { void refresh(); timer = window.setInterval(() => void refresh(), 60_000) })
  onUnmounted(() => { if (timer) window.clearInterval(timer) })
  return { servers, total, loading, error, openServer }
}
