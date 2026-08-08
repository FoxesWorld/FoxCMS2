import { t } from '@/i18n'
import { computed, onUnmounted, reactive, ref, shallowReactive, watch } from 'vue'
import { appBootstrap } from '@/app/context'
import { FoxesApiError, foxesApi } from '@/api'
import { invalidateContentRegistry } from '@/content/contentData'
import { bootstrapString } from '@/domain/bootstrap'
import { normalizeBalanceMatrix } from '@/domain/userBalance'
import { createJsonObjectTemplate, decodeJsonValue, mergeJsonWithTemplate, normalizeJsonValue } from '@/forms/json-form'
import type { JsonObject, JsonValue } from '@/forms/json-form'
import {
  cloneRuntimeUserOptions, installRuntimeUserOptions, loadRuntimeUserOptions, runtimeAdminCategories, runtimeAdminTools, runtimeUserOptionsRevision,
  type RuntimeUserOptionsDocument,
} from '@/runtime/userOptions'
import {
  installRuntimePageTemplates,
  type RuntimePageTemplatesDocument,
} from '@/runtime/pageTemplates'

export type Tab = 'overview' | 'settings' | 'slides' | 'content' | 'rewards' | 'maintenance' | 'users' | 'achievements' | 'servers' | 'files' | 'logs' | 'catalogs' | 'runtime-options'
export type CatalogName = 'infobox' | 'badges' | 'groups'
export type AdminToolId = Exclude<Tab, 'catalogs'> | 'infobox' | 'badges' | 'groups'
export type AdminSection = 'home' | AdminToolId
export type AdminView = 'home' | Tab
export type AdminCategoryId = string
export interface AdminToolDefinition {
  id: AdminToolId
  tab: Tab
  category: AdminCategoryId
  label: string
  description: string
  icon: string
  catalog?: CatalogName
}
export interface AdminCategoryDefinition {
  id: AdminCategoryId
  label: string
  description: string
  icon: string
}
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
export interface LogDeviation {
  code?: string
  severity?: string
  expected?: unknown
  actual?: unknown
}
export interface LogException {
  class?: string
  message?: string
  file?: string
  line?: number
  trace?: string
}
export interface LogEntry {
  timestamp: string
  time: string
  level: string
  event: string
  message: string
  tone: string
  requestId?: string
  correlationId?: string
  component?: string
  operation?: string
  outcome?: string
  httpMethod?: string
  httpPath?: string
  httpStatus?: number | null
  durationMs?: number | null
  actorUuid?: string
  actorLogin?: string
  actorGroup?: string
  requestChannel?: string
  action?: string
  handler?: string
  authenticated?: boolean | null
  sessionState?: string
  deviation?: LogDeviation | null
  exception?: LogException | null
  context?: Record<string, unknown>
  malformed?: boolean
}
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
export interface HardwareDistribution {
  label: string
  count: number
  percentage: number
}
export interface HardwareSummary {
  totalSystems: number
  totalMemoryBytes: number
  averageMemoryBytes: number
  averageLogicalCpuCount: number
  firstSeenAt: string | null
  lastSeenAt: string | null
}
export interface HardwareSystem {
  systemId: string
  schemaVersion: number
  updaterVersion: string
  platform: string
  osName: string
  osVersion: string | null
  kernelVersion: string | null
  architecture: string
  cpuBrand: string | null
  logicalCpuCount: number
  memoryBytes: number
  gpuAdapters: string[]
  firstSeenAt: string | null
}
export interface Hardware {
  summary: HardwareSummary
  platforms: HardwareDistribution[]
  operatingSystems: HardwareDistribution[]
  architectures: HardwareDistribution[]
  updaterVersions: HardwareDistribution[]
  cpuVendors: HardwareDistribution[]
  gpuVendors: HardwareDistribution[]
  cpuModels: HardwareDistribution[]
  gpuModels: HardwareDistribution[]
  memoryBuckets: HardwareDistribution[]
  systems: HardwareSystem[]
}
export interface MaintenanceSettings {
  enabled: boolean
  allowedGroups: string[]
  title: string
  message: string
  updatedAt: string
  updatedByUuid: string
  storageReady: boolean
}
export interface SiteSettings {
  siteTitle: string
  siteStatus: string
  siteDesc: string
  homeTitle: string
  titleTemplate: string
  keywords: string
  robots: 'index,follow' | 'index,nofollow' | 'noindex,follow' | 'noindex,nofollow'
  canonicalUrl: string
  lang: string
  locale: string
  author: string
  themeColor: string
  faviconUrl: string
  ogSiteName: string
  ogTitle: string
  ogDescription: string
  ogImage: string
  twitterCard: 'summary' | 'summary_large_image'
  twitterSite: string
  twitterCreator: string
  googleVerification: string
  yandexVerification: string
  bingVerification: string
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
export interface SystemPageDraft {
  id: string
  title: string
  description: string
  route: string
  routeName: string
  view: string
  source: string
  capability: string
  editable: boolean
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
export type RewardClaimUsageMode = 'single' | 'reusable'
export type RewardClaimAccessMode = 'code' | 'public'
export interface RewardDefinitionRow {
  id: number
  rewardName: string
  description: string
  badgeId: number
  badgeName: string
  badgeDescription: string
  badgeImage: string
  currencyCode: string
  currencyAmount: number
  enabled: boolean
  createdAt: number
  updatedAt: number
  createdByUuid: string
  updatedByUuid: string
  keysCount: number
  claimsCount: number
}
export interface RewardClaimKeyRow {
  id: number
  rewardId: number
  rewardName: string
  badgeId: number
  badgeName: string
  currencyCode: string
  currencyAmount: number
  tokenHint: string
  usageMode: RewardClaimUsageMode
  accessMode: RewardClaimAccessMode
  publicPlacement: string
  usesCount: number
  enabled: boolean
  createdAt: number
  updatedAt: number
  createdByUuid: string
  claimsCount: number
  lastClaimedAt: number | null
}
export interface IssuedRewardClaimCode {
  token: string | null
  entry: RewardClaimKeyRow
}
export interface RewardDraft {
  id: number
  rewardName: string
  description: string
  badgeId: number
  currencyCode: string
  currencyAmount: number
  enabled: boolean
}
export interface RuntimeContentDocument<T> {
  schema: number
  pages: T[]
}

export function javaMajorFromSelector(value: unknown): string {
  const raw = String(value ?? '').trim()
  if (raw === '') return ''

  const profile = raw.match(/^java=([0-9]{1,3})(?:;|$)/i)
  if (profile) {
    const major = Number(profile[1])
    return major >= 1 && major <= 100 ? String(major) : ''
  }

  const normalized = raw.replace(/^(?:jdk|jre|java)[-_\s]*/i, '')
  const legacy = normalized.match(/^1\.([0-9]{1,3})(?=[^0-9]|$)/)
  const modern = normalized.match(/^([0-9]{1,3})(?=[^0-9]|$)/)
  const major = Number(legacy?.[1] ?? modern?.[1] ?? 0)
  return major >= 1 && major <= 100 ? String(major) : ''
}

export interface GroupOption {
  groupTag: string
  groupName: string
  groupColor: string
}
export interface AdminBadgeOption {
  id: number
  badgeName: string
  title: string
  description: string
  image: string | null
}
export interface JdkRuntimeArtifact {
  runtimeId: string
  platform: string
  system: string
  version: string
  versionCore: string
  javaMajor: number
  vendor: string
  distribution: string
  fileName: string
  path: string
  archive: string
  size: number
  installPath: string
  javaPath: string
  stripComponents: number
  inspection: string
}
export interface JdkRuntimeOption {
  value: string
  profile: string
  label: string
  version: string
  javaMajor: number
  complete: boolean
  systems: string[]
  missingSystems: string[]
  platforms: string[]
  missingPlatforms: string[]
  versions: string[]
  versionsBySystem: Record<string, string[]>
  versionsByPlatform: Record<string, string>
  selectedVersions: Record<string, string>
  selectors: string[]
  artifacts: Record<string, JdkRuntimeArtifact>
  names: string[]
  files: Record<string, string[]>
  archives: number
  archiveFormats: string[]
}
export interface JdkCatalogStatus {
  available: boolean
  root: string
  requiredSystems: string[]
  requiredPlatforms?: string[]
  supportedPlatforms?: string[]
  scannedArchives: number
  matchedArchives: number
  ignoredArchives: number
  ignoredCandidates?: Array<{
    path: string
    name: string
    version: string
    system: string | null
    reason: string
  }>
  mode?: 'exact-runtime-profiles'
  versionSource?: 'archive-release-metadata-or-file-name'
  systemSource?: 'catalog-branch-and-release-metadata'
  error?: string
}
export interface GameVersionOption {
  value: string
  label: string
}
export interface GameVersionCatalogStatus {
  available: boolean
  root: string
  directories: number
  ignoredEntries: number
  error?: string
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
export interface AchievementAdminServer {
  serverId: string
  definitions: number
  progressRows: number
  players: number
  events: number
}
export interface AchievementAdminPlayer {
  uuid: string
  login: string
  realname: string
  progressRows: number
  completedCount: number
  events: number
}
export interface AchievementEconomyAdminSettings {
  enabled: boolean
  pointsPerUnit: number
  minimumPoints: number
  updatedAt: number
  updatedByUuid: string
}
export interface AchievementEconomyAdminStats {
  awardCount: number
  awardedPlayers: number
  earnedPoints: number
  exchangeCount: number
  exchangePlayers: number
  exchangedPoints: number
  availablePoints: number
  unitsGranted: number
}
export interface AchievementAdminResponse {
  available: boolean
  servers: AchievementAdminServer[]
  players: AchievementAdminPlayer[]
  selectedServerId: string
  economy?: AchievementEconomyAdminSettings | null
  economyStats?: AchievementEconomyAdminStats | null
  message?: string
}
export interface ServerRow extends JsonRow {
  id?: number | string
  serverName: string
  host?: string
  port?: number | string
  ignoreDirs?: unknown
  enabled?: string | boolean | number
  checkLib?: string | boolean | number
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


function normalizeServerArray(value: unknown, splitLegacy = false): JsonValue[] {
  const decoded = decodeJsonValue(value, [])
  if (Array.isArray(decoded)) return decoded
  if (splitLegacy && typeof decoded === 'string') {
    return decoded.split(/[\r\n,]+/).map((entry) => entry.trim()).filter(Boolean)
  }
  return []
}
export function useAdminPanel() {
  const isAdmin = appBootstrap.frontend.capabilities.includes('admin.panel')
  const activeTab = ref<AdminView>('home')
  const loading = ref(false)
  const feedback = ref<Feedback | null>(null)
  const overview = ref<Overview | null>(null)
  const hardware = ref<Hardware | null>(null)
  const siteSettings = reactive<SiteSettings>({
    siteTitle: appBootstrap.site.title || 'FoxesCraft',
    siteStatus: appBootstrap.site.status || '',
    siteDesc: appBootstrap.site.description || '',
    homeTitle: appBootstrap.site.homeTitle || appBootstrap.site.title || 'FoxesCraft',
    titleTemplate: appBootstrap.site.titleTemplate || '%page% — %site%',
    keywords: appBootstrap.site.keywords || '',
    robots: appBootstrap.site.robots || 'index,follow',
    canonicalUrl: appBootstrap.site.canonicalUrl || '',
    lang: appBootstrap.site.language || 'ru',
    locale: appBootstrap.site.locale || 'ru_RU',
    author: 'FoxesCraft',
    themeColor: appBootstrap.site.themeColor || '#152019',
    faviconUrl: '/favicon.ico',
    ogSiteName: appBootstrap.site.title || 'FoxesCraft',
    ogTitle: appBootstrap.site.homeTitle || appBootstrap.site.title || 'FoxesCraft',
    ogDescription: appBootstrap.site.description || '',
    ogImage: appBootstrap.site.ogImage || '',
    twitterCard: 'summary_large_image',
    twitterSite: '',
    twitterCreator: '',
    googleVerification: '',
    yandexVerification: '',
    bingVerification: '',
  })
  const siteSettingsUpdatedAt = ref('')
  const siteSettingsStorageReady = ref(false)
  const siteSocialImageUploading = ref(false)
  const siteSocialImageError = ref('')
  const maintenance = reactive<MaintenanceSettings>({
    enabled: false,
    allowedGroups: ['admin'],
    title: t('modules.adminpanel.useadminpanel.001'),
    message: t('modules.adminpanel.useadminpanel.002'),
    updatedAt: '',
    updatedByUuid: '',
    storageReady: false,
  })
  const sliderSettings = reactive<SliderSettings>({
    schema: 1,
    eyebrow: t('modules.adminpanel.useadminpanel.003'),
    autoplayMs: 7000,
    slides: [],
  })
  const sliderRoutes = ref<SlideRouteOption[]>([])
  const projectPages = ref<ProjectPageDraft[]>([])
  const systemPages = ref<SystemPageDraft[]>([])
  const badgePages = ref<BadgePageDraft[]>([])
  const contentBadges = ref<BadgeCatalogRow[]>([])
  const rewardDefinitions = ref<RewardDefinitionRow[]>([])
  const rewardClaimKeys = ref<RewardClaimKeyRow[]>([])
  const issuedRewardClaimCode = ref<IssuedRewardClaimCode | null>(null)
  const rewardDraft = reactive<RewardDraft>({ id: 0, rewardName: '', description: '', badgeId: 0, currencyCode: '', currencyAmount: 0, enabled: true })
  const groupOptions = ref<GroupOption[]>([])
  const badgeOptions = ref<AdminBadgeOption[]>([])
  const users = ref<UserRow[]>([])
  const userSearch = ref('')
  const selectedUser = ref<UserRow | null>(null)
  const achievementAvailable = ref(true)
  const achievementServers = ref<AchievementAdminServer[]>([])
  const achievementPlayers = ref<AchievementAdminPlayer[]>([])
  const achievementServerId = ref('')
  const achievementPlayerSearch = ref('')
  const achievementEconomy = reactive<AchievementEconomyAdminSettings>({
    enabled: true,
    pointsPerUnit: 10,
    minimumPoints: 10,
    updatedAt: 0,
    updatedByUuid: '',
  })
  const achievementEconomyStats = reactive<AchievementEconomyAdminStats>({
    awardCount: 0,
    awardedPlayers: 0,
    earnedPoints: 0,
    exchangeCount: 0,
    exchangePlayers: 0,
    exchangedPoints: 0,
    availablePoints: 0,
    unitsGranted: 0,
  })
  const userDraft = shallowReactive<UserDraft>({
    login: '',
    realname: '',
    email: '',
    userStatus: '',
    groupTag: 'guest',
    balance: normalizeBalanceMatrix(null) as unknown as JsonValue,
    badges: '',
    serversOnline: '',
  })
  const servers = ref<ServerRow[]>([])
  const jdkOptions = ref<JdkRuntimeOption[]>([])
  const jdkCatalog = ref<JdkCatalogStatus>({
    available: false,
    root: '/var/www/FoxCMS/uploads/bootstrap/runtime',
    requiredSystems: ['windows', 'linux', 'macos'],
    scannedArchives: 0,
    matchedArchives: 0,
    ignoredArchives: 0,
  })
  const gameVersionOptions = ref<GameVersionOption[]>([])
  const gameVersionCatalog = ref<GameVersionCatalogStatus>({
    available: false,
    root: '/var/www/FoxCMS/uploads/game/versions',
    directories: 0,
    ignoredEntries: 0,
  })
  const selectedServer = ref<ServerRow | null>(null)
  const serverImageUploading = ref(false)
  const serverImageError = ref('')
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
  const catalogName = ref<CatalogName>('infobox')
  const catalogRows = ref<JsonRow[]>([])
  const catalogFields = ref<string[]>([])
  const catalogDraft = ref<JsonObject>({})
  const originalCatalogKey = ref('')
  const runtimeOptionsDraft = ref<RuntimeUserOptionsDocument | null>(cloneRuntimeUserOptions())
  const runtimeOptionsUpdatedAt = ref(runtimeOptionsDraft.value?.updatedAt ?? '')
  const runtimeOptionsStorageReady = ref(false)
  const runtimePageTemplatesDraft = ref<RuntimePageTemplatesDocument | null>(null)
  const runtimePageTemplatesStorageReady = ref(false)

  const categories = computed<AdminCategoryDefinition[]>(() => runtimeAdminCategories.value.map((category) => ({
    id: category.id,
    label: category.label,
    description: category.description,
    icon: category.icon,
  })))
  const tabs = computed<AdminToolDefinition[]>(() => runtimeAdminTools.value.map((tool) => ({
    id: tool.id as AdminToolId,
    tab: tool.tab as Tab,
    category: tool.category,
    label: tool.label,
    description: tool.description,
    icon: tool.icon,
    ...(tool.catalog ? { catalog: tool.catalog as CatalogName } : {}),
  })))
  const groupedTabs = computed(() => categories.value.map((category) => ({
    ...category,
    tools: tabs.value.filter((tab) => tab.category === category.id),
  })))
  const catalogKey = computed(() => ({ infobox: 'group_name', badges: 'badgeName', groups: 'groupTag' })[catalogName.value])

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
        : t('modules.adminpanel.useadminpanel.038')
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
  async function loadSiteSettings(): Promise<void> {
    const response = await run(() => foxesApi.post<{
      settings: SiteSettings
      updatedAt: string
      storageReady: boolean
    }>({ admPanel: 'siteSettings' }))
    if (!response) return
    Object.assign(siteSettings, response.settings)
    siteSettingsUpdatedAt.value = response.updatedAt || ''
    siteSettingsStorageReady.value = response.storageReady
    siteSocialImageError.value = ''
  }
  function clearSiteSocialImage(): void {
    siteSettings.ogImage = ''
    siteSocialImageError.value = ''
  }
  async function uploadSiteSocialImage(file: File): Promise<void> {
    if (siteSocialImageUploading.value) return
    const body = new FormData()
    body.set('admPanel', 'uploadSiteSocialImage')
    body.set('image', file, file.name)
    siteSocialImageUploading.value = true
    siteSocialImageError.value = ''
    try {
      const response = await run(() => foxesApi.postFormData<Feedback & { image: string }>(body))
      if (response?.image) {
        siteSettings.ogImage = response.image
        feedback.value = response
      } else {
        siteSocialImageError.value = feedback.value?.message || t('modules.adminpanel.useadminpanel.039')
      }
    } finally {
      siteSocialImageUploading.value = false
    }
  }
  async function saveSiteSettings(): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & {
      settings: SiteSettings
      updatedAt: string
      storageReady: boolean
    }>({
      admPanel: 'saveSiteSettings',
      entry: JSON.stringify(siteSettings),
    }))
    if (!response) return
    feedback.value = response
    Object.assign(siteSettings, response.settings)
    siteSettingsUpdatedAt.value = response.updatedAt || ''
    siteSettingsStorageReady.value = response.storageReady
  }
  async function loadUserOptionsEditor(): Promise<void> {
    const response = await run(() => foxesApi.post<{
      document: RuntimeUserOptionsDocument
      updatedAt: string
      storageReady: boolean
    }>({ admPanel: 'userOptions' }))
    if (!response) return
    const installed = installRuntimeUserOptions(response.document)
    runtimeOptionsDraft.value = structuredClone(installed)
    runtimeOptionsUpdatedAt.value = response.updatedAt || installed.updatedAt
    runtimeOptionsStorageReady.value = response.storageReady
  }
  async function saveUserOptionsEditor(templateId: string, source: string): Promise<void> {
    if (!runtimeOptionsDraft.value || source.trim() === '') return
    const response = await run(() => foxesApi.post<Feedback & {
      document: RuntimeUserOptionsDocument
      updatedAt: string
      storageReady: boolean
    }>({ admPanel: 'saveUserOptions', entry: JSON.stringify({ templateId, source }) }))
    if (!response) return
    feedback.value = response
    const installed = installRuntimeUserOptions(response.document)
    runtimeOptionsDraft.value = structuredClone(installed)
    runtimeOptionsUpdatedAt.value = response.updatedAt || installed.updatedAt
    runtimeOptionsStorageReady.value = response.storageReady
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
      title: t('modules.adminpanel.useadminpanel.040'),
      description: '',
      image: 'img/slides/slide1.png',
      route: fallbackRoute,
      action: t('modules.adminpanel.useadminpanel.041'),
      secondaryRoute: '',
      secondaryAction: '',
    })
  }
  function removeSlide(index: number): void {
    const slide = sliderSettings.slides[index]
    if (!slide || !window.confirm(t('modules.adminpanel.useadminpanel.042', [slide.title]))) return
    sliderSettings.slides.splice(index, 1)
  }
  function reorderSlide(fromIndex: number, toIndex: number): void {
    if (
      fromIndex < 0 || toIndex < 0
      || fromIndex >= sliderSettings.slides.length || toIndex >= sliderSettings.slides.length
      || fromIndex === toIndex
    ) return
    const [slide] = sliderSettings.slides.splice(fromIndex, 1)
    if (slide) sliderSettings.slides.splice(toIndex, 0, slide)
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
    return t('modules.adminpanel.useadminpanel.043', [badgeName, slug])
  }
  async function loadContent(): Promise<void> {
    const response = await run(() => foxesApi.post<{
      projectPages: RuntimeContentDocument<ProjectPageDraft>
      pageTemplates: RuntimePageTemplatesDocument
      pageTemplatesStorageReady: boolean
      systemPages: SystemPageDraft[]
      badgePages: { pages: BadgePageDraft[] }
      badges: BadgeCatalogRow[]
    }>({ admPanel: 'content' }))
    if (!response) return
    projectPages.value = cloneContent(response.projectPages.pages)
    runtimePageTemplatesDraft.value = structuredClone(installRuntimePageTemplates(response.pageTemplates))
    runtimePageTemplatesStorageReady.value = response.pageTemplatesStorageReady
    systemPages.value = cloneContent(response.systemPages ?? [])
    badgePages.value = cloneContent(response.badgePages.pages)
    contentBadges.value = response.badges.map((badge) => ({ ...badge, id: Number(badge.id) }))
  }

  function applyRewardDraft(source?: RewardDefinitionRow): void {
    rewardDraft.id = source?.id ?? 0
    rewardDraft.rewardName = source?.rewardName ?? ''
    rewardDraft.description = source?.description ?? ''
    rewardDraft.badgeId = source?.badgeId ?? 0
    rewardDraft.currencyCode = source?.currencyCode ?? ''
    rewardDraft.currencyAmount = source?.currencyAmount ?? 0
    rewardDraft.enabled = source?.enabled ?? true
  }

  async function loadRewards(): Promise<void> {
    const response = await run(() => foxesApi.post<{
      rewards: RewardDefinitionRow[]
      claimKeys: RewardClaimKeyRow[]
      badges: AdminBadgeOption[]
    }>({ admPanel: 'rewards' }))
    if (!response) return
    rewardDefinitions.value = response.rewards.map((entry) => ({
      ...entry,
      id: Number(entry.id),
      badgeId: Number(entry.badgeId),
      currencyAmount: Number(entry.currencyAmount),
      createdAt: Number(entry.createdAt),
      updatedAt: Number(entry.updatedAt),
      keysCount: Number(entry.keysCount),
      claimsCount: Number(entry.claimsCount),
      enabled: entry.enabled === true,
    }))
    rewardClaimKeys.value = response.claimKeys.map((entry) => ({
      ...entry,
      id: Number(entry.id),
      rewardId: Number(entry.rewardId),
      badgeId: Number(entry.badgeId),
      currencyAmount: Number(entry.currencyAmount),
      usesCount: Number(entry.usesCount),
      claimsCount: Number(entry.claimsCount),
      createdAt: Number(entry.createdAt),
      updatedAt: Number(entry.updatedAt),
      lastClaimedAt: entry.lastClaimedAt === null ? null : Number(entry.lastClaimedAt),
      enabled: entry.enabled === true,
    }))
    badgeOptions.value = response.badges.map((badge) => ({ ...badge, id: Number(badge.id) }))
    if (rewardDraft.id > 0) {
      applyRewardDraft(rewardDefinitions.value.find((entry) => entry.id === rewardDraft.id))
    }
  }

  function newReward(): void { applyRewardDraft() }
  function editReward(reward: RewardDefinitionRow): void { applyRewardDraft(reward) }
  async function saveReward(): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & { reward: RewardDefinitionRow }>({
      admPanel: 'saveReward',
      entry: JSON.stringify(rewardDraft),
    }))
    if (!response) return
    feedback.value = response
    await loadRewards()
    applyRewardDraft(rewardDefinitions.value.find((entry) => entry.id === Number(response.reward.id)))
  }
  async function deleteReward(reward: RewardDefinitionRow): Promise<void> {
    if (!window.confirm(t('modules.adminpanel.useadminpanel.044', [reward.rewardName]))) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'deleteReward', rewardId: reward.id }))
    if (!response) return
    feedback.value = response
    if (rewardDraft.id === reward.id) applyRewardDraft()
    await loadRewards()
  }
  async function issueRewardClaimKey(
    rewardId: number,
    usageMode: RewardClaimUsageMode,
    accessMode: RewardClaimAccessMode,
    publicPlacement: string,
  ): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & IssuedRewardClaimCode>({
      admPanel: 'issueRewardClaimKey', rewardId, usageMode, accessMode, publicPlacement,
    }))
    if (!response) return
    feedback.value = response
    issuedRewardClaimCode.value = { token: response.token, entry: response.entry }
    await loadRewards()
  }
  async function revokeRewardClaimKey(keyId: number): Promise<void> {
    const response = await run(() => foxesApi.post<Feedback & { entry: RewardClaimKeyRow }>({
      admPanel: 'revokeRewardClaimKey', keyId,
    }))
    if (!response) return
    feedback.value = response
    if (issuedRewardClaimCode.value?.entry.id === keyId) issuedRewardClaimCode.value = null
    await loadRewards()
  }
  function clearIssuedRewardClaimCode(): void { issuedRewardClaimCode.value = null }

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

  async function savePageTemplate(templateId: string, source: string): Promise<void> {
    if (source.trim() === '') return
    const response = await run(() => foxesApi.post<Feedback & {
      document: RuntimePageTemplatesDocument
      storageReady: boolean
    }>({
      admPanel: 'savePageTemplate',
      entry: JSON.stringify({ templateId, source }),
    }))
    if (!response) return
    feedback.value = response
    runtimePageTemplatesDraft.value = structuredClone(installRuntimePageTemplates(response.document))
    runtimePageTemplatesStorageReady.value = response.storageReady
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
    const response = await run(() => foxesApi.post<{
      items: UserRow[]
      groups: GroupOption[]
      badgeOptions: AdminBadgeOption[]
    }>({ admPanel: 'users', search: userSearch.value, limit: 100 }))
    if (!response) return
    const preferredUuid = options.selectUuid ?? selectedUser.value?.uuid ?? ''
    users.value = response.items
    setGroups(response.groups)
    badgeOptions.value = response.badgeOptions.map((badge) => ({ ...badge, id: Number(badge.id) }))

    if (options.autoSelect === false) return
    const preferred = preferredUuid
      ? users.value.find((user) => user.uuid === preferredUuid) ?? null
      : null
    if (preferred) {
      editUser(preferred)
      return
    }
    const next = users.value[0] ?? null
    if (next) editUser(next)
    else selectedUser.value = null
  }
  async function searchUsers(): Promise<void> {
    await loadUsers({ autoSelect: false })
  }
  function editUser(user: UserRow): void {
    selectedUser.value = user
    userDraft.login = user.login
    userDraft.realname = String(user.realname ?? '')
    userDraft.email = String(user.email ?? '')
    userDraft.userStatus = String(user.userStatus ?? '')
    userDraft.groupTag = user.groupTag || 'guest'
    userDraft.balance = normalizeBalanceMatrix(decodeJsonValue(user.balance)) as unknown as JsonValue
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
      serversOnline: userDraft.serversOnline,
    }
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'updateUser', userUuid: selectedUuid, entry: JSON.stringify(entry) }))
    if (response) {
      feedback.value = response
      selectedUser.value = {
        ...selectedUser.value,
        ...entry,
        uuid: selectedUuid,
        groupName: groupOptions.value.find((group) => group.groupTag === entry.groupTag)?.groupName,
        groupColor: groupOptions.value.find((group) => group.groupTag === entry.groupTag)?.groupColor,
      }
      await loadUsers({ selectUuid: selectedUuid, autoSelect: false })
    }
  }

  function applySelectedUserBadges(userUuid: string, badges: JsonValue): void {
    userDraft.badges = badges
    if (selectedUser.value?.uuid === userUuid) {
      selectedUser.value = { ...selectedUser.value, badges }
    }
    const index = users.value.findIndex((user) => user.uuid === userUuid)
    if (index >= 0) users.value[index] = { ...users.value[index], badges }
  }

  async function grantUserBadge(badgeId: number, reason: string): Promise<void> {
    if (!selectedUser.value) return
    const userUuid = selectedUser.value.uuid
    const response = await run(() => foxesApi.post<Feedback & { badges: JsonValue }>({
      admPanel: 'grantUserBadge', userUuid, badgeId, reason,
    }))
    if (!response) return
    feedback.value = response
    applySelectedUserBadges(userUuid, response.badges)
  }

  async function revokeUserBadge(badgeName: string, reason: string): Promise<void> {
    if (!selectedUser.value) return
    const userUuid = selectedUser.value.uuid
    const response = await run(() => foxesApi.post<Feedback & { badges: JsonValue }>({
      admPanel: 'revokeUserBadge', userUuid, badgeName, reason,
    }))
    if (!response) return
    feedback.value = response
    applySelectedUserBadges(userUuid, response.badges)
  }

  async function loadAchievementAdmin(): Promise<void> {
    const response = await run(() => foxesApi.post<AchievementAdminResponse>({
      admPanel: 'achievementsAdmin',
      serverId: achievementServerId.value,
      search: achievementPlayerSearch.value,
      limit: 100,
    }))
    if (!response) return
    achievementAvailable.value = response.available !== false
    achievementServers.value = Array.isArray(response.servers) ? response.servers.map((entry) => ({
      serverId: String(entry.serverId ?? ''),
      definitions: Math.max(0, Number(entry.definitions) || 0),
      progressRows: Math.max(0, Number(entry.progressRows) || 0),
      players: Math.max(0, Number(entry.players) || 0),
      events: Math.max(0, Number(entry.events) || 0),
    })) : []
    achievementPlayers.value = Array.isArray(response.players) ? response.players.map((entry) => ({
      uuid: String(entry.uuid ?? ''),
      login: String(entry.login ?? ''),
      realname: String(entry.realname ?? ''),
      progressRows: Math.max(0, Number(entry.progressRows) || 0),
      completedCount: Math.max(0, Number(entry.completedCount) || 0),
      events: Math.max(0, Number(entry.events) || 0),
    })) : []
    achievementServerId.value = String(response.selectedServerId ?? '')
    if (response.economy) {
      achievementEconomy.enabled = response.economy.enabled === true
      achievementEconomy.pointsPerUnit = Math.max(1, Number(response.economy.pointsPerUnit) || 10)
      achievementEconomy.minimumPoints = Math.max(1, Number(response.economy.minimumPoints) || 10)
      achievementEconomy.updatedAt = Math.max(0, Number(response.economy.updatedAt) || 0)
      achievementEconomy.updatedByUuid = String(response.economy.updatedByUuid ?? '')
    }
    if (response.economyStats) {
      achievementEconomyStats.awardCount = Math.max(0, Number(response.economyStats.awardCount) || 0)
      achievementEconomyStats.awardedPlayers = Math.max(0, Number(response.economyStats.awardedPlayers) || 0)
      achievementEconomyStats.earnedPoints = Math.max(0, Number(response.economyStats.earnedPoints) || 0)
      achievementEconomyStats.exchangeCount = Math.max(0, Number(response.economyStats.exchangeCount) || 0)
      achievementEconomyStats.exchangePlayers = Math.max(0, Number(response.economyStats.exchangePlayers) || 0)
      achievementEconomyStats.exchangedPoints = Math.max(0, Number(response.economyStats.exchangedPoints) || 0)
      achievementEconomyStats.availablePoints = Math.max(0, Number(response.economyStats.availablePoints) || 0)
      achievementEconomyStats.unitsGranted = Math.max(0, Number(response.economyStats.unitsGranted) || 0)
    }
    if (response.message) feedback.value = { type: response.available === false ? 'warning' : 'success', message: response.message }
  }
  async function saveAchievementEconomy(settings: AchievementEconomyAdminSettings): Promise<void> {
    if (loading.value) return
    const response = await run(() => foxesApi.post<Feedback & {
      economy?: AchievementEconomyAdminSettings
      economyStats?: AchievementEconomyAdminStats
    }>({
      admPanel: 'saveAchievementEconomy',
      enabled: settings.enabled,
      pointsPerUnit: Math.max(1, Math.trunc(settings.pointsPerUnit)),
      minimumPoints: Math.max(1, Math.trunc(settings.minimumPoints)),
    }))
    if (!response) return
    feedback.value = response
    await loadAchievementAdmin()
  }
  async function selectAchievementServer(serverId: string): Promise<void> {
    achievementServerId.value = serverId.trim()
    achievementPlayerSearch.value = ''
    await loadAchievementAdmin()
  }
  function setAchievementPlayerSearch(value: string): void {
    achievementPlayerSearch.value = value
  }
  async function searchAchievementPlayers(): Promise<void> {
    await loadAchievementAdmin()
  }
  async function clearAchievementServer(): Promise<void> {
    const server = achievementServers.value.find((entry) => entry.serverId === achievementServerId.value)
    if (!server || loading.value) return
    if (!window.confirm(t('modules.adminpanel.useadminpanel.053', [server.serverId, server.definitions, server.progressRows, server.events]))) return
    const response = await run(() => foxesApi.post<Feedback>({
      admPanel: 'clearAchievementServer', serverId: server.serverId,
    }))
    if (!response) return
    feedback.value = response
    await loadAchievementAdmin()
  }
  async function clearAchievementPlayer(player: AchievementAdminPlayer): Promise<void> {
    if (!achievementServerId.value || !player.uuid || loading.value) return
    const label = player.login || player.uuid
    if (!window.confirm(t('modules.adminpanel.useadminpanel.054', [label, achievementServerId.value, player.progressRows, player.events]))) return
    const response = await run(() => foxesApi.post<Feedback>({
      admPanel: 'clearAchievementPlayer', serverId: achievementServerId.value, playerUuid: player.uuid,
    }))
    if (!response) return
    feedback.value = response
    await loadAchievementAdmin()
  }

  async function loadServers(): Promise<void> {
    const response = await run(() => foxesApi.post<{
      items: ServerRow[]
      groups: GroupOption[]
      jdkOptions: JdkRuntimeOption[]
      jdkCatalog: JdkCatalogStatus
      gameVersionOptions: GameVersionOption[]
      gameVersionCatalog: GameVersionCatalogStatus
    }>({ admPanel: 'servers' }))
    if (!response) return
    servers.value = response.items
    jdkOptions.value = response.jdkOptions.map((option) => ({
      ...option,
      systems: [...option.systems],
      missingSystems: [...(option.missingSystems ?? [])],
      platforms: [...option.platforms],
      missingPlatforms: [...(option.missingPlatforms ?? [])],
      versions: [...option.versions],
      versionsBySystem: Object.fromEntries(
        Object.entries(option.versionsBySystem).map(([system, versions]) => [system, [...versions]]),
      ),
      versionsByPlatform: { ...option.versionsByPlatform },
      selectedVersions: { ...option.selectedVersions },
      selectors: [...option.selectors],
      artifacts: Object.fromEntries(
        Object.entries(option.artifacts).map(([platform, artifact]) => [platform, { ...artifact }]),
      ),
      names: [...option.names],
      files: Object.fromEntries(
        Object.entries(option.files).map(([system, files]) => [system, [...files]]),
      ),
      archiveFormats: [...option.archiveFormats],
    }))
    jdkCatalog.value = {
      ...response.jdkCatalog,
      requiredSystems: [...response.jdkCatalog.requiredSystems],
    }
    gameVersionOptions.value = Array.isArray(response.gameVersionOptions)
      ? response.gameVersionOptions.map((option) => ({
          value: String(option.value ?? '').trim(),
          label: String(option.label ?? option.value ?? '').trim(),
        })).filter((option) => option.value !== '')
      : []
    gameVersionCatalog.value = response.gameVersionCatalog
      ? { ...response.gameVersionCatalog }
      : {
          available: false,
          root: '/var/www/FoxCMS/uploads/game/versions',
          directories: 0,
          ignoredEntries: 0,
        }
    setGroups(response.groups)
  }
  function newServer(): void {
    selectedServer.value = null
    serverImageError.value = ''
    Object.assign(serverDraft, {
      serverName: '', host: '', port: 25565, enabled: false, checkLib: false,
      ignoreDirs: [],
      serverGroups: groupOptions.value.filter((group) => group.groupTag !== 'admin').map((group) => group.groupTag),
      serverDescription: '', serverVersion: gameVersionOptions.value[0]?.value ?? '', jreVersion: jdkOptions.value[0]?.value ?? '', serverImage: '', modsInfo: [],
    })
  }
  function editServer(server: ServerRow): void {
    selectedServer.value = server
    serverImageError.value = ''
    const rawRuntime = String(server.jreVersion ?? '').trim()
    const parsedRuntimeMajor = javaMajorFromSelector(rawRuntime)
    const runtimeMajor = jdkOptions.value.find((option) => (
      option.value === rawRuntime
      || option.profile === rawRuntime
      || option.selectors.some((selector) => selector.toLocaleLowerCase() === rawRuntime.toLocaleLowerCase())
      || (parsedRuntimeMajor !== '' && String(option.javaMajor) === parsedRuntimeMajor)
    ))?.value ?? (parsedRuntimeMajor || rawRuntime)
    Object.assign(serverDraft, server, {
      enabled: server.enabled === true || server.enabled === 'true' || Number(server.enabled) === 1,
      checkLib: server.checkLib === true || server.checkLib === 'true' || Number(server.checkLib) === 1,
      ignoreDirs: normalizeServerArray(server.ignoreDirs, true),
      serverGroups: Array.isArray(server.serverGroups) ? server.serverGroups.map(String) : [],
      jreVersion: runtimeMajor,
      modsInfo: normalizeServerArray(server.modsInfo),
    })
  }
  function clearServerImage(): void {
    serverDraft.serverImage = ''
    serverImageError.value = ''
  }

  async function uploadServerImage(file: File): Promise<void> {
    if (serverImageUploading.value) return
    const body = new FormData()
    body.set('admPanel', 'uploadServerImage')
    body.set('image', file, file.name)
    serverImageUploading.value = true
    serverImageError.value = ''
    try {
      const response = await run(() => foxesApi.postFormData<Feedback & { image: string }>(body))
      if (response?.image) {
        serverDraft.serverImage = response.image
        feedback.value = response
      } else {
        serverImageError.value = feedback.value?.message || t('modules.adminpanel.useadminpanel.045')
      }
    } finally {
      serverImageUploading.value = false
    }
  }

  async function saveServer(): Promise<void> {
    const entry = {
      ...serverDraft,
      ignoreDirs: normalizeServerArray(serverDraft.ignoreDirs, true),
      serverGroups: [...serverDraft.serverGroups],
      modsInfo: normalizeServerArray(serverDraft.modsInfo),
    }
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'saveServer', originalName: selectedServer.value?.serverName ?? '', entry: JSON.stringify(entry) }))
    if (response) { feedback.value = response; await loadServers(); newServer() }
  }
  async function deleteServer(server: ServerRow): Promise<void> {
    if (!window.confirm(t('modules.adminpanel.useadminpanel.046', [server.serverName]))) return
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
    const name = window.prompt(t('modules.adminpanel.useadminpanel.047'), entry.name)?.trim()
    if (!name || name === entry.name) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'fileRename', path: entry.path, name }))
    if (response) { feedback.value = response; await loadFiles() }
  }
  async function deleteFile(entry: FileEntry): Promise<void> {
    const label = entry.type === 'directory' ? t('modules.adminpanel.useadminpanel.048', [entry.name]) : t('modules.adminpanel.useadminpanel.049', [entry.name])
    if (!window.confirm(t('modules.adminpanel.useadminpanel.050', [label]))) return
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
    if (!window.confirm(t('modules.adminpanel.useadminpanel.051', [logFile.value]))) return
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
    if (!key || !window.confirm(t('modules.adminpanel.useadminpanel.052', [key]))) return
    const response = await run(() => foxesApi.post<Feedback>({ admPanel: 'deleteCatalogEntry', catalog: catalogName.value, key }))
    if (response) {
      feedback.value = response
      if (catalogName.value === 'badges') invalidateContentRegistry('badges')
      await loadCatalog()
    }
  }
  async function activate(section: AdminSection): Promise<void> {
    feedback.value = null
    if (section === 'home') {
      activeTab.value = 'home'
      if (!overview.value || !hardware.value) await loadOverview()
      return
    }
    const tool = tabs.value.find((entry) => entry.id === section)
    if (!tool) {
      activeTab.value = 'home'
      return
    }
    activeTab.value = tool.tab
    if (tool.catalog) catalogName.value = tool.catalog
    if (tool.tab === 'overview') await loadOverview()
    if (tool.tab === 'settings') await loadSiteSettings()
    if (tool.tab === 'slides') await loadSlides()
    if (tool.tab === 'content') await loadContent()
    if (tool.tab === 'rewards') await loadRewards()
    if (tool.tab === 'maintenance') await loadMaintenance()
    if (tool.tab === 'users') await loadUsers()
    if (tool.tab === 'achievements') await loadAchievementAdmin()
    if (tool.tab === 'servers') { await loadServers(); if (!selectedServer.value) newServer() }
    if (tool.tab === 'files') await loadFiles()
    if (tool.tab === 'logs') await loadLogs()
    if (tool.tab === 'catalogs') await loadCatalog()
    if (tool.tab === 'runtime-options') await loadUserOptionsEditor()
  }
  void loadRuntimeUserOptions().catch((error: unknown) => {
    console.error('[FoxesCraft] Runtime admin options failed to load', error)
    feedback.value = {
      type: 'error',
      message: error instanceof Error && error.message.trim() !== ''
        ? error.message
        : t('engine.runtime.useroptions.010'),
    }
  })
  watch(logFile, () => void loadLogs())
  watch(autoRefreshLogs, updateLogTimer)
  onUnmounted(() => { if (logTimer) window.clearInterval(logTimer) })

  return {
    isAdmin, activeTab, loading, feedback, overview, hardware, siteSettings, siteSettingsUpdatedAt, siteSettingsStorageReady, siteSocialImageUploading, siteSocialImageError,
    maintenance, sliderSettings, sliderRoutes, projectPages, systemPages, badgePages, contentBadges, rewardDefinitions, rewardClaimKeys, issuedRewardClaimCode, rewardDraft, groupOptions, badgeOptions,
    users, userSearch, selectedUser, userDraft, achievementAvailable, achievementServers, achievementPlayers, achievementServerId, achievementPlayerSearch, achievementEconomy, achievementEconomyStats, servers, jdkOptions, jdkCatalog, gameVersionOptions, gameVersionCatalog, selectedServer, serverDraft, serverImageUploading, serverImageError,
    filePath, fileParent, fileEntries, fileWritable, fileTotalBytes, selectedUpload, fileUploading, newDirectoryName,
    logFile, logEntries, autoRefreshLogs, catalogName, catalogRows, catalogDraft,
    originalCatalogKey, categories, tabs, groupedTabs, catalogKey, formatTimestamp, runtimeUserOptionsRevision,
    runtimeOptionsDraft, runtimeOptionsUpdatedAt, runtimeOptionsStorageReady, runtimePageTemplatesDraft, runtimePageTemplatesStorageReady,
    loadSiteSettings, saveSiteSettings, clearSiteSocialImage, uploadSiteSocialImage, loadUserOptionsEditor, saveUserOptionsEditor, loadMaintenance,
    saveMaintenance, loadSlides, addSlide, removeSlide, reorderSlide, uploadSlideImage, saveSlides,
    loadContent, loadRewards, newReward, editReward, saveReward, deleteReward, issueRewardClaimKey, revokeRewardClaimKey, clearIssuedRewardClaimCode, ensureBadgePage, removeBadgePage, saveProjectPages, savePageTemplate, saveBadgePage, deleteBadgePage,
    loadUsers, searchUsers, editUser, saveUser, grantUserBadge, revokeUserBadge, loadAchievementAdmin, saveAchievementEconomy, selectAchievementServer, setAchievementPlayerSearch, searchAchievementPlayers, clearAchievementServer, clearAchievementPlayer, newServer, editServer, clearServerImage, uploadServerImage, saveServer,
    deleteServer, loadFiles, selectUpload, uploadFile, createDirectory, renameFile, deleteFile, openFile,
    loadLogs, clearLogs, loadCatalog, newCatalogEntry, editCatalogEntry,
    saveCatalogEntry, deleteCatalogEntry, activate,
  }
}
