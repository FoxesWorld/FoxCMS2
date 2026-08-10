<script setup lang="ts">
import { t } from '@/i18n'
import { ref, watch } from 'vue'
import HCaptchaField from '@/components/HCaptchaField.vue'

interface Feedback { type:string; message:string }
const props = defineProps<{ email:string; submitting:boolean; feedback:Feedback|null }>()
const emit=defineEmits<{ 'update:email':[value:string]; submit:[captchaToken:string]; navigate:[route:string] }>()
function update(event:Event):void{emit('update:email',(event.target as HTMLInputElement).value)}

const captchaToken = ref('')
const captcha = ref<InstanceType<typeof HCaptchaField> | null>(null)
watch(() => props.submitting, (submitting, previous) => {
  if (!submitting && previous) captcha.value?.reset()
})
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">{{ t('theme.useroptions.content.guest.lostpassword.001') }}</span><h1>{{ t('theme.useroptions.content.guest.lostpassword.002') }}</h1><p class="lead">{{ t('theme.useroptions.content.guest.lostpassword.003') }}</p></div>
    <form class="account-form" @submit.prevent="emit('submit', captchaToken)">
      <label><span>{{ t('theme.useroptions.content.guest.lostpassword.004') }}</span><input :value="email" type="email" autocomplete="email" required placeholder="mail@example.com" @input="update"></label>
      <HCaptchaField ref="captcha" form="passwordRecovery" :disabled="submitting" @update:token="captchaToken = $event" />
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? t('theme.useroptions.content.guest.lostpassword.005') : t('theme.useroptions.content.guest.lostpassword.006') }}</button>
      <button class="text-button" type="button" @click="emit('navigate','auth')">{{ t('theme.useroptions.content.guest.lostpassword.007') }}</button>
    </form>
  </article>
</template>
