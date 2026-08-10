<script setup lang="ts">
import { t } from '@/i18n'

import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import RegisterPage from '@theme/userOptions/content/guest/Reg.vue'
import { foxesApi, foxesApiFailureFeedback } from '@/api'
import { queuePayloadToast, showToast, toastFeedback } from '@/notifications/toasts'
import { focusFormField } from '@/forms/focusFormField'
import { hcaptchaRequired } from '@/security/hcaptcha'

interface RegisterResponse {
  type: 'success' | 'error' | 'warning' | 'warn' | string
  message: string
  code?: string
  field?: string
  requestId?: string
  correlationId?: string
  authenticated?: boolean
  userUuid?: string
}

const router = useRouter()
const form = reactive({ login: '', email: '', password1: '', password2: '', dataProcessing: false, acceptRules: false })
const submitting = ref(false)
const feedback = ref<RegisterResponse | null>(null)

const strength = computed(() => {
  const password = form.password1
  let score = 0
  if (password.length >= 8) score++
  if (password.length >= 14) score++
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++
  if (/\d/.test(password)) score++
  if (/[^A-Za-z0-9]/.test(password)) score++
  const labels = [t('modules.authreg.registerview.001'), t('modules.authreg.registerview.002'), t('modules.authreg.registerview.003'), t('modules.authreg.registerview.004'), t('modules.authreg.registerview.005'), t('modules.authreg.registerview.006')]
  return { score, label: labels[score], width: `${score * 20}%` }
})

function generatePassword(): void {
  const alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789_-!@#'
  const values = new Uint32Array(20)
  crypto.getRandomValues(values)
  const password = Array.from(values, (value) => alphabet[value % alphabet.length]).join('')
  form.password1 = password
  form.password2 = password
  showToast(t('modules.authreg.registerview.007'), 'info', 3500)
}

interface LocalValidationFailure { message: string; field: string }

function localValidationFailure(): LocalValidationFailure | null {
  const login = form.login.trim()
  const email = form.email.trim()
  if (!login) return { message: t('modules.authreg.registerview.008'), field: 'login' }
  if (login.length < 3) return { message: t('modules.authreg.registerview.009'), field: 'login' }
  if (!/^[A-Za-z0-9_.-]+$/.test(login)) {
    return {
      message: t('modules.authreg.registerview.010'),
      field: 'login',
    }
  }
  if (!email) return { message: t('modules.authreg.registerview.011'), field: 'email' }
  if (/\s/.test(email)) {
    return {
      message: t('modules.authreg.registerview.012'),
      field: 'email',
    }
  }
  if (!form.password1) return { message: t('modules.authreg.registerview.013'), field: 'password1' }
  if (form.password1.length > 72) {
    return { message: t('modules.authreg.registerview.014'), field: 'password1' }
  }
  if (form.password1 !== form.password2) return { message: t('modules.authreg.registerview.015'), field: 'password2' }
  if (/[^\x21-\x7E]/.test(form.password1)) {
    return {
      message: t('modules.authreg.registerview.016'),
      field: 'password1',
    }
  }
  return null
}

async function submit(hcaptchaToken = ''): Promise<void> {
  feedback.value = null
  if (hcaptchaRequired('registration') && !hcaptchaToken) {
    feedback.value = toastFeedback({ type: 'error', message: t('engine.security.hcaptcha.003') })
    return
  }
  if (!form.acceptRules || !form.dataProcessing) {
    feedback.value = toastFeedback({ type: 'error', message: t('modules.authreg.registerview.017') })
    return
  }

  const validationFailure = localValidationFailure()
  if (validationFailure) {
    feedback.value = toastFeedback({ type: 'error', message: validationFailure.message })
    focusFormField(validationFailure.field)
    return
  }

  submitting.value = true
  try {
    const response = await foxesApi.post<RegisterResponse>({
      userAction: 'register',
      login: form.login.trim(),
      email: form.email.trim(),
      password1: form.password1,
      password2: form.password2,
      hcaptchaToken,
    })
    feedback.value = response
    if (response.type === 'success') {
      queuePayloadToast(response)
      window.setTimeout(() => window.location.reload(), 900)
    }
  } catch (error) {
    console.error('[FoxesCraft] Registration failed', error)
    const failure = foxesApiFailureFeedback(
      error,
      t('modules.authreg.registerview.018'),
    )
    feedback.value = failure
    focusFormField(failure.field)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <RegisterPage
    :form="form"
    :strength="strength"
    :submitting="submitting"
    :feedback="feedback"
    @submit="submit"
    @generate="generatePassword"
    @navigate="router.push({ name: $event })"
  />
</template>
