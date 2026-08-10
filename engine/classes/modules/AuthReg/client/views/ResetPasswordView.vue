<script setup lang="ts">
import { t } from '@/i18n'

import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PassResetPage from '@theme/userOptions/content/guest/PassReset.vue'
import { foxesApi } from '@/api'
import { toastFeedback } from '@/notifications/toasts'
import { hcaptchaRequired } from '@/security/hcaptcha'

interface ApiResponse { type: 'success' | 'error' | string; message: string }
const route = useRoute()
const router = useRouter()
const token = computed(() => typeof route.query.token === 'string' ? route.query.token : '')
const form = reactive({ password: '', confirmation: '' })
const submitting = ref(false)
const feedback = ref<ApiResponse | null>(null)

async function submit(hcaptchaToken = ''): Promise<void> {
  feedback.value = null
  if (hcaptchaRequired('passwordReset') && !hcaptchaToken) {
    feedback.value = toastFeedback({ type: 'error', message: t('engine.security.hcaptcha.003') })
    return
  }
  if (!token.value) { feedback.value = toastFeedback({ type: 'error', message: t('modules.authreg.resetpasswordview.001') }); return }
  if (form.password !== form.confirmation) { feedback.value = toastFeedback({ type: 'error', message: t('modules.authreg.resetpasswordview.002') }); return }
  if (form.password.length < 10 || /[А-Яа-яЁё]/u.test(form.password)) { feedback.value = toastFeedback({ type: 'error', message: t('modules.authreg.resetpasswordview.003') }); return }

  submitting.value = true
  try {
    feedback.value = await foxesApi.post<ApiResponse>({
      user_doaction: 'resetpassword',
      token: token.value,
      new_password: form.password,
      confirm_password: form.confirmation,
      hcaptchaToken,
    })
    if (feedback.value.type === 'success') window.setTimeout(() => void router.push({ name: 'auth' }), 1000)
  } catch (error) {
    console.error('[FoxesCraft] Password reset request failed', error)
    feedback.value = { type: 'error', message: t('modules.authreg.resetpasswordview.004') }
  } finally { submitting.value = false }
}
</script>
<template><PassResetPage :form="form" :token-available="Boolean(token)" :submitting="submitting" :feedback="feedback" @submit="submit" @navigate="router.push({ name: $event })" /></template>
