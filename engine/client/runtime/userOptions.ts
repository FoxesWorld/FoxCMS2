import { t } from '@/i18n'
import { computed, reactive, readonly } from 'vue'
import { loadContentRegistry } from '@/content/contentData'
import type { SettingsTab } from '@/contracts/user-pages'

export type ProfileOptionComponent = 'ProfileOption' | 'AppearanceOption' | 'SecurityOption'
export type RuntimeAdminComponent = 'Overview' | 'SiteSettings' | 'Slides' | 'Content' | 'Rewards'
  | 'Maintenance' | 'Users' | 'Achievements' | 'Servers' | 'FileManager' | 'Logs' | 'Catalogs' | 'RuntimeOptions'
export type RuntimeAdminTab = 'overview' | 'settings' | 'slides' | 'content' | 'rewards'
  | 'maintenance' | 'users' | 'achievements' | 'servers' | 'files' | 'logs' | 'catalogs' | 'runtime-options'
export type RuntimeCatalogName = 'infobox' | 'badges' | 'groups'

export interface RuntimeProfileOption {
  id: SettingsTab
  component: ProfileOptionComponent
  label: string
  description: string
  icon: string
  order: number
  enabled: boolean
}
export interface RuntimeAdminCategory {
  id: string
  label: string
  description: string
  icon: string
  order: number
  enabled: boolean
}
export interface RuntimeAdminTool {
  id: string
  component: RuntimeAdminComponent
  tab: RuntimeAdminTab
  category: string
  label: string
  description: string
  icon: string
  order: number
  enabled: boolean
  catalog?: RuntimeCatalogName
  protected?: boolean
}
export interface RuntimeUserOptionsTemplate {
  id: 'profile-settings' | 'admin-panel'
  file: string
  revision: number
  updatedAt: string
  html?: string
  moduleUrl: string
  moduleFile: string
  source?: string
}
export interface RuntimeUserOptionsDocument {
  schema: 1
  revision: number
  updatedAt: string
  profile: { tabs: RuntimeProfileOption[] }
  admin: { categories: RuntimeAdminCategory[]; tools: RuntimeAdminTool[] }
  templates: { profileSettings: RuntimeUserOptionsTemplate; adminPanel: RuntimeUserOptionsTemplate }
}

const profileBindings = new Map<SettingsTab, ProfileOptionComponent>([
  ['profile', 'ProfileOption'],
  ['appearance', 'AppearanceOption'],
  ['security', 'SecurityOption'],
])
const adminBindings = new Map<string, { component: RuntimeAdminComponent; tab: RuntimeAdminTab; catalog?: RuntimeCatalogName }>([
  ['overview', { component: 'Overview', tab: 'overview' }],
  ['logs', { component: 'Logs', tab: 'logs' }],
  ['users', { component: 'Users', tab: 'users' }],
  ['achievements', { component: 'Achievements', tab: 'achievements' }],
  ['infobox', { component: 'Catalogs', tab: 'catalogs', catalog: 'infobox' }],
  ['badges', { component: 'Catalogs', tab: 'catalogs', catalog: 'badges' }],
  ['rewards', { component: 'Rewards', tab: 'rewards' }],
  ['groups', { component: 'Catalogs', tab: 'catalogs', catalog: 'groups' }],
  ['content', { component: 'Content', tab: 'content' }],
  ['slides', { component: 'Slides', tab: 'slides' }],
  ['settings', { component: 'SiteSettings', tab: 'settings' }],
  ['runtime-options', { component: 'RuntimeOptions', tab: 'runtime-options' }],
  ['servers', { component: 'Servers', tab: 'servers' }],
  ['files', { component: 'FileManager', tab: 'files' }],
  ['maintenance', { component: 'Maintenance', tab: 'maintenance' }],
])
const idPattern = /^[a-z][a-z0-9-]{1,63}$/
const runtimeModuleUrlPattern = /^\/templates\/[A-Za-z0-9_-]+\/assets\/runtime\/templates\/([a-z][a-z0-9-]{1,63})\.(\d+)\.js\?v=(\d+)$/u
const iconPattern = /^fa-[a-z0-9-]{1,63}$/
const state = reactive<{
  document: RuntimeUserOptionsDocument | null
  loading: boolean
  loaded: boolean
  error: string
}>({ document: null, loading: false, loaded: false, error: '' })
let loadingPromise: Promise<RuntimeUserOptionsDocument> | null = null

function object(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {}
}
function text(value: unknown, maximum: number, allowEmpty = false): string {
  const normalized = typeof value === 'string' ? value.trim() : ''
  if ((!allowEmpty && normalized === '') || normalized.length > maximum) throw new Error(t('engine.runtime.useroptions.001'))
  return normalized
}
function order(value: unknown): number {
  const normalized = Number(value)
  if (!Number.isInteger(normalized) || normalized < 0 || normalized > 10_000) throw new Error(t('engine.runtime.useroptions.002'))
  return normalized
}
function icon(value: unknown): string {
  const normalized = text(value, 66)
  if (!iconPattern.test(normalized)) throw new Error(t('engine.runtime.useroptions.003'))
  return normalized
}
function enabled(value: unknown): boolean { return value === true }
function sorted<T extends { id: string; order: number }>(items: T[]): T[] {
  return items.sort((left, right) => left.order - right.order || left.id.localeCompare(right.id))
}
function normalizeDocument(value: unknown): RuntimeUserOptionsDocument {
  const source = object(value)
  if (source.schema !== 1) throw new Error(t('engine.runtime.useroptions.004'))
  const profile = object(source.profile)
  const admin = object(source.admin)
  if (!Array.isArray(profile.tabs) || !Array.isArray(admin.categories) || !Array.isArray(admin.tools)) {
    throw new Error(t('engine.runtime.useroptions.005'))
  }
  const profileTabs = sorted(profile.tabs.map((raw): RuntimeProfileOption => {
    const entry = object(raw)
    const id = String(entry.id ?? '') as SettingsTab
    const expected = profileBindings.get(id)
    if (!expected || entry.component !== expected) throw new Error(`Invalid profile runtime adapter: ${id}`)
    return {
      id, component: expected, label: text(entry.label, 80), description: text(entry.description, 240, true),
      icon: icon(entry.icon), order: order(entry.order), enabled: enabled(entry.enabled),
    }
  }))
  if (new Set(profileTabs.map((entry) => entry.id)).size !== profileBindings.size || !profileTabs.some((entry) => entry.enabled)) {
    throw new Error(t('engine.runtime.useroptions.006'))
  }
  const categories = sorted(admin.categories.map((raw): RuntimeAdminCategory => {
    const entry = object(raw)
    const id = String(entry.id ?? '')
    if (!idPattern.test(id)) throw new Error(`Invalid runtime category: ${id}`)
    return {
      id, label: text(entry.label, 80), description: text(entry.description, 240, true), icon: icon(entry.icon),
      order: order(entry.order), enabled: enabled(entry.enabled),
    }
  }))
  const categoryIds = new Set(categories.map((entry) => entry.id))
  if (categories.length === 0 || categoryIds.size !== categories.length) throw new Error(t('engine.runtime.useroptions.007'))
  const tools = sorted(admin.tools.map((raw): RuntimeAdminTool => {
    const entry = object(raw)
    const id = String(entry.id ?? '')
    const binding = adminBindings.get(id)
    if (!binding || entry.component !== binding.component || entry.tab !== binding.tab || !categoryIds.has(String(entry.category ?? ''))) {
      throw new Error(`Invalid admin runtime adapter: ${id}`)
    }
    if (binding.catalog && entry.catalog !== binding.catalog) throw new Error(`Invalid runtime catalog binding: ${id}`)
    const tool: RuntimeAdminTool = {
      id, component: binding.component, tab: binding.tab, category: String(entry.category),
      label: text(entry.label, 80), description: text(entry.description, 240, true), icon: icon(entry.icon),
      order: order(entry.order), enabled: id === 'runtime-options' ? true : enabled(entry.enabled),
    }
    if (binding.catalog) tool.catalog = binding.catalog
    if (id === 'runtime-options') tool.protected = true
    return tool
  }))
  if (tools.length !== adminBindings.size || new Set(tools.map((entry) => entry.id)).size !== tools.length) {
    throw new Error(t('engine.runtime.useroptions.008'))
  }
  const categoriesById = new Map(categories.map((entry) => [entry.id, entry]))
  if (tools.some((tool) => tool.enabled && !categoriesById.get(tool.category)?.enabled)) {
    throw new Error(t('engine.runtime.useroptions.009'))
  }
  const templates = object(source.templates)
  function runtimeTemplate(raw: unknown, expectedId: RuntimeUserOptionsTemplate['id']): RuntimeUserOptionsTemplate {
    const entry = object(raw)
    if (entry.id !== expectedId || typeof entry.file !== 'string' || !entry.file.endsWith('.tpl')
      || (entry.html !== undefined && typeof entry.html !== 'string')
      || typeof entry.moduleUrl !== 'string' || typeof entry.moduleFile !== 'string') {
      throw new Error(t('engine.runtime.useroptions.005'))
    }
    const revision = Math.max(1, Math.trunc(Number(entry.revision) || 1))
    const moduleMatch = entry.moduleUrl.match(runtimeModuleUrlPattern)
    if (!moduleMatch || moduleMatch[1] !== expectedId || Number(moduleMatch[2]) !== revision
      || Number(moduleMatch[3]) !== revision || entry.moduleFile !== `${expectedId}.${revision}.js`) {
      throw new Error(t('engine.runtime.useroptions.005'))
    }
    return {
      id: expectedId,
      file: entry.file,
      revision,
      updatedAt: typeof entry.updatedAt === 'string' ? entry.updatedAt : '',
      moduleUrl: entry.moduleUrl,
      moduleFile: entry.moduleFile,
      ...(typeof entry.html === 'string' ? { html: entry.html } : {}),
      ...(typeof entry.source === 'string' ? { source: entry.source } : {}),
    }
  }
  return {
    schema: 1,
    revision: Math.max(1, Math.trunc(Number(source.revision) || 1)),
    updatedAt: typeof source.updatedAt === 'string' ? source.updatedAt : '',
    profile: { tabs: profileTabs },
    admin: { categories, tools },
    templates: {
      profileSettings: runtimeTemplate(templates.profileSettings, 'profile-settings'),
      adminPanel: runtimeTemplate(templates.adminPanel, 'admin-panel'),
    },
  }
}

export const runtimeUserOptionsState = readonly(state)
export const runtimeProfileOptions = computed(() => state.document?.profile.tabs.filter((entry) => entry.enabled) ?? [])
export const runtimeAdminCategories = computed(() => state.document?.admin.categories.filter((entry) => entry.enabled) ?? [])
export const runtimeAdminTools = computed(() => {
  const categories = new Set(runtimeAdminCategories.value.map((entry) => entry.id))
  return state.document?.admin.tools.filter((entry) => entry.enabled && categories.has(entry.category)) ?? []
})
export const runtimeUserOptionsRevision = computed(() => state.document?.revision ?? 0)

export function installRuntimeUserOptions(value: unknown): RuntimeUserOptionsDocument {
  const document = normalizeDocument(value)
  state.document = document
  state.loaded = true
  state.error = ''
  return document
}

export function loadRuntimeUserOptions(force = false): Promise<RuntimeUserOptionsDocument> {
  if (!force && state.document) return Promise.resolve(state.document)
  if (!force && loadingPromise) return loadingPromise
  state.loading = true
  state.error = ''
  loadingPromise = loadContentRegistry<unknown>('user-options')
    .then(installRuntimeUserOptions)
    .catch((error: unknown) => {
      state.error = error instanceof Error ? error.message : t('engine.runtime.useroptions.010')
      throw error
    })
    .finally(() => {
      state.loading = false
      loadingPromise = null
    })
  return loadingPromise
}

export function cloneRuntimeUserOptions(): RuntimeUserOptionsDocument | null {
  return state.document ? structuredClone(state.document) : null
}
