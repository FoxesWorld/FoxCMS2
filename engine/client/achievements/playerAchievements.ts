import { t } from '@/i18n'

export interface PlayerAchievement {
  serverId: string
  playerName: string
  achievementKey: string
  achievementType: string
  parentKey: string | null
  title: string
  description: string
  frameType: string
  category: string
  iconDataUrl: string
  iconItem: string
  points: number
  progress: number
  target: number
  completed: boolean
  completedAt: number | null
  updatedAt: number
}

export interface PlayerAchievementSummary {
  trackedCount: number
  completedCount: number
  points: number
}

export interface PlayerAchievementsResponse {
  playerUuid: string
  items: PlayerAchievement[]
  summary: PlayerAchievementSummary
}

export type PlayerAchievementIdentity = {
  uuid?: string
  login?: string
}

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

function integer(value: unknown): number {
  const numeric = Number(value)
  return Number.isFinite(numeric) ? Math.max(0, Math.trunc(numeric)) : 0
}

function text(value: unknown): string {
  return typeof value === 'string' ? value.trim() : ''
}

function normalizeItem(value: unknown): PlayerAchievement | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null
  const source = value as Record<string, unknown>
  const achievementKey = text(source.achievementKey)
  const title = text(source.title)
  if (!achievementKey || !title) return null
  const target = Math.max(1, integer(source.target))
  return {
    serverId: text(source.serverId),
    playerName: text(source.playerName),
    achievementKey,
    achievementType: text(source.achievementType) || 'achievement',
    parentKey: text(source.parentKey) || null,
    title,
    description: text(source.description),
    frameType: text(source.frameType) || 'task',
    category: text(source.category) || 'general',
    iconDataUrl: text(source.iconDataUrl),
    iconItem: text(source.iconItem),
    points: integer(source.points),
    progress: Math.min(integer(source.progress), target),
    target,
    completed: source.completed === true,
    completedAt: source.completedAt === null || source.completedAt === undefined
      ? null
      : integer(source.completedAt),
    updatedAt: integer(source.updatedAt),
  }
}

function message(payload: unknown, fallback: string): string {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return fallback
  const value = text((payload as Record<string, unknown>).message)
  return value || fallback
}

function normalizeIdentity(identity: PlayerAchievementIdentity): Required<PlayerAchievementIdentity> {
  const uuid = text(identity.uuid)
  const login = text(identity.login)
  if (!uuid && !login) throw new Error(t('engine.achievements.player.001'))
  return { uuid, login }
}

export function achievementIdentity(value: string): PlayerAchievementIdentity {
  const normalized = value.trim()
  return UUID_PATTERN.test(normalized) ? { uuid: normalized } : { login: normalized }
}

export async function loadPlayerAchievementsByIdentity(
  identity: PlayerAchievementIdentity,
  signal?: AbortSignal,
): Promise<PlayerAchievementsResponse> {
  const normalized = normalizeIdentity(identity)
  const url = new URL('/api/game/achievements/player/', window.location.origin)
  if (normalized.uuid) url.searchParams.set('uuid', normalized.uuid)
  else url.searchParams.set('login', normalized.login)

  const response = await fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    signal,
  })
  const body = await response.text()
  let payload: unknown = null
  try {
    payload = body ? JSON.parse(body) : null
  } catch {
    throw new Error(t('engine.achievements.player.002'))
  }
  if (!response.ok) throw new Error(message(payload, `HTTP ${response.status}`))
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw new Error(t('engine.achievements.player.002'))
  }
  const source = payload as Record<string, unknown>
  const items = Array.isArray(source.items)
    ? source.items.map(normalizeItem).filter((item): item is PlayerAchievement => item !== null)
    : []
  const rawSummary = source.summary && typeof source.summary === 'object' && !Array.isArray(source.summary)
    ? source.summary as Record<string, unknown>
    : {}
  return {
    playerUuid: text(source.playerUuid) || normalized.uuid,
    items,
    summary: {
      trackedCount: integer(rawSummary.trackedCount),
      completedCount: integer(rawSummary.completedCount),
      points: integer(rawSummary.points),
    },
  }
}

export async function loadPlayerAchievements(
  playerUuid: string,
  signal?: AbortSignal,
): Promise<PlayerAchievementsResponse> {
  return loadPlayerAchievementsByIdentity({ uuid: playerUuid }, signal)
}


export interface AchievementStatisticPlayer {
  uuid: string
  login: string
  playerName: string
  completedAt: number | null
}

export interface AchievementStatistic {
  serverId: string
  achievementKey: string
  parentKey: string | null
  title: string
  description: string
  frameType: 'task' | 'goal' | 'challenge'
  category: string
  iconDataUrl: string
  iconItem: string
  points: number
  earnedCount: number
  playersTruncated: boolean
  players: AchievementStatisticPlayer[]
}

export interface AchievementStatisticsSummary {
  achievementCount: number
  earnedAchievementCount: number
  playerCount: number
  unlockCount: number
}

export interface AchievementStatisticsResponse {
  summary: AchievementStatisticsSummary
  items: AchievementStatistic[]
}

export async function loadAchievementStatistics(
  signal?: AbortSignal,
  serverId = '',
): Promise<AchievementStatisticsResponse> {
  const url = new URL('/api/game/achievements/statistics/', window.location.origin)
  const normalizedServerId = serverId.trim()
  if (normalizedServerId) url.searchParams.set('serverId', normalizedServerId)

  const response = await fetch(url, {
    method: 'GET',
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
    signal,
  })
  const payload = await response.json().catch(() => null) as Partial<AchievementStatisticsResponse> & {
    error?: string
    message?: string
  } | null
  if (!response.ok) {
    throw new Error(payload?.message || payload?.error || `HTTP ${response.status}`)
  }
  if (!payload || !Array.isArray(payload.items) || !payload.summary) {
    throw new Error(t('engine.achievements.player.003'))
  }

  return {
    summary: {
      achievementCount: Math.max(0, Number(payload.summary.achievementCount) || 0),
      earnedAchievementCount: Math.max(0, Number(payload.summary.earnedAchievementCount) || 0),
      playerCount: Math.max(0, Number(payload.summary.playerCount) || 0),
      unlockCount: Math.max(0, Number(payload.summary.unlockCount) || 0),
    },
    items: payload.items.map<AchievementStatistic>((item) => ({
      serverId: String(item.serverId ?? ''),
      achievementKey: String(item.achievementKey ?? ''),
      parentKey: item.parentKey === null || item.parentKey === undefined ? null : String(item.parentKey),
      title: String(item.title ?? ''),
      description: String(item.description ?? ''),
      frameType: item.frameType === 'challenge'
        ? 'challenge'
        : item.frameType === 'goal'
          ? 'goal'
          : 'task',
      category: String(item.category ?? ''),
      iconDataUrl: String(item.iconDataUrl ?? ''),
      iconItem: String(item.iconItem ?? ''),
      points: Math.max(0, Number(item.points) || 0),
      earnedCount: Math.max(0, Number(item.earnedCount) || 0),
      playersTruncated: item.playersTruncated === true,
      players: Array.isArray(item.players)
        ? item.players.map((player) => ({
          uuid: String(player.uuid ?? ''),
          login: String(player.login ?? ''),
          playerName: String(player.playerName ?? ''),
          completedAt: player.completedAt === null || player.completedAt === undefined
            ? null
            : Math.max(0, Number(player.completedAt) || 0),
        }))
        : [],
    })).filter((item) => item.serverId && item.achievementKey),
  }
}
