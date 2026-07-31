export type BootstrapValue = string | number | boolean | null | BootstrapValue[] | { [key: string]: BootstrapValue }

export interface FrontendRouteDefinition {
  path: string
  name: string
  view?: string
  redirect?: string
  title?: string
  props?: boolean | Record<string, BootstrapValue>
  owner?: string
}

export interface NavigationDefinition {
  area: string
  label: string
  order: number
  route?: string
  action?: string
  intent?: string
  owner?: string
  paramsFromUser?: Record<string, string>
}

export interface LegacyRouteDefinition {
  kind: string
  value?: string
  route: string
  param?: string
  query?: Record<string, string>
}

export interface FoxesCraftBootstrap {
  engine: {
    version: string
    csrfToken: string
    endpoints: Record<string, string>
  }
  theme: {
    name: string
    assets: string
    mount: string
    settings: Record<string, BootstrapValue>
  }
  site: {
    title: string
    status: string
    description: string
  }
  user: Record<string, BootstrapValue>
  frontend: {
    routes: FrontendRouteDefinition[]
    navigation: NavigationDefinition[]
    legacy: LegacyRouteDefinition[]
    capabilities: string[]
    endpoints: Record<string, string>
  }
  replaceData: Record<string, BootstrapValue>
  userFields: string[]
}

function emptyBootstrap(): FoxesCraftBootstrap {
  const themeName = document.documentElement.dataset.theme ?? ''
  const assets = themeName ? `/templates/${encodeURIComponent(themeName)}/assets/` : ''
  return {
    engine: { version: '', csrfToken: '', endpoints: {} },
    theme: { name: themeName, assets, mount: 'foxescraft-app', settings: {} },
    site: { title: 'FoxesCraft', status: '', description: '' },
    user: { isLogged: false, groupTag: 'guest', login: 'anonymous' },
    frontend: { routes: [], navigation: [], legacy: [], capabilities: [], endpoints: {} },
    replaceData: { groupTag: 'guest', login: 'anonymous', siteTitle: 'FoxesCraft', assets },
    userFields: [],
  }
}

function record(value: unknown): Record<string, BootstrapValue> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, BootstrapValue>
    : {}
}

export function readBootstrap(): FoxesCraftBootstrap {
  const fallback = emptyBootstrap()
  const node = document.querySelector<HTMLScriptElement>('#foxescraft-bootstrap')
  if (!node?.textContent?.trim()) return fallback

  try {
    const value = JSON.parse(node.textContent) as Partial<FoxesCraftBootstrap>
    const engine = value.engine && typeof value.engine === 'object' ? value.engine : fallback.engine
    const theme = value.theme && typeof value.theme === 'object' ? value.theme : fallback.theme
    const site = value.site && typeof value.site === 'object' ? value.site : fallback.site
    const frontend = value.frontend && typeof value.frontend === 'object' ? value.frontend : fallback.frontend
    return {
      engine: {
        version: typeof engine.version === 'string' ? engine.version : '',
        csrfToken: typeof engine.csrfToken === 'string' ? engine.csrfToken : '',
        endpoints: record(engine.endpoints) as Record<string, string>,
      },
      theme: {
        name: typeof theme.name === 'string' ? theme.name : fallback.theme.name,
        assets: typeof theme.assets === 'string' ? theme.assets : fallback.theme.assets,
        mount: typeof theme.mount === 'string' ? theme.mount : fallback.theme.mount,
        settings: record(theme.settings),
      },
      site: {
        title: typeof site.title === 'string' ? site.title : fallback.site.title,
        status: typeof site.status === 'string' ? site.status : '',
        description: typeof site.description === 'string' ? site.description : '',
      },
      user: record(value.user),
      frontend: {
        routes: Array.isArray(frontend.routes) ? frontend.routes : [],
        navigation: Array.isArray(frontend.navigation) ? frontend.navigation : [],
        legacy: Array.isArray(frontend.legacy) ? frontend.legacy : [],
        capabilities: Array.isArray(frontend.capabilities) ? frontend.capabilities.filter((item): item is string => typeof item === 'string') : [],
        endpoints: record(frontend.endpoints) as Record<string, string>,
      },
      replaceData: record(value.replaceData),
      userFields: Array.isArray(value.userFields) ? value.userFields.filter((item): item is string => typeof item === 'string') : [],
    }
  } catch (error) {
    console.error('[FoxesCraft] Invalid engine bootstrap payload', error)
    return fallback
  }
}

export function bootstrapValue(data: FoxesCraftBootstrap, key: string): BootstrapValue | undefined {
  return data.user[key] ?? data.replaceData[key]
}

export function bootstrapString(data: FoxesCraftBootstrap, key: string, fallback = ''): string {
  const value = bootstrapValue(data, key)
  return typeof value === 'string' || typeof value === 'number' ? String(value) : fallback
}

export function bootstrapNumber(data: FoxesCraftBootstrap, key: string, fallback = 0): number {
  const value = Number(bootstrapValue(data, key))
  return Number.isFinite(value) ? value : fallback
}

export function bootstrapBoolean(data: FoxesCraftBootstrap, key: string, fallback = false): boolean {
  const value = bootstrapValue(data, key)
  return typeof value === 'boolean' ? value : fallback
}

export function bootstrapEndpoint(data: FoxesCraftBootstrap, name: string, fallback = ''): string {
  const value = data.engine.endpoints[name] ?? data.frontend.endpoints[name]
  return typeof value === 'string' && value.startsWith('/') ? value : fallback
}

export function themeAsset(data: FoxesCraftBootstrap, relativePath: string): string {
  const relative = relativePath.replace(/^\/+/, '')
  return data.theme.assets ? `${data.theme.assets}${relative}` : relative
}
