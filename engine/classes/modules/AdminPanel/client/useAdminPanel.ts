import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { bootstrapNumber } from '@/domain/bootstrap'
import { toastFeedback } from '@/notifications/toasts'

export type Tab = 'overview' | 'maintenance' | 'users' | 'servers' | 'logs' | 'catalogs'
export type Feedback = { type?: string; message?: string }
export type JsonRow = Record<string, unknown>

export interface Overview {
  users: number
  recentUsers: number
  servers: number
  enabledServers: number
  hardwareReports: number
}
export interface Hardware { cpu: Record<string, number>; gpu: Record<string, number> }
export interface MaintenanceSettings {
  enabled: boolean
  allowedGroups: number[]
  title: string
  message: string
  updatedAt: string
  updatedByUuid: string
  storageReady: boolean
}
export interface GroupOption {
  groupNum: number
  groupName: string
  groupColor: string
  groupType: string
}
export interface UserRow extends JsonRow {
  uuid: string
  login: string
  email: string
  realname?: string
  user_group: number | string
  last_date?: number | string
  profilePhoto?: string
  userStatus?: string
  balance?: unknown
  badges?: unknown
  serversOnline?: unknown
}
export interface ServerRow extends JsonRow {
  id?: number | string
  serverName: string
  host?: string
  port?: number | string
  ignoreDirs?: unknown
  enabled?: string | boolean
  checkLib?: string | boolean
  serverGroups?: unknown
  serverDescription?: string
  serverVersion?: string
  jreVersion?: string
  serverImage?: string
  modsInfo?: unknown
}

export interface UserDraft {
  login: string
  realname: string
  email: string
  userStatus: string
  user_group: number
  balance: string
  badges: string
  serversOnline: string
}

export function useAdminPanel() {
  const isAdmin = bootstrapNumber(appBootstrap, 'user_group', 5) === 1
  const activeTab = ref<Tab>('overview')
  const loading = ref(false)
  const feedback = ref<Feedback | null>(null)
  const overview = ref<Overview | null>(null)
  const hardware = ref<Hardware | null>(null)
  const maintenance = reactive<MaintenanceSettings>({
    enabled: false,
    allowedGroups: [1],
    title: 'Ведутся технические работы',
    message: 'Мы обновляем систему. Доступ будет восстановлен после завершения работ.',
    updatedAt: '',
    updatedByUuid: '',
    storageReady: false,
  })
  const maintenanceGroups = ref<GroupOption[]>([])
  const users = ref<UserRow[]>([])
  const userSearch = ref('')
  const selectedUser = ref<UserRow | null>(null)
  const userDraft = reactive({ login: '', realname: '', email: '', userStatus: '', user_group: 5, balance: '', badges: '', serversOnline: '' })
  const servers = ref<ServerRow[]>([])
  const selectedServer = ref<ServerRow | null>(null)
  const serverDraft = reactive<ServerRow>({ serverName: '', host: '', port: 25565, enabled: false, checkLib: false, ignoreDirs: '[]', serverGroups: '[]', serverDescription: '', serverVersion: '', jreVersion: '', serverImage: '', modsInfo: '[]' })
  const logFile = ref<'lastlog' | 'error' | 'access'>('lastlog')
  const logLines = ref<string[]>([])
  const autoRefreshLogs = ref(false)
  let logTimer: number | undefined
  const catalogName = ref<'infobox' | 'badges' | 'groups'>('infobox')
  const catalogRows = ref<JsonRow[]>([])
  const catalogDraft = ref('{}')
  const originalCatalogKey = ref('')

  const tabs: Array<{ id: Tab; label: string }> = [
    { id: 'overview', label: 'Обзор' },
    { id: 'maintenance', label: 'Техработы' },
    { id: 'users', label: 'Пользователи' },
    { id: 'servers', label: 'Серверы' },
    { id: 'logs', label: 'Журналы' },
    { id: 'catalogs', label: 'Каталоги' },
  ]
  const catalogKey = computed(() => ({ infobox: 'group_name', badges: 'badgeName', groups: 'groupName' })[catalogName.value])
  const hardwareMax = computed(() => Math.max(1, ...Object.values(hardware.value?.cpu ?? {}), ...Object.values(hardware.value?.gpu ?? {})))

  function toEditorValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return ''
    if (typeof value === 'string') {
      try { return JSON.stringify(JSON.parse(value), null, 2) } catch { return value }
    }
    return JSON.stringify(value, null, 2)
  }
  function parseEditorValue(value: string): unknown {
    const trimmed = value.trim()
    if (!trimmed) return ''
    try { return JSON.parse(trimmed) } catch { return trimmed }
  }
  function formatTimestamp(value?: number | string): string {
    if (!value) return '—'
    const numeric = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value
    const date = new Date(typeof numeric === 'number' && numeric < 1e12 ? numeric * 1000 : numeric)
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('ru', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
  }
  async function run<T>(action: () => Promise<T>): Promise<T | null> {
    loading.value = true
    feedback.value = null
    try { return await action() }
    catch (error) {
      console.error('[FoxesCraft] Admin request failed', error)
      feedback.value = { type: 'error', message: 'Административная операция завершилась ошибкой.' }
      return null
    } finally { loading.value = false }
  }
  async function loadOverview(): Promise<void> {
    const [summary, hardwareData] = await Promise.all([
      run(() => foxesApi.post<Overview>({ admPanel: 'overview' })),
      run(() => foxesApi.post<Hardware>({ admPanel: 'hardware' })),
    ])
    if (summary) overview.value = summary
    if (hardwareData) hardware.value = hardwareData
  }
  async function loadMaintenance(): Promise<void> {
    const response = await run(() => foxesApi.post<{ settings: MaintenanceSettings; groups: GroupOption[] }>({ admPanel: 'maintenance' }))
    if (!response) return
    Object.assign(maintenance, response.settings, { allowedGroups: [...response.settings.allowedGroups] })
    maintenanceGroups.value = response.groups
  }
  async function saveMaintenance(): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & { settings: MaintenanceSettings }>({
      admPanel: 'saveMaintenance',
      entry: JSON.stringify({
        enabled: maintenance.enabled,
        allowedGroups: maintenance.allowedGroups,
        title: maintenance.title,
        message: maintenance.message,
      }),
    }))
    if (response?.settings) {
      feedback.value = response
      Object.assign(maintenance, response.settings, { allowedGroups: [...response.settings.allowedGroups] })
    }
  }
  async function loadUsers(): Promise<void> {
    const response = await run(() => foxesApi.post<{ items: UserRow[] }>({ admPanel: 'users', search: userSearch.value, limit: 100 }))
    if (response) users.value = response.items
  }
  function editUser(user: UserRow): void {
    selectedUser.value = user
    userDraft.login = user.login
    userDraft.realname = String(user.realname ?? '')
    userDraft.email = String(user.email ?? '')
    userDraft.userStatus = String(user.userStatus ?? '')
    userDraft.user_group = Number(user.user_group) || 5
    userDraft.balance = toEditorValue(user.balance)
    userDraft.badges = toEditorValue(user.badges)
    userDraft.serversOnline = toEditorValue(user.serversOnline)
  }
  async function saveUser(): Promise<void> {
    if (!selectedUser.value) return
    const entry = {
      login: userDraft.login,
      realname: userDraft.realname,
      email: userDraft.email,
      userStatus: userDraft.userStatus,
      user_group: userDraft.user_group,
      balance: parseEditorValue(userDraft.balance),
      badges: parseEditorValue(userDraft.badges),
      serversOnline: parseEditorValue(userDraft.serversOnline),
    }
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'updateUser', userUuid: selectedUser.value?.uuid, entry: JSON.stringify(entry) }))
    if (response) { feedback.value = response; await loadUsers(); selectedUser.value = null }
  }
  async function loadServers(): Promise<void> {
    const response = await run(() => foxesApi.post<{ items: ServerRow[] }>({ admPanel: 'servers' }))
    if (response) servers.value = response.items
  }
  function newServer(): void {
    selectedServer.value = null
    Object.assign(serverDraft, { serverName: '', host: '', port: 25565, enabled: false, checkLib: false, ignoreDirs: '[]', serverGroups: '[]', serverDescription: '', serverVersion: '', jreVersion: '', serverImage: '', modsInfo: '[]' })
  }
  function editServer(server: ServerRow): void {
    selectedServer.value = server
    Object.assign(serverDraft, server, {
      enabled: server.enabled === true || server.enabled === 'true',
      checkLib: server.checkLib === true || server.checkLib === 'true',
      ignoreDirs: toEditorValue(server.ignoreDirs),
      serverGroups: toEditorValue(server.serverGroups),
      modsInfo: toEditorValue(server.modsInfo),
    })
  }
  async function saveServer(): Promise<void> {
    const entry = { ...serverDraft, ignoreDirs: parseEditorValue(String(serverDraft.ignoreDirs ?? '')), serverGroups: parseEditorValue(String(serverDraft.serverGroups ?? '')), modsInfo: parseEditorValue(String(serverDraft.modsInfo ?? '')) }
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'saveServer', originalName: selectedServer.value?.serverName ?? '', entry: JSON.stringify(entry) }))
    if (response) { feedback.value = response; await loadServers(); newServer() }
  }
  async function deleteServer(server: ServerRow): Promise<void> {
    if (!window.confirm(`Удалить сервер ${server.serverName}?`)) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'deleteServer', serverName: server.serverName }))
    if (response) { feedback.value = response; await loadServers(); if (selectedServer.value?.serverName === server.serverName) newServer() }
  }
  async function loadLogs(): Promise<void> {
    const response = await run(() => foxesApi.post<{ lines: string[] }>({ admPanel: 'log', file: logFile.value, lines: 200 }))
    if (response) logLines.value = response.lines
  }
  async function clearLogs(): Promise<void> {
    if (!window.confirm(`Очистить ${logFile.value}.log?`)) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'clearLog', file: logFile.value }))
    if (response) { feedback.value = response; await loadLogs() }
  }
  function updateLogTimer(): void {
    if (logTimer) window.clearInterval(logTimer)
    logTimer = autoRefreshLogs.value ? window.setInterval(() => void loadLogs(), 10_000) : undefined
  }
  async function loadCatalog(): Promise<void> {
    const response = await run(() => foxesApi.post<{ items: JsonRow[] }>({ admPanel: 'catalog', catalog: catalogName.value }))
    if (response) catalogRows.value = response.items
    newCatalogEntry()
  }
  function newCatalogEntry(): void { originalCatalogKey.value = ''; catalogDraft.value = JSON.stringify({}, null, 2) }
  function editCatalogEntry(row: JsonRow): void {
    originalCatalogKey.value = String(row[catalogKey.value] ?? '')
    catalogDraft.value = JSON.stringify(row, null, 2)
  }
  async function saveCatalogEntry(): Promise<void> {
    let parsed: unknown
    try { parsed = JSON.parse(catalogDraft.value) }
    catch { feedback.value = toastFeedback({ type: 'error', message: 'Каталог содержит некорректный JSON.' }); return }
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'saveCatalogEntry', catalog: catalogName.value, originalKey: originalCatalogKey.value, entry: JSON.stringify(parsed) }))
    if (response) { feedback.value = response; await loadCatalog() }
  }
  async function deleteCatalogEntry(row: JsonRow): Promise<void> {
    const key = String(row[catalogKey.value] ?? '')
    if (!key || !window.confirm(`Удалить запись ${key}?`)) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'deleteCatalogEntry', catalog: catalogName.value, key }))
    if (response) { feedback.value = response; await loadCatalog() }
  }
  async function activate(tab: Tab): Promise<void> {
    activeTab.value = tab
    if (tab === 'overview') await loadOverview()
    if (tab === 'maintenance') await loadMaintenance()
    if (tab === 'users') await loadUsers()
    if (tab === 'servers') { await loadServers(); if (!selectedServer.value) newServer() }
    if (tab === 'logs') await loadLogs()
    if (tab === 'catalogs') await loadCatalog()
  }
  watch(logFile, () => void loadLogs())
  watch(autoRefreshLogs, updateLogTimer)
  watch(catalogName, () => void loadCatalog())
  onMounted(() => { if (isAdmin) void loadOverview() })
  onUnmounted(() => { if (logTimer) window.clearInterval(logTimer) })

  return {
    isAdmin,
    activeTab,
    loading,
    feedback,
    overview,
    hardware,
    maintenance,
    maintenanceGroups,
    users,
    userSearch,
    selectedUser,
    userDraft,
    servers,
    selectedServer,
    serverDraft,
    logFile,
    logLines,
    autoRefreshLogs,
    catalogName,
    catalogRows,
    catalogDraft,
    originalCatalogKey,
    tabs,
    catalogKey,
    hardwareMax,
    formatTimestamp,
    loadMaintenance,
    saveMaintenance,
    loadUsers,
    editUser,
    saveUser,
    newServer,
    editServer,
    saveServer,
    deleteServer,
    loadLogs,
    clearLogs,
    loadCatalog,
    newCatalogEntry,
    editCatalogEntry,
    saveCatalogEntry,
    deleteCatalogEntry,
    activate,
  }
}
