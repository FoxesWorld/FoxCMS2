<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthPage from '@theme/userOptions/content/guest/Auth.vue'
import { foxesApi } from '@/api'
import { queuePayloadToast, toastFeedback } from '@/notifications/toasts'

interface AuthResponse { type: 'success' | 'error' | string; message: string }
const router = useRouter()
const form = reactive({ login: '', password: '', rememberMe: true })
const submitting = ref(false)
const feedback = ref<AuthResponse | null>(null)

async function submit(): Promise<void> {
  feedback.value = null
  if (!form.login.trim() || !form.password) {
    feedback.value = toastFeedback({ type: 'error', message: 'Введите логин и пароль.' })
    return
  }

  submitting.value = true
  try {
    const response = await foxesApi.post<AuthResponse>({
      userAction: 'auth',
      login: form.login.trim(),
      password: form.password,
      rememberMe: form.rememberMe ? 1 : 0,
    })
    feedback.value = response
    if (response.type === 'success') { queuePayloadToast(response); window.setTimeout(() => window.location.reload(), 700) }
  } catch (error) {
    console.error('[FoxesCraft] Authorization failed', error)
    feedback.value = { type: 'error', message: 'Сервер авторизации временно недоступен.' }
  } finally { submitting.value = false }
}
</script>
<template><AuthPage :form="form" :submitting="submitting" :feedback="feedback" @submit="submit" @navigate="router.push({ name: $event })" /></template>
