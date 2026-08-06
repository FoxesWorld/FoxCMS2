import { computed, reactive, readonly, type ComputedRef } from 'vue'
import { t } from '@/i18n'
import { loadContentRegistry } from '@/content/contentData'

export type RuntimePageTemplateId =
  | 'static-content'
  | 'start-game'
  | 'badges'
  | 'badge'
  | 'achievements'
  | 'achievement-statistics'
  | 'achievement-tree-node'
  | 'achievement-profile-panel'

export interface RuntimePageTemplate {
  id: RuntimePageTemplateId
  file: string
  revision: number
  updatedAt: string
  html?: string
  moduleUrl: string
  moduleFile: string
  source?: string
}

export interface RuntimePageTemplatesDocument {
  schema: 1
  revision: number
  updatedAt: string
  templates: RuntimePageTemplate[]
}

const moduleUrlPattern = /^\/templates\/[A-Za-z0-9_-]+\/assets\/runtime\/templates\/([a-z][a-z0-9-]{1,63})\.(\d+)\.js\?v=(\d+)$/u
const templateIds = new Set<RuntimePageTemplateId>([
  'static-content',
  'start-game',
  'badges',
  'badge',
  'achievements',
  'achievement-statistics',
  'achievement-tree-node',
  'achievement-profile-panel',
])
const state = reactive<{
  document: RuntimePageTemplatesDocument | null
  loading: boolean
  loaded: boolean
  error: string
}>({ document: null, loading: false, loaded: false, error: '' })
let loadingPromise: Promise<RuntimePageTemplatesDocument> | null = null

function object(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {}
}

function normalizeTemplate(value: unknown): RuntimePageTemplate {
  const entry = object(value)
  const id = String(entry.id ?? '') as RuntimePageTemplateId
  if (!templateIds.has(id)
    || typeof entry.file !== 'string'
    || !entry.file.endsWith('.tpl')
    || (entry.html !== undefined && typeof entry.html !== 'string')
    || typeof entry.moduleUrl !== 'string'
    || typeof entry.moduleFile !== 'string') {
    throw new Error(t('engine.runtime.pagetemplates.002'))
  }
  const revision = Math.max(1, Math.trunc(Number(entry.revision) || 1))
  const moduleMatch = entry.moduleUrl.match(moduleUrlPattern)
  if (!moduleMatch || moduleMatch[1] !== id || Number(moduleMatch[2]) !== revision || Number(moduleMatch[3]) !== revision
    || entry.moduleFile !== `${id}.${revision}.js`) {
    throw new Error(t('engine.runtime.pagetemplates.002'))
  }
  return {
    id,
    file: entry.file,
    revision,
    updatedAt: typeof entry.updatedAt === 'string' ? entry.updatedAt : '',
    moduleUrl: entry.moduleUrl,
    moduleFile: entry.moduleFile,
    ...(typeof entry.html === 'string' ? { html: entry.html } : {}),
    ...(typeof entry.source === 'string' ? { source: entry.source } : {}),
  }
}

function normalizeDocument(value: unknown): RuntimePageTemplatesDocument {
  const source = object(value)
  if (source.schema !== 1 || !Array.isArray(source.templates)) {
    throw new Error(t('engine.runtime.pagetemplates.001'))
  }
  const templates = source.templates.map(normalizeTemplate)
  if (templates.length !== templateIds.size || new Set(templates.map((entry) => entry.id)).size !== templateIds.size) {
    throw new Error(t('engine.runtime.pagetemplates.001'))
  }
  return {
    schema: 1,
    revision: Math.max(1, Math.trunc(Number(source.revision) || 1)),
    updatedAt: typeof source.updatedAt === 'string' ? source.updatedAt : '',
    templates,
  }
}

export const runtimePageTemplatesState = readonly(state)
export const runtimePageTemplates = computed(() => state.document?.templates ?? [])

export function runtimePageTemplate(id: RuntimePageTemplateId): ComputedRef<RuntimePageTemplate | null> {
  return computed(() => state.document?.templates.find((entry) => entry.id === id) ?? null)
}

export function installRuntimePageTemplates(value: unknown): RuntimePageTemplatesDocument {
  const document = normalizeDocument(value)
  state.document = document
  state.loaded = true
  state.error = ''
  return document
}

export function loadRuntimePageTemplates(force = false): Promise<RuntimePageTemplatesDocument> {
  if (!force && state.document) return Promise.resolve(state.document)
  if (!force && loadingPromise) return loadingPromise
  state.loading = true
  state.error = ''
  loadingPromise = loadContentRegistry<unknown>('page-templates')
    .then(installRuntimePageTemplates)
    .catch((error: unknown) => {
      state.error = error instanceof Error ? error.message : t('engine.runtime.pagetemplates.003')
      throw error
    })
    .finally(() => {
      state.loading = false
      loadingPromise = null
    })
  return loadingPromise
}
