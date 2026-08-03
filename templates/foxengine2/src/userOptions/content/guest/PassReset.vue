<script setup lang="ts">
import { t } from '@/i18n'

interface Feedback { type:string; message:string }
interface ResetForm { password:string; confirmation:string }
defineProps<{ form:ResetForm; tokenAvailable:boolean; submitting:boolean; feedback:Feedback|null }>()
const emit=defineEmits<{ submit:[]; navigate:[route:string] }>()
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">{{ t('theme.useroptions.content.guest.passreset.001') }}</span><h1>{{ t('theme.useroptions.content.guest.passreset.002') }}</h1><p class="lead">{{ t('theme.useroptions.content.guest.passreset.003') }}</p></div>
    <form class="account-form" @submit.prevent="emit('submit')">
      <label><span>{{ t('theme.useroptions.content.guest.passreset.002') }}</span><input v-model="form.password" type="password" autocomplete="new-password" required :placeholder="t('theme.useroptions.content.guest.passreset.004')"></label>
      <label><span>{{ t('theme.useroptions.content.guest.passreset.005') }}</span><input v-model="form.confirmation" type="password" autocomplete="new-password" required :placeholder="t('theme.useroptions.content.guest.passreset.006')"></label>
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting || !tokenAvailable">{{ submitting ? t('theme.useroptions.content.guest.passreset.007') : t('theme.useroptions.content.guest.passreset.008') }}</button>
      <button class="text-button" type="button" @click="emit('navigate','lost-password')">{{ t('theme.useroptions.content.guest.passreset.009') }}</button>
    </form>
  </article>
</template>
