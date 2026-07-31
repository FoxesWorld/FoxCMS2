<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import RegisterPage from '@theme/userOptions/content/guest/Reg.vue'
import { foxesApi } from '@/api'
import { queuePayloadToast, showToast, toastFeedback } from '@/notifications/toasts'

interface RegisterResponse { type: 'success' | 'error' | 'warn' | string; message: string }
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
  const labels = ['Нет пароля', 'Очень слабый', 'Слабый', 'Нормальный', 'Надёжный', 'Очень надёжный']
  return { score, label: labels[score], width: `${score * 20}%` }
})

function generatePassword(): void {
  const alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789_-!@#'
  const values = new Uint32Array(20)
  crypto.getRandomValues(values)
  const password = Array.from(values, (value) => alphabet[value % alphabet.length]).join('')
  form.password1 = password
  form.password2 = password
  showToast('Надёжный пароль создан и подставлен в оба поля.', 'info', 3500)
}

async function submit(): Promise<void> {
  feedback.value = null
  if (!form.acceptRules || !form.dataProcessing) {
    feedback.value = toastFeedback({ type: 'error', message: 'Нужно принять правила и условия обработки данных.' })
    return
  }
  if (form.password1 !== form.password2) {
    feedback.value = toastFeedback({ type: 'error', message: 'Пароли не совпадают.' })
    return
  }
  if (form.password1.length < 8 || /[А-Яа-яЁё]/.test(form.password1)) {
    feedback.value = toastFeedback({ type: 'error', message: 'Пароль должен содержать минимум 8 символов без кириллицы.' })
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
    })
    feedback.value = response
    if (response.type === 'success') { queuePayloadToast(response); window.setTimeout(() => window.location.reload(), 900) }
  } catch (error) {
    console.error('[FoxesCraft] Registration failed', error)
    feedback.value = { type: 'error', message: 'Сервер регистрации временно недоступен.' }
  } finally { submitting.value = false }
}
</script>
<template><RegisterPage :form="form" :strength="strength" :submitting="submitting" :feedback="feedback" @submit="submit" @generate="generatePassword" @navigate="router.push({ name: $event })" /></template>
