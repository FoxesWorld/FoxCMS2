import { computed, onMounted } from 'vue'
import { t } from '@/i18n'
import { showToast } from '@/notifications/toasts'
import {
  refreshUserSessions,
  revokeUserSession,
  userSessions,
  type ActiveUserSession,
} from '@/sessions/userSessions'

/** Route-level orchestration for the authenticated devices/session center. */
export function useDevicesCenter() {
  const rememberedSessionsCount = computed(() =>
    userSessions.items.filter((session) => session.remembered).length)
  const shortSessionsCount = computed(() =>
    userSessions.items.filter((session) => !session.remembered).length)
  const currentSession = computed(() =>
    userSessions.items.find((session) => session.current) ?? null)

  onMounted(() => void refreshUserSessions({ silent: userSessions.initialized }))

  async function deactivateSession(session: ActiveUserSession): Promise<void> {
    if (session.current) return
    if (!window.confirm(t('engine.views.devices.038', [session.deviceLabel]))) return

    try {
      const message = await revokeUserSession(session.sessionUuid)
      showToast(message, 'success')
    } catch (error) {
      showToast(
        error instanceof Error ? error.message : t('engine.views.devices.039'),
        'error',
      )
    }
  }

  return {
    currentSession,
    deactivateSession,
    refreshSessions: refreshUserSessions,
    rememberedSessionsCount,
    shortSessionsCount,
    userSessions,
  }
}
