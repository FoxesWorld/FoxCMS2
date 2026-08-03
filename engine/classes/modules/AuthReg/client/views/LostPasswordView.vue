<script setup lang="ts">
import { t } from '@/i18n'

import { ref } from 'vue'
import { useRouter } from 'vue-router'
import LostPasswordPage from '@theme/userOptions/content/guest/LostPassword.vue'
import { foxesApi } from '@/api'
import { toastFeedback } from '@/notifications/toasts'

interface ApiResponse { type: 'success' | 'error' | string; message: string }
const router = useRouter()
const email = ref('')
const submitting = ref(false)
const feedback = ref<ApiResponse | null>(null)

async function submit(): Promise<void> {
  feedback.value = null
  const value = email.value.trim()
  if (!value || !/^\S+@\S+\.\S+$/.test(value)) {
    feedback.value = toastFeedback({ type: 'error', message: t('modules.authreg.lostpasswordview.001') })
    return
  }
  submitting.value = true
  try {
    feedback.value = await foxesApi.post<ApiResponse>({ user_doaction: 'lostpassword', email: value })
  } catch (error) {
    console.error('[FoxesCraft] Password reset failed', error)
    feedback.value = { type: 'error', message: t('modules.authreg.lostpasswordview.002') }
  } finally { submitting.value = false }
}
</script>
<template><LostPasswordPage v-model:email="email" :submitting="submitting" :feedback="feedback" @submit="submit" @navigate="router.push({ name: $event })" /></template>
