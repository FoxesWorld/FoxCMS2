export interface UserBadgeAssignment { badgeName: string }
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
  const add = (value: unknown): void => {
    const badgeName = String(value ?? '').trim()
    const key = normalizedBadgeKey(badgeName)
    if (badgeName && key && !seen.has(key)) { seen.add(key); result.push({ badgeName }) }
  }
  const walk = (entry: unknown): void => {
    if (typeof entry === 'string' || typeof entry === 'number') { add(entry); return }
    if (!entry || typeof entry !== 'object') return
    if (Array.isArray(entry)) { entry.forEach(walk); return }
    const record = entry as Record<string, unknown>
    const direct = record.badgeName ?? record.name ?? record.title ?? record.id
    if (direct !== undefined) add(direct)
    else Object.keys(record).forEach(add)
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
