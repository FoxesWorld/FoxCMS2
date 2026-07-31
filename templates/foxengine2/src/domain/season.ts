import type { FoxesCraftBootstrap } from '@/domain/bootstrap'
import { themeAsset } from '@/domain/bootstrap'

export type SiteSeason = 'winter' | 'spring' | 'summer' | 'autumn'

export function getSiteSeason(date: Date = new Date()): SiteSeason {
  const month = date.getMonth()
  if (month === 11 || month <= 1) return 'winter'
  if (month <= 4) return 'spring'
  if (month <= 7) return 'summer'
  return 'autumn'
}

export function getSeasonBackground(data: FoxesCraftBootstrap, season: SiteSeason): string {
  const directory = typeof data.theme.settings.seasonDirectory === 'string'
    ? data.theme.settings.seasonDirectory.replace(/^assets\//, '')
    : 'img/season'
  const configuredFiles = data.theme.settings.seasonFiles
  const files = configuredFiles && typeof configuredFiles === 'object' && !Array.isArray(configuredFiles)
    ? configuredFiles as Record<string, unknown>
    : {}
  const fallback: Record<SiteSeason, string> = {
    winter: 'winter.jpg', spring: 'spring.png', summer: 'summer.png', autumn: 'autumn.png',
  }
  const file = typeof files[season] === 'string' ? files[season] : fallback[season]
  return themeAsset(data, `${directory}/${file}`)
}
