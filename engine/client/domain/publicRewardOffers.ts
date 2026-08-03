import type { BalanceCurrency } from '@/domain/userBalance'

export interface PublicRewardBadge {
  id: number
  badgeName: string
  title: string
  description: string
  image: string | null
  acquiredAt: number
}

export interface PublicRewardCurrency {
  currencyCode: BalanceCurrency['code']
  currencyName: string
  currencySymbol: string
  amount: number
}

export interface PublicRewardDefinition {
  id: number
  rewardName: string
  title: string
  description: string
  badge: PublicRewardBadge | null
  currency: PublicRewardCurrency | null
}

export interface PublicRewardOffer {
  placement: string
  reward: PublicRewardDefinition
  acquired: boolean
  claimable: boolean
}

function record(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : null
}

function safeInteger(value: unknown): number {
  const number = Number(value)
  return Number.isSafeInteger(number) && number >= 0 ? number : 0
}

function normalizeBadge(value: unknown): PublicRewardBadge | null {
  const badge = record(value)
  if (!badge) return null
  const badgeName = String(badge.badgeName ?? badge.title ?? '').trim()
  if (!badgeName) return null
  return {
    id: safeInteger(badge.id),
    badgeName,
    title: String(badge.title ?? badgeName).trim() || badgeName,
    description: String(badge.description ?? '').trim(),
    image: typeof badge.image === 'string' && badge.image.trim() ? badge.image.trim() : null,
    acquiredAt: safeInteger(badge.acquiredAt),
  }
}

function normalizeCurrency(value: unknown): PublicRewardCurrency | null {
  const currency = record(value)
  if (!currency) return null
  const currencyCode = String(currency.currencyCode ?? '').trim().toLowerCase()
  const amount = safeInteger(currency.amount)
  if ((currencyCode !== 'units' && currencyCode !== 'crystals') || amount <= 0) return null
  return {
    currencyCode,
    currencyName: String(currency.currencyName ?? currencyCode).trim() || currencyCode,
    currencySymbol: String(currency.currencySymbol ?? '').trim(),
    amount,
  }
}

export function normalizePublicRewardOffer(value: unknown): PublicRewardOffer | null {
  const source = record(value)
  const reward = record(source?.reward)
  if (!source || !reward) return null
  const placement = String(source.placement ?? '').trim()
  const rewardName = String(reward.rewardName ?? reward.title ?? '').trim()
  if (!placement || !rewardName) return null
  const badge = normalizeBadge(reward.badge)
  const currency = normalizeCurrency(reward.currency)
  if (!badge && !currency) return null
  return {
    placement,
    reward: {
      id: safeInteger(reward.id),
      rewardName,
      title: String(reward.title ?? rewardName).trim() || rewardName,
      description: String(reward.description ?? '').trim(),
      badge,
      currency,
    },
    acquired: source.acquired === true,
    claimable: source.claimable === true,
  }
}
