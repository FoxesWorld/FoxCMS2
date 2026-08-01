<script setup lang="ts">
import UiCheckbox from '@/components/UiCheckbox.vue'
interface Feedback { type: string; message: string }
interface AuthForm { login: string; password: string; rememberMe: boolean }
defineProps<{ form: AuthForm; submitting: boolean; feedback: Feedback | null }>()
const emit=defineEmits<{ submit: []; navigate: [route:string] }>()
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">С возвращением</span><h1>Авторизация</h1><p class="lead">Войдите в аккаунт, чтобы открыть профиль, прогресс, игровые сервисы и персональные настройки.</p></div>
    <form class="account-form" @submit.prevent="emit('submit')">
      <label><span>Логин</span><input v-model="form.login" name="login" type="text" autocomplete="username" required placeholder="Ваш логин"></label>
      <label><span>Пароль</span><input v-model="form.password" name="password" type="password" autocomplete="current-password" required placeholder="Ваш пароль"></label>
      <UiCheckbox
        v-model="form.rememberMe"
        class="check-field"
        label="Запомнить меня на этом устройстве"
      />
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? 'Проверяем…' : 'Войти' }}</button>
      <button class="text-button" type="button" @click="emit('navigate','lost-password')">Забыли пароль?</button>
    </form>
  </article>
</template>
