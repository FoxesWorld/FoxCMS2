<script setup lang="ts">
import { t } from '@/i18n'

import UiCheckbox from '@/components/UiCheckbox.vue'
interface Feedback { type:string; message:string }
interface RegisterForm { login:string; email:string; password1:string; password2:string; dataProcessing:boolean; acceptRules:boolean }
interface Strength { score:number; label:string; width:string }
defineProps<{ form:RegisterForm; strength:Strength; submitting:boolean; feedback:Feedback|null }>()
const emit=defineEmits<{ submit:[]; generate:[]; navigate:[route:string] }>()
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">{{ t('theme.useroptions.content.guest.reg.001') }}</span><h1>{{ t('theme.useroptions.content.guest.reg.002') }}</h1><p class="lead">{{ t('theme.useroptions.content.guest.reg.003') }}</p></div>
    <form class="account-form" @submit.prevent="emit('submit')">
      <label><span>{{ t('theme.useroptions.content.guest.reg.004') }}</span><input v-model="form.login" name="login" type="text" autocomplete="username" required maxlength="64" :placeholder="t('theme.useroptions.content.guest.reg.005')"></label>
      <label><span>{{ t('theme.useroptions.content.guest.reg.006') }}</span><input v-model="form.email" name="email" type="email" autocomplete="email" required :placeholder="t('theme.useroptions.content.guest.reg.007')"></label>
      <label><span>{{ t('theme.useroptions.content.guest.reg.008') }}</span><div class="input-with-action"><input v-model="form.password1" name="password1" type="password" autocomplete="new-password" required :placeholder="t('theme.useroptions.content.guest.reg.009')"><button type="button" @click="emit('generate')">{{ t('theme.useroptions.content.guest.reg.010') }}</button></div></label>
      <div class="password-meter"><div class="password-meter__track"><span :style="{ width: strength.width }" /></div><small>{{ t('theme.useroptions.content.guest.reg.011') }} {{ strength.label }}</small></div>
      <label><span>{{ t('theme.useroptions.content.guest.reg.012') }}</span><input v-model="form.password2" name="password2" type="password" autocomplete="new-password" required :placeholder="t('theme.useroptions.content.guest.reg.013')"></label>
      <UiCheckbox
        v-model="form.dataProcessing"
        class="check-field"
        required
        :label="t('theme.useroptions.content.guest.reg.014')"
      />
      <UiCheckbox v-model="form.acceptRules" class="check-field" required>
        <span>{{ t('theme.useroptions.content.guest.reg.015') }} <button class="inline-link" type="button" @click.stop="emit('navigate','rules')">{{ t('theme.useroptions.content.guest.reg.016') }}</button>.</span>
      </UiCheckbox>
      <p v-if="feedback" class="form-feedback" role="alert" aria-live="polite" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? t('theme.useroptions.content.guest.reg.017') : t('theme.useroptions.content.guest.reg.018') }}</button>
    </form>
  </article>
</template>
