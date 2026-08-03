import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { bootstrapBoolean, bootstrapString, type NavigationDefinition } from '@/domain/bootstrap'
import { queuePayloadToast } from '@/notifications/toasts'
import { normalizeBalanceMatrix } from '@/domain/userBalance'

export function useEngineShell() {
  const router = useRouter()
  const isGuest = computed(() => !bootstrapBoolean(appBootstrap, 'isLogged', false))
  const displayName = computed(() => bootstrapString(appBootstrap, 'realname', bootstrapString(appBootstrap, 'login', 'Пользователь')))
  const profilePhoto = computed(() => bootstrapString(appBootstrap, 'profilePhoto'))
  const balance = computed(() => normalizeBalanceMatrix(appBootstrap.user.balance))

  function navigation(area: string): NavigationDefinition[] {
    return appBootstrap.frontend.navigation.filter((item) => item.area === area)
  }

  function routeParams(item: NavigationDefinition): Record<string, string> {
    const params: Record<string, string> = {}
    for (const [param, userField] of Object.entries(item.paramsFromUser ?? {})) {
      const value = bootstrapString(appBootstrap, userField)
      if (value) params[param] = value
    }
    return params
  }

  async function logout(): Promise<void> {
    try {
      const response = await foxesApi.post<{ type?: string; message?: string }>({ userAction: 'logout' })
      if (response.type === 'success') {
        queuePayloadToast(response)
        window.location.reload()
      }
    } catch (error) { console.error('[FoxesCraft] Logout failed', error) }
  }

  function activate(item: NavigationDefinition): void {
    if (item.action === 'logout') {
      void logout()
      return
    }
    if (item.route) void router.push({ name: item.route, params: routeParams(item) })
  }

  return {
    bootstrap: appBootstrap,
    isGuest,
    displayName,
    profilePhoto,
    balance,
    siteTitle: appBootstrap.site.title || 'FoxesCraft',
    serviceVersion: appBootstrap.engine.version,
    primaryItems: computed(() => navigation('header').filter((item) => item.intent !== 'admin')),
    footerItems: computed(() => navigation('footer')),
    guestItems: computed(() => navigation('guest')),
    accountItems: computed(() => appBootstrap.frontend.navigation
      .filter((item) => item.area === 'account' || item.intent === 'admin')
      .sort((left, right) => {
        const leftIsLogout = left.action === 'logout' || left.intent === 'logout'
        const rightIsLogout = right.action === 'logout' || right.intent === 'logout'
        return Number(leftIsLogout) - Number(rightIsLogout) || left.order - right.order
      })),
    activate,
  }
}
