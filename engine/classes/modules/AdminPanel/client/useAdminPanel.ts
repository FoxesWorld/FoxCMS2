import { computed, onMounted, onUnmounted, reactive, ref, shallowReactive, watch } from 'vue'
import { appBootstrap } from '@/app/context'
import { FoxesApiError, foxesApi } from '@/api'
import { invalidateContentRegistry } from '@/content/contentData'
import { bootstrapString } from '@/domain/bootstrap'
import { createJsonObjectTemplate, decodeJsonValue, mergeJsonWithTemplate, normalizeJsonValue } from '@/forms/json-form'
import type { JsonObject, JsonValue } from '@/forms/json-form'

export type Tab = 'overview' | 'slides' | 'content' | 'maintenance' | 'users' | 'servers' | 'files' | 'logs' | 'catalogs'
export interface AdminErrorDetails {
  action: string
  exception: string
  detail: string
  requestId: string
}
export type Feedback = {
  type?: string
  message?: string
  requestId?: string
  error?: AdminErrorDetails
}
export type JsonRow = Record<string, unknown>
export interface LogEntry { timestamp: string; time: string; level: string; message: string; tone: string }
export interface FileEntry {
  name: string
  path: string
  type: 'file' | 'directory'
  size: number
  modified: number
  extension: string
  mime: string
  url: string
}
export interface FileListResponse {
  root: string
  path: string
  parent: string | null
  items: FileEntry[]
  writable: boolean
  totalBytes: number
}

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
  allowedGroups: string[]
  title: string
  message: string
  updatedAt: string
  updatedByUuid: string
  storageReady: boolean
}
export interface SlideRouteOption {
  name: string
  path: string
  title: string
}
export interface SlideDraft {
  id: string
  enabled: boolean
  title: string
  description: string
  image: string
  route: string
  action: string
  secondaryRoute: string
  secondaryAction: string
}
export interface SliderSettings {
  schema: number
  eyebrow: string
  autoplayMs: number
  slides: SlideDraft[]
}

export interface ProjectPageDraft {
  id: string
  title: string
  html: string
}
export interface BadgePageDraft {
  badgeName: string
  slug: string
  html: string
}
export interface BadgeCatalogRow {
  id: number
  badgeName: string
  description: string
  img: string
  pageSlug: string
  pageConfigured: boolean
}
export interface RuntimeContentDocument<T> {
  schema: number
  pages: T[]
}

export interface GroupOption {
  groupTag: string
  groupName: string
  groupColor: string
}
export interface UserRow extends JsonRow {
  uuid: string
  login: string
  email: string
  realname?: string
  groupTag: string
  groupName?: string
  groupColor?: string
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
export interface ServerDraft {
  id?: number | string
  serverName: string
  host: string
  port: number | string
  ignoreDirs: JsonValue
  enabled: boolean
  checkLib: boolean
  serverGroups: string[]
  serverDescription: string
  serverVersion: string
  jreVersion: string
  serverImage: string
  modsInfo: JsonValue
}
export interface UserDraft {
  login: string
  realname: string
  email: string
  userStatus: string
  groupTag: string
  balance: JsonValue
  badges: JsonValue
  serversOnline: JsonValue
}

export function useAdminPanel() {
  const isAdmin = appBootstrap.frontend.capabilities.includes('admin.panel')
  const activeTab = ref<Tab>('overview')
  const loading = ref(false)
  const feedback = ref<Feedback | null>(null)
  const overview = ref<Overview | null>(null)
  const hardware = ref<Hardware | null>(null)
  const maintenance = reactive<MaintenanceSettings>({
    enabled: false,
    allowedGroups: ['admin'],
    title: 'Ведутся технические работы',
    message: 'Мы обновляем систему. Доступ будет восстановлен после завершения работ.',
    updatedAt: '',
    updatedByUuid: '',
    storageReady: false,
  })
  const sliderSettings = reactive<SliderSettings>({
    schema: 1,
    eyebrow: 'FoxesCraft — новая глава',
    autoplayMs: 7000,
    slides: [],
  })
  const sliderRoutes = ref<SlideRouteOption[]>([])
  const projectPages = ref<ProjectPageDraft[]>([])
  const badgePages = ref<BadgePageDraft[]>([])
  const contentBadges = ref<BadgeCatalogRow[]>([])
  const groupOptions = ref<GroupOption[]>([])
  const badgeOptions = ref<string[]>([])
  const users = ref<UserRow[]>([])
  const userSearch = ref('')
  const selectedUser = ref<UserRow | null>(null)
  const userDraft = shallowReactive<UserDraft>({
    login: '',
    realname: '',
    email: '',
    userStatus: '',
    groupTag: 'guest',
    balance: '',
    badges: '',
    serversOnline: '',
  })
  const servers = ref<ServerRow[]>([])
  const selectedServer = ref<ServerRow | null>(null)
  const serverDraft = shallowReactive<ServerDraft>({
    serverName: '',
    host: '',
    port: 25565,
    enabled: false,
    checkLib: false,
    ignoreDirs: [],
    serverGroups: [],
    serverDescription: '',
    serverVersion: '',
    jreVersion: '',
    serverImage: '',
    modsInfo: [],
  })
  const filePath = ref('')
  const fileParent = ref<string | null>(null)
  const fileEntries = ref<FileEntry[]>([])
  const fileWritable = ref(false)
  const fileTotalBytes = ref(0)
  const selectedUpload = ref<File | null>(null)
  const fileUploading = ref(false)
  const newDirectoryName = ref('')
  const logFile = ref<'lastlog' | 'error' | 'access'>('lastlog')
  const logEntries = ref<LogEntry[]>([])
  const autoRefreshLogs = ref(false)
  let logTimer: number | undefined
  const catalogName = ref<'infobox' | 'badges' | 'groups'>('infobox')
  const catalogRows = ref<JsonRow[]>([])
  const catalogFields = ref<string[]>([])
  const catalogDraft = ref<JsonObject>({})
  const originalCatalogKey = ref('')

  const tabs: Array<{ id: Tab; label: string; description: string; icon: string }> = [
    { id: 'overview', label: 'Обзор', description: 'Состояние системы и основные показатели', icon: 'fa-chart-line' },
    { id: 'slides', label: 'Слайды', description: 'Главный экран и порядок публикаций', icon: 'fa-images' },
    { id: 'content', label: 'Контент', description: 'Страницы проекта и полные страницы бейджей', icon: 'fa-newspaper' },
    { id: 'maintenance', label: 'Техработы', description: 'Режим обслуживания и доступ групп', icon: 'fa-screwdriver-wrench' },
    { id: 'users', label: 'Пользователи', description: 'Аккаунты, группы и профильные данные', icon: 'fa-users' },
    { id: 'servers', label: 'Серверы', description: 'Игровые серверы и параметры запуска', icon: 'fa-server' },
    { id: 'files', label: 'Файлы', description: 'Управление каталогом uploads', icon: 'fa-folder-open' },
    { id: 'logs', label: 'Журналы', description: 'Системные события и ошибки', icon: 'fa-rectangle-list' },
    { id: 'catalogs', label: 'Каталоги', description: 'Справочники и структурированные данные', icon: 'fa-table-list' },
  ]
  const catalogKey = computed(() => ({ infobox: 'group_name', badges: 'badgeName', groups: 'groupTag' })[catalogName.value])
  const hardwareMax = computed(() => Math.max(1, ...Object.values(hardware.value?.cpu ?? {}), ...Object.values(hardware.value?.gpu ?? {})))

  function setGroups(groups: GroupOption[]): void { groupOptions.value = groups }
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
      const message = error instanceof Error && error.message.trim() !== ''
        ? error.message.trim()
        : 'Неизвестная ошибка административной операции.'
      if (error instanceof FoxesApiError) {
        const rawDetails = error.payload?.error
        const details = rawDetails && typeof rawDetails === 'object' && !Array.isArray(rawDetails)
          ? rawDetails as Record<string, unknown>
          : null
        feedback.value = {
          type: 'error',
          message,
          requestId: error.requestId,
          error: details ? {
            action: typeof details.action === 'string' ? details.action : 'unknown',
            exception: typeof details.exception === 'string' ? details.exception : 'UnknownException',
            detail: typeof details.detail === 'string' ? details.detail : message,
            requestId: typeof details.requestId === 'string' ? details.requestId : error.requestId,
          } : undefined,
        }
      } else {
        feedback.value = { type: 'error', message }
      }
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
    setGroups(response.groups)
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
  async function loadSlides(): Promise<void> {
    const response = await run(() => foxesApi.post<{ settings: SliderSettings; routes: SlideRouteOption[] }>({ admPanel: 'slides' }))
    if (!response) return
    Object.assign(sliderSettings, response.settings, {
      slides: response.settings.slides.map((slide) => ({ ...slide })),
    })
    sliderRoutes.value = response.routes
  }
  function addSlide(): void {
    const fallbackRoute = sliderRoutes.value.find((route) => route.name === 'about')?.name
      ?? sliderRoutes.value[0]?.name
      ?? 'home'
    sliderSettings.slides.push({
      id: `slide-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`,
      enabled: true,
      title: 'Новый слайд',
      description: '',
      image: 'img/slides/slide1.png',
      route: fallbackRoute,
      action: 'Подробнее',
      secondaryRoute: '',
      secondaryAction: '',
    })
  }
  function removeSlide(index: number): void {
    const slide = sliderSettings.slides[index]
    if (!slide || !window.confirm(`Удалить слайд «${slide.title}»?`)) return
    sliderSettings.slides.splice(index, 1)
  }
  function moveSlide(index: number, direction: number): void {
    const target = index + direction
    if (index < 0 || target < 0 || index >= sliderSettings.slides.length || target >= sliderSettings.slides.length) return
    const [slide] = sliderSettings.slides.splice(index, 1)
    if (slide) sliderSettings.slides.splice(target, 0, slide)
  }
  async function uploadSlideImage(index: number, file: File): Promise<void> {
    const slide = sliderSettings.slides[index]
    if (!slide) return
    const body = new FormData()
    body.set('admPanel', 'uploadSlideImage')
    body.set('image', file, file.name)
    const response = await run(() => foxesApi.postFormData<Feedback & { image: string }>(body))
    if (response?.image) {
      slide.image = response.image
      feedback.value = response
    }
  }
  async function saveSlides(): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & { settings: SliderSettings }>({
      admPanel: 'saveSlides',
      entry: JSON.stringify({
        schema: 1,
        eyebrow: sliderSettings.eyebrow,
        autoplayMs: sliderSettings.autoplayMs,
        slides: sliderSettings.slides,
      }),
    }))
    if (!response) return
    feedback.value = response
    if (response.settings) {
      Object.assign(sliderSettings, response.settings, {
        slides: response.settings.slides.map((slide) => ({ ...slide })),
      })
    }
  }

  function cloneContent<T>(value: T): T {
    return structuredClone(value)
  }
  function escapeHtml(value: string): string {
    return value
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')
  }
  function badgePageTemplate(badge: BadgeCatalogRow, slug: string): string {
    const badgeName = escapeHtml(badge.badgeName)
    return `<article class="content-surface badge-page badge-page--runtime" data-badge-page="1" data-badge-name="${badgeName}" data-badge-slug="${slug}">
  <header class="badge-page__header">
    <div class="badge-page__visual">
      <img data-badge-image src="" alt="" loading="eager" decoding="async">
    </div>
    <div>
      <span class="eyebrow" data-badge-eyebrow>FoxesCraft badge</span>
      <h1 data-badge-title></h1>
      <p class="lead" data-badge-description></p>
    </div>
  </header>
  <section class="badge-story" data-badge-history>
    <h2>История бейджа</h2>
    <p>Добавьте полное описание, происхождение и историю этого бейджа.</p>
  </section>
</article>
`
  }
  async function loadContent(): Promise<void> {
    const response = await run(() => foxesApi.post<{
      projectPages: RuntimeContentDocument<ProjectPageDraft>
      badgePages: { pages: BadgePageDraft[] }
      badges: BadgeCatalogRow[]
    }>({ admPanel: 'content' }))
    if (!response) return
    projectPages.value = cloneContent(response.projectPages.pages)
    badgePages.value = cloneContent(response.badgePages.pages)
    contentBadges.value = response.badges.map((badge) => ({ ...badge, id: Number(badge.id) }))
  }
  function ensureBadgePage(badge: BadgeCatalogRow): BadgePageDraft {
    const existing = badgePages.value.find((page) => page.slug === badge.pageSlug)
    if (existing) return existing
    const created: BadgePageDraft = {
      badgeName: badge.badgeName,
      slug: badge.pageSlug,
      html: badgePageTemplate(badge, badge.pageSlug),
    }
    badgePages.value.push(created)
    return created
  }
  function removeBadgePage(badgeName: string): void {
    const badge = contentBadges.value.find((entry) => entry.badgeName === badgeName)
    if (!badge) return
    const index = badgePages.value.findIndex((page) => page.slug === badge.pageSlug)
    if (index >= 0) badgePages.value.splice(index, 1)
  }
  async function saveProjectPages(): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & { document: RuntimeContentDocument<ProjectPageDraft> }>({
      admPanel: 'saveProjectPages',
      entry: JSON.stringify({ schema: 2, pages: projectPages.value }),
    }))
    if (!response) return
    feedback.value = response
    projectPages.value = cloneContent(response.document.pages)
    invalidateContentRegistry('project-pages')
  }
  async function saveBadgePage(page: BadgePageDraft): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & { page: BadgePageDraft }>({
      admPanel: 'saveBadgePage',
      entry: JSON.stringify(page),
    }))
    if (!response) return
    feedback.value = response
    const index = badgePages.value.findIndex((entry) => entry.slug === response.page.slug)
    if (index >= 0) badgePages.value.splice(index, 1, cloneContent(response.page))
    else badgePages.value.push(cloneContent(response.page))
    invalidateContentRegistry('badges')
  }
  async function deleteBadgePage(page: BadgePageDraft): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'deleteBadgePage', slug: page.slug }))
    if (!response) return
    feedback.value = response
    const index = badgePages.value.indexOf(page)
    if (index >= 0) badgePages.value.splice(index, 1)
    invalidateContentRegistry('badges')
  }

  async function loadUsers(options: { selectUuid?: string; autoSelect?: boolean } = {}): Promise<void> {
    const response = await run(() => foxesApi.post<{ items: UserRow[]; groups: GroupOption[]; badgeOptions: string[] }>({ admPanel: 'users', search: userSearch.value, limit: 100 }))
    if (!response) return
    const preferredUuid = options.selectUuid ?? selectedUser.value?.uuid ?? ''
    users.value = response.items
    setGroups(response.groups)
    badgeOptions.value = response.badgeOptions

    const preferred = preferredUuid
      ? users.value.find((user) => user.uuid === preferredUuid) ?? null
      : null
    const next = preferred ?? (options.autoSelect === false ? null : users.value[0] ?? null)
    if (next) editUser(next)
    else selectedUser.value = null
  }
  function editUser(user: UserRow): void {
    selectedUser.value = user
    userDraft.login = user.login
    userDraft.realname = String(user.realname ?? '')
    userDraft.email = String(user.email ?? '')
    userDraft.userStatus = String(user.userStatus ?? '')
    userDraft.groupTag = user.groupTag || 'guest'
    userDraft.balance = decodeJsonValue(user.balance)
    userDraft.badges = decodeJsonValue(user.badges)
    userDraft.serversOnline = decodeJsonValue(user.serversOnline)
  }
  async function saveUser(): Promise<void> {
    if (!selectedUser.value) return
    const selectedUuid = selectedUser.value.uuid
    const entry = {
      login: userDraft.login,
      realname: userDraft.realname,
      email: userDraft.email,
      userStatus: userDraft.userStatus,
      groupTag: userDraft.groupTag,
      balance: userDraft.balance,
      badges: userDraft.badges,
      serversOnline: userDraft.serversOnline,
    }
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'updateUser', userUuid: selectedUuid, entry: JSON.stringify(entry) }))
    if (response) {
      feedback.value = response
      await loadUsers({ selectUuid: selectedUuid })
    }
  }
  async function loadServers(): Promise<void> {
    const response = await run(() => foxesApi.post<{ items: ServerRow[]; groups: GroupOption[] }>({ admPanel: 'servers' }))
    if (!response) return
    servers.value = response.items
    setGroups(response.groups)
  }
  function newServer(): void {
    selectedServer.value = null
    Object.assign(serverDraft, {
      serverName: '', host: '', port: 25565, enabled: false, checkLib: false,
      ignoreDirs: [],
      serverGroups: groupOptions.value.filter((group) => group.groupTag !== 'admin').map((group) => group.groupTag),
      serverDescription: '', serverVersion: '', jreVersion: '', serverImage: '', modsInfo: [],
    })
  }
  function editServer(server: ServerRow): void {
    selectedServer.value = server
    Object.assign(serverDraft, server, {
      enabled: server.enabled === true || server.enabled === 'true',
      checkLib: server.checkLib === true || server.checkLib === 'true',
      ignoreDirs: decodeJsonValue(server.ignoreDirs, []),
      serverGroups: Array.isArray(server.serverGroups) ? server.serverGroups.map(String) : [],
      modsInfo: decodeJsonValue(server.modsInfo, []),
    })
  }
  async function saveServer(): Promise<void> {
    const entry = {
      ...serverDraft,
      ignoreDirs: serverDraft.ignoreDirs,
      serverGroups: [...serverDraft.serverGroups],
      modsInfo: serverDraft.modsInfo,
    }
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'saveServer', originalName: selectedServer.value?.serverName ?? '', entry: JSON.stringify(entry) }))
    if (response) { feedback.value = response; await loadServers(); newServer() }
  }
  async function deleteServer(server: ServerRow): Promise<void> {
    if (!window.confirm(`Удалить сервер ${server.serverName}?`)) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'deleteServer', serverName: server.serverName }))
    if (response) { feedback.value = response; await loadServers(); if (selectedServer.value?.serverName === server.serverName) newServer() }
  }
  async function loadFiles(path = filePath.value): Promise<void> {
    const response = await run(() => foxesApi.post<FileListResponse>({ admPanel: 'fileList', path }))
    if (!response) return
    filePath.value = response.path
    fileParent.value = response.parent
    fileEntries.value = response.items
    fileWritable.value = response.writable
    fileTotalBytes.value = response.totalBytes
  }
  function selectUpload(file: File | null): void {
    selectedUpload.value = file
  }
  async function uploadFile(): Promise<void> {
    if (!selectedUpload.value || fileUploading.value) return
    const body = new FormData()
    body.set('admPanel', 'fileUpload')
    body.set('path', filePath.value)
    const file = selectedUpload.value
    body.set('file', file, file.name)
    fileUploading.value = true
    try {
      const response = await run(() => foxesApi.postFormData<Feedback>(body))
      if (response) {
        feedback.value = response
        selectedUpload.value = null
        await loadFiles()
      }
    } finally {
      fileUploading.value = false
    }
  }
  async function createDirectory(): Promise<void> {
    const name = newDirectoryName.value.trim()
    if (!name) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'fileCreateDirectory', path: filePath.value, name }))
    if (response) {
      feedback.value = response
      newDirectoryName.value = ''
      await loadFiles()
    }
  }
  async function renameFile(entry: FileEntry): Promise<void> {
    const name = window.prompt('Новое имя', entry.name)?.trim()
    if (!name || name === entry.name) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'fileRename', path: entry.path, name }))
    if (response) { feedback.value = response; await loadFiles() }
  }
  async function deleteFile(entry: FileEntry): Promise<void> {
    const label = entry.type === 'directory' ? `каталог ${entry.name} со всем содержимым` : `файл ${entry.name}`
    if (!window.confirm(`Удалить ${label}?`)) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'fileDelete', path: entry.path }))
    if (response) { feedback.value = response; await loadFiles() }
  }
  function openFile(entry: FileEntry): void {
    if (entry.type === 'directory') { void loadFiles(entry.path); return }
    if (entry.url) window.open(entry.url, '_blank', 'noopener,noreferrer')
  }

  async function loadLogs(): Promise<void> {
    const response = await run(() => foxesApi.post<{ entries: LogEntry[] }>({ admPanel: 'log', file: logFile.value, lines: 200 }))
    if (response) logEntries.value = response.entries
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
    const response = await run(() => foxesApi.post<{ items: JsonRow[]; fields: string[] }>({ admPanel: 'catalog', catalog: catalogName.value }))
    if (response) {
      catalogRows.value = response.items
      catalogFields.value = response.fields
      if (catalogName.value === 'groups') setGroups(response.items as unknown as GroupOption[])
    }
    newCatalogEntry()
  }
  function newCatalogEntry(): void {
    originalCatalogKey.value = ''
    catalogDraft.value = createJsonObjectTemplate(catalogRows.value, catalogFields.value)
  }
  function editCatalogEntry(row: JsonRow): void {
    originalCatalogKey.value = String(row[catalogKey.value] ?? '')
    const template = createJsonObjectTemplate(catalogRows.value, catalogFields.value)
    catalogDraft.value = mergeJsonWithTemplate(normalizeJsonValue(row), template) as JsonObject
  }
  async function saveCatalogEntry(): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'saveCatalogEntry', catalog: catalogName.value, originalKey: originalCatalogKey.value, entry: JSON.stringify(catalogDraft.value) }))
    if (response) {
      feedback.value = response
      if (catalogName.value === 'badges') invalidateContentRegistry('badges')
      await loadCatalog()
    }
  }
  async function deleteCatalogEntry(row: JsonRow): Promise<void> {
    const key = String(row[catalogKey.value] ?? '')
    if (!key || !window.confirm(`Удалить запись ${key}?`)) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'deleteCatalogEntry', catalog: catalogName.value, key }))
    if (response) {
      feedback.value = response
      if (catalogName.value === 'badges') invalidateContentRegistry('badges')
      await loadCatalog()
    }
  }
  async function activate(tab: Tab): Promise<void> {
    activeTab.value = tab
    if (tab === 'overview') await loadOverview()
    if (tab === 'slides') await loadSlides()
    if (tab === 'content') await loadContent()
    if (tab === 'maintenance') await loadMaintenance()
    if (tab === 'users') await loadUsers()
    if (tab === 'servers') { await loadServers(); if (!selectedServer.value) newServer() }
    if (tab === 'files') await loadFiles()
    if (tab === 'logs') await loadLogs()
    if (tab === 'catalogs') await loadCatalog()
  }
  watch(logFile, () => void loadLogs())
  watch(autoRefreshLogs, updateLogTimer)
  watch(catalogName, () => void loadCatalog())
  onMounted(() => { if (isAdmin) void loadOverview() })
  onUnmounted(() => { if (logTimer) window.clearInterval(logTimer) })

  return {
    isAdmin, activeTab, loading, feedback, overview, hardware, maintenance, sliderSettings, sliderRoutes, projectPages, badgePages, contentBadges, groupOptions, badgeOptions,
    users, userSearch, selectedUser, userDraft, servers, selectedServer, serverDraft,
    filePath, fileParent, fileEntries, fileWritable, fileTotalBytes, selectedUpload, fileUploading, newDirectoryName,
    logFile, logEntries, autoRefreshLogs, catalogName, catalogRows, catalogDraft,
    originalCatalogKey, tabs, catalogKey, hardwareMax, formatTimestamp, loadMaintenance,
    saveMaintenance, loadSlides, addSlide, removeSlide, moveSlide, uploadSlideImage, saveSlides,
    loadContent, ensureBadgePage, removeBadgePage, saveProjectPages, saveBadgePage, deleteBadgePage,
    loadUsers, editUser, saveUser, newServer, editServer, saveServer,
    deleteServer, loadFiles, selectUpload, uploadFile, createDirectory, renameFile, deleteFile, openFile,
    loadLogs, clearLogs, loadCatalog, newCatalogEntry, editCatalogEntry,
    saveCatalogEntry, deleteCatalogEntry, activate,
  }
}
