import { appBootstrap } from '@/app/context'
import { t } from '@/i18n'
import { foxesApi } from '@/api'
import type { BootstrapValue } from '@/domain/bootstrap'

export interface AchievementEconomyState {
  enabled: boolean
  pointsPerUnit: number
  minimumPoints: number
  earnedPoints: number
  exchangedPoints: number
  availablePoints: number
  maxExchangeablePoints: number
  exchangeableUnits: number
  lifetimeUnits: number
  exchangeCount: number
  unitBalance: number
  currencyCode: 'units'
  currencyName: string
  currencySymbol: string
}

export interface AchievementPointExchange {
  id: number
  requestUuid: string
  pointsSpent: number
  unitsGranted: number
  pointsPerUnit: number
  createdAt: number
}

export interface AchievementEconomyResponse {
  economy: AchievementEconomyState
}

export interface AchievementExchangeResponse extends AchievementEconomyResponse {
  type: 'success' | 'warning'
  message: string
  duplicate: boolean
  exchange: AchievementPointExchange
  balance?: BootstrapValue
}

function integer(value: unknown): number {
  const numeric = Number(value)
  return Number.isFinite(numeric) ? Math.max(0, Math.trunc(numeric)) : 0
}

function normalizeState(value: unknown): AchievementEconomyState {
  const source = value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {}
  const rate = Math.max(1, integer(source.pointsPerUnit) || 10)
  return {
    enabled: source.enabled === true,
    pointsPerUnit: rate,
    minimumPoints: Math.max(rate, integer(source.minimumPoints) || rate),
    earnedPoints: integer(source.earnedPoints),
    exchangedPoints: integer(source.exchangedPoints),
    availablePoints: integer(source.availablePoints),
    maxExchangeablePoints: integer(source.maxExchangeablePoints),
    exchangeableUnits: integer(source.exchangeableUnits),
    lifetimeUnits: integer(source.lifetimeUnits),
    exchangeCount: integer(source.exchangeCount),
    unitBalance: integer(source.unitBalance),
    currencyCode: 'units',
    currencyName: typeof source.currencyName === 'string' && source.currencyName.trim() ? source.currencyName.trim() : 'Units',
    currencySymbol: typeof source.currencySymbol === 'string' && source.currencySymbol.trim() ? source.currencySymbol.trim() : 'U',
  }
}

function operationUuid(): string {
  const webCrypto = globalThis.crypto
  if (!webCrypto) throw new Error(t('engine.achievements.economy.001'))
  if (typeof webCrypto.randomUUID === 'function') return webCrypto.randomUUID()
  const bytes = new Uint8Array(16)
  webCrypto.getRandomValues(bytes)
  bytes[6] = (bytes[6] & 0x0f) | 0x40
  bytes[8] = (bytes[8] & 0x3f) | 0x80
  const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

export async function loadAchievementEconomy(): Promise<AchievementEconomyState> {
  const response = await foxesApi.post<AchievementEconomyResponse>({
    user_doaction: 'getAchievementEconomy',
  })
  return normalizeState(response.economy)
}

export async function exchangeAchievementPoints(points: number): Promise<AchievementExchangeResponse> {
  const response = await foxesApi.post<AchievementExchangeResponse>({
    user_doaction: 'exchangeAchievementPoints',
    points: Math.trunc(points),
    requestUuid: operationUuid(),
  })
  if (response.balance !== undefined) appBootstrap.user.balance = response.balance
  return {
    ...response,
    economy: normalizeState(response.economy),
  }
}
