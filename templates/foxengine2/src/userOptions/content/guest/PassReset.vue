<script setup lang="ts">
import { t } from '@/i18n'
import { ref, watch } from 'vue'
import HCaptchaField from '@/components/HCaptchaField.vue'

interface Feedback { type:string; message:string }
interface ResetForm { password:string; confirmation:string }
const props = defineProps<{ form:ResetForm; tokenAvailable:boolean; submitting:boolean; feedback:Feedback|null }>()
const emit=defineEmits<{ submit:[captchaToken:string]; navigate:[route:string] }>()

const captchaToken = ref('')
const captcha = ref<InstanceType<typeof HCaptchaField> | null>(null)
watch(() => props.submitting, (submitting, previous) => {
  if (!submitting && previous) captcha.value?.reset()
})
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">{{ t('theme.useroptions.content.guest.passreset.001') }}</span><h1>{{ t('theme.useroptions.content.guest.passreset.002') }}</h1><p class="lead">{{ t('theme.useroptions.content.guest.passreset.003') }}</p></div>
    <form class="account-form" @submit.prevent="emit('submit', captchaToken)">
      <label><span>{{ t('theme.useroptions.content.guest.passreset.002') }}</span><input v-model="form.password" type="password" autocomplete="new-password" required :placeholder="t('theme.useroptions.content.guest.passreset.004')"></label>
      <label><span>{{ t('theme.useroptions.content.guest.passreset.005') }}</span><input v-model="form.confirmation" type="password" autocomplete="new-password" required :placeholder="t('theme.useroptions.content.guest.passreset.006')"></label>
      <HCaptchaField ref="captcha" form="passwordReset" :disabled="submitting" @update:token="captchaToken = $event" />
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting || !tokenAvailable">{{ submitting ? t('theme.useroptions.content.guest.passreset.007') : t('theme.useroptions.content.guest.passreset.008') }}</button>
      <button class="text-button" type="button" @click="emit('navigate','lost-password')">{{ t('theme.useroptions.content.guest.passreset.009') }}</button>
    </form>
  </article>
</template>
