<script setup lang="ts">
import { t } from '@/i18n'

import UiCheckbox from '@/components/UiCheckbox.vue'
interface Feedback { type: string; message: string }
interface AuthForm { login: string; password: string; rememberMe: boolean }
defineProps<{ form: AuthForm; submitting: boolean; feedback: Feedback | null }>()
const emit=defineEmits<{ submit: []; navigate: [route:string] }>()
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">{{ t('theme.useroptions.content.guest.auth.001') }}</span><h1>{{ t('theme.useroptions.content.guest.auth.002') }}</h1><p class="lead">{{ t('theme.useroptions.content.guest.auth.003') }}</p></div>
    <form class="account-form" @submit.prevent="emit('submit')">
      <label><span>{{ t('theme.useroptions.content.guest.auth.004') }}</span><input v-model="form.login" name="login" type="text" autocomplete="username" required maxlength="64" :placeholder="t('theme.useroptions.content.guest.auth.005')"></label>
      <label><span>{{ t('theme.useroptions.content.guest.auth.006') }}</span><input v-model="form.password" name="password" type="password" autocomplete="current-password" required maxlength="4096" :placeholder="t('theme.useroptions.content.guest.auth.007')"></label>
      <UiCheckbox
        v-model="form.rememberMe"
        class="check-field"
        :label="t('theme.useroptions.content.guest.auth.008')"
      />
      <p v-if="feedback" class="form-feedback" role="alert" aria-live="polite" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? t('theme.useroptions.content.guest.auth.009') : t('theme.useroptions.content.guest.auth.010') }}</button>
      <button class="text-button" type="button" @click="emit('navigate','lost-password')">{{ t('theme.useroptions.content.guest.auth.011') }}</button>
    </form>
  </article>
</template>
