import { t } from '@/i18n'
import { bootstrapString, type FoxesCraftBootstrap } from '@/domain/bootstrap'

export type SocialNetwork = 'telegram' | 'github' | 'youtube' | 'discord'

export interface SocialLinkDefinition {
  id: SocialNetwork
  label: string
  href: string
  icon: string
}

const SOCIAL_NETWORKS: ReadonlyArray<{ id: SocialNetwork; icon: string; bootstrapKey: string }> = [
  { id: 'telegram', icon: 'fa-brands fa-telegram', bootstrapKey: 'telegramLink' },
  { id: 'github', icon: 'fa-brands fa-github', bootstrapKey: 'githubLink' },
  { id: 'youtube', icon: 'fa-brands fa-youtube', bootstrapKey: 'youtubeLink' },
  { id: 'discord', icon: 'fa-brands fa-discord', bootstrapKey: 'discordLink' },
]

function socialLabel(network: SocialNetwork): string {
  switch (network) {
    case 'telegram': return t('theme.footer.005')
    case 'github': return t('theme.footer.006')
    case 'youtube': return t('theme.footer.007')
    case 'discord': return t('theme.footer.008')
  }
}

function normalizeExternalUrl(value: string): string {
  if (!value.trim()) return ''

  try {
    const url = new URL(value)
    return url.protocol === 'https:' || url.protocol === 'http:' ? url.href : ''
  } catch {
    return ''
  }
}

export function resolveSocialLinks(bootstrap: FoxesCraftBootstrap): SocialLinkDefinition[] {
  return SOCIAL_NETWORKS.flatMap(({ bootstrapKey, ...network }) => {
    const href = normalizeExternalUrl(bootstrapString(bootstrap, bootstrapKey))
    return href ? [{ ...network, label: socialLabel(network.id), href }] : []
  })
}
