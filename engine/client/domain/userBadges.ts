export interface UserBadgeAssignment {
  badgeName: string
  acquiredAt?: number
  source?: string
}
export function normalizedBadgeKey(value: unknown): string {
  return String(value ?? '').normalize('NFKC').trim().toLocaleLowerCase('ru')
}
export function userBadgeAssignments(value: unknown): UserBadgeAssignment[] {
  let parsed: unknown = value
  if (typeof value === 'string' && /^[\[{]/.test(value.trim())) {
    try { parsed = JSON.parse(value) } catch { parsed = value }
  }
  const result: UserBadgeAssignment[] = []
  const seen = new Set<string>()
  const add = (value: unknown, metadata: Partial<UserBadgeAssignment> = {}): void => {
    const badgeName = String(value ?? '').trim()
    const key = normalizedBadgeKey(badgeName)
    if (!badgeName || !key || seen.has(key)) return
    seen.add(key)
    const acquiredAt = Number(metadata.acquiredAt)
    const source = String(metadata.source ?? '').trim()
    result.push({
      badgeName,
      ...(Number.isFinite(acquiredAt) && acquiredAt > 0 ? { acquiredAt } : {}),
      ...(source ? { source } : {}),
    })
  }
  const walk = (entry: unknown): void => {
    if (typeof entry === 'string' || typeof entry === 'number') { add(entry); return }
    if (!entry || typeof entry !== 'object') return
    if (Array.isArray(entry)) { entry.forEach(walk); return }
    const record = entry as Record<string, unknown>
    const direct = record.badgeName ?? record.name ?? record.title ?? record.id
    if (direct !== undefined) add(direct, {
      acquiredAt: typeof record.acquiredAt === 'number' ? record.acquiredAt : Number(record.acquiredAt),
      source: typeof record.source === 'string' ? record.source : '',
    })
    else Object.entries(record).forEach(([badgeName, acquiredAt]) => add(badgeName, { acquiredAt: Number(acquiredAt) }))
  }
  ;(Array.isArray(parsed) ? parsed : parsed ? [parsed] : []).forEach(walk)
  return result
}
export function toggleUserBadgeAssignment(value: unknown, badgeName: string): UserBadgeAssignment[] {
  const badges = userBadgeAssignments(value)
  const key = normalizedBadgeKey(badgeName)
  return badges.some((badge) => normalizedBadgeKey(badge.badgeName) === key)
    ? badges.filter((badge) => normalizedBadgeKey(badge.badgeName) !== key)
    : [...badges, { badgeName: badgeName.trim() }]
}
