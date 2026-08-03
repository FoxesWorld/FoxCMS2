<script setup lang="ts">
import { t } from '@/i18n'

import type { ProfileSettingsFormModel } from '@engine/contracts/user-pages'

defineProps<{ form: ProfileSettingsFormModel }>()
</script>

<template>
  <section class="settings-panel">
    <label>
      <span>{{ t('theme.useroptions.useroptions.profile.options.profileoption.001') }}</span>
      <input v-model="form.login" type="text" minlength="3" maxlength="64" pattern="[A-Za-z0-9_.-]+" required autocomplete="username">
      <small>{{ t('theme.useroptions.useroptions.profile.options.profileoption.002') }}</small>
    </label>
    <label><span>{{ t('theme.useroptions.useroptions.profile.options.profileoption.003') }}</span><input v-model="form.realname" type="text" maxlength="64" autocomplete="name"></label>
    <label><span>{{ t('theme.useroptions.useroptions.profile.options.profileoption.004') }}</span><input v-model="form.userStatus" type="text" maxlength="32" :placeholder="t('theme.useroptions.useroptions.profile.options.profileoption.005')"></label>
    <label><span>{{ t('theme.useroptions.useroptions.profile.options.profileoption.006') }}</span><input v-model="form.land" type="text" maxlength="64" autocomplete="country-name"></label>
    <label><span>{{ t('theme.useroptions.useroptions.profile.options.profileoption.007') }}</span><input v-model="form.email" type="email" required autocomplete="email"></label>
  </section>
</template>
