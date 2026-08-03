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

export function getSeasonBackground(
  data: FoxesCraftBootstrap,
  season: SiteSeason,
  colorTheme: 'light' | 'dark' = 'light',
): string {
  const directory = typeof data.theme.settings.seasonDirectory === 'string'
    ? data.theme.settings.seasonDirectory.replace(/^assets\//, '')
    : 'img/season'
  const configuredFiles = colorTheme === 'dark'
    ? data.theme.settings.seasonNightFiles
    : data.theme.settings.seasonFiles
  const files = configuredFiles && typeof configuredFiles === 'object' && !Array.isArray(configuredFiles)
    ? configuredFiles as Record<string, unknown>
    : {}
  const fallback = colorTheme === 'dark' ? `${season}Night.png` : `${season}.png`
  const file = typeof files[season] === 'string' ? files[season] : fallback
  return themeAsset(data, `${directory}/${file}`)
}
