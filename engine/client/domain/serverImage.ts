import { appBootstrap } from '@/app/context'
import { themeAsset } from '@/domain/bootstrap'

export function serverImageUrl(value: unknown): string {
  const normalized = String(value ?? '').trim().replaceAll('\\', '/')
  if (!normalized) return ''
  if (/^(?:https?:|data:|blob:)/i.test(normalized) || normalized.startsWith('//')) return normalized
  if (normalized.startsWith('/')) return normalized
  if (normalized.startsWith('uploads/') || normalized.startsWith('templates/')) return `/${normalized}`

  const themeRelative = normalized.replace(/^assets\//, '')
  return themeAsset(
    appBootstrap,
    themeRelative.includes('/') ? themeRelative : `img/servers/${themeRelative}`,
  )
}
