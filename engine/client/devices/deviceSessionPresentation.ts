import { t } from '@/i18n'
import type { ActiveUserSession } from '@/sessions/userSessions'

export function sessionTypeLabel(session: ActiveUserSession): string {
  return session.remembered
    ? t('engine.views.devices.008')
    : t('engine.views.devices.009')
}

export function sessionTechnicalLabel(session: ActiveUserSession): string {
  return [session.browser, session.operatingSystem]
    .filter((value) => value.trim())
    .join(' · ') || session.deviceLabel
}
