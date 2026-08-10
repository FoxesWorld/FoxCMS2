import { appBootstrap } from '@/app/context'

export type HCaptchaForm = 'login' | 'registration' | 'passwordRecovery' | 'passwordReset'

export function hcaptchaRequired(form: HCaptchaForm): boolean {
  const config = appBootstrap.site.hcaptcha
  return config.enabled && config.siteKey.trim() !== '' && config.forms[form]
}

export function hcaptchaSiteKey(): string {
  return appBootstrap.site.hcaptcha.siteKey.trim()
}
