import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { foxesApi } from '@/api'
import type { LastUserRecord } from '@/contracts/sidebar'

export function useLastUser() {
  const router = useRouter()
  const user = ref<LastUserRecord | null>(null)
  const loading = ref(true)
  const error = ref(false)
  function formatDate(value?: string | number): string {
    if (!value) return 'недавно'
    const numeric = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value
    const date = new Date(typeof numeric === 'number' && numeric < 10_000_000_000 ? numeric * 1000 : numeric)
    return Number.isNaN(date.getTime()) ? 'недавно' : new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'long' }).format(date)
  }
  function openProfile(login: string): void { void router.push({ name: 'profile', params: { value: login } }) }
  onMounted(async () => {
    try { user.value = await foxesApi.post<LastUserRecord>({ userAction: 'lastUser' }) }
    catch (requestError) { console.warn('[FoxesCraft] Last-user request failed', requestError); error.value = true }
    finally { loading.value = false }
  })
  return { user, loading, error, formatDate, openProfile }
}
