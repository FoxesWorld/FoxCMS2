<script setup lang="ts">
import { t } from '@/i18n'

import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthPage from '@theme/userOptions/content/guest/Auth.vue'
import { foxesApi, foxesApiFailureFeedback } from '@/api'
import { queuePayloadToast, toastFeedback } from '@/notifications/toasts'
import { focusFormField } from '@/forms/focusFormField'
import { hcaptchaRequired } from '@/security/hcaptcha'

interface AuthResponse {
  type: 'success' | 'error' | 'warning' | 'warn' | string
  message: string
  code?: string
  field?: string
  requestId?: string
  correlationId?: string
}

const router = useRouter()
const form = reactive({ login: '', password: '', rememberMe: true })
const submitting = ref(false)
const feedback = ref<AuthResponse | null>(null)

async function submit(hcaptchaToken = ''): Promise<void> {
  feedback.value = null
  if (hcaptchaRequired('login') && !hcaptchaToken) {
    feedback.value = toastFeedback({ type: 'error', message: t('engine.security.hcaptcha.003') })
    return
  }
  const login = form.login.trim()
  if (!login) {
    feedback.value = toastFeedback({ type: 'error', message: t('modules.authreg.authview.001') })
    focusFormField('login')
    return
  }
  if (!form.password) {
    feedback.value = toastFeedback({ type: 'error', message: t('modules.authreg.authview.002') })
    focusFormField('password')
    return
  }
  if (!/^[A-Za-z0-9_.-]+$/.test(login)) {
    feedback.value = toastFeedback({
      type: 'error',
      message: t('modules.authreg.authview.003'),
    })
    focusFormField('login')
    return
  }

  submitting.value = true
  try {
    const response = await foxesApi.post<AuthResponse>({
      userAction: 'auth',
      login,
      password: form.password,
      rememberMe: form.rememberMe ? 1 : 0,
      hcaptchaToken,
    })
    feedback.value = response
    if (response.type === 'success') {
      queuePayloadToast(response)
      window.setTimeout(() => window.location.reload(), 700)
    }
  } catch (error) {
    console.error('[FoxesCraft] Authorization failed', error)
    const failure = foxesApiFailureFeedback(
      error,
      t('modules.authreg.authview.004'),
    )
    feedback.value = failure
    focusFormField(failure.field)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthPage
    :form="form"
    :submitting="submitting"
    :feedback="feedback"
    @submit="submit"
    @navigate="router.push({ name: $event })"
  />
</template>
