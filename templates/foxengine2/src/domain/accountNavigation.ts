import type { NavigationDefinition } from '@engine/domain/bootstrap'

export function accountNavigationIcon(item: NavigationDefinition): string {
  if (item.action === 'logout' || item.intent === 'logout') return 'fa-solid fa-right-from-bracket fa-fw'
  if (item.intent === 'admin' || item.route === 'admin') return 'fa-solid fa-shield-halved fa-fw'
  if (item.intent === 'profile' || item.route === 'profile') return 'fa-solid fa-user fa-fw'
  if (item.intent === 'login') return 'fa-solid fa-right-to-bracket fa-fw'
  if (item.intent === 'register') return 'fa-solid fa-user-plus fa-fw'
  return 'fa-solid fa-circle fa-fw'
}
