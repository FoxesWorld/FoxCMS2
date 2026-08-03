<script setup lang="ts">
import { t } from '@/i18n'

import type { ProfileSettingsFormModel } from '@engine/contracts/user-pages'

defineProps<{ form: ProfileSettingsFormModel; requireCurrentPassword: boolean }>()
</script>

<template>
  <section class="settings-panel">
    <label v-if="requireCurrentPassword">
      <span>{{ t('theme.useroptions.useroptions.profile.options.securityoption.001') }}</span>
      <input v-model="form.currentPassword" type="password" autocomplete="current-password" required>
    </label>
    <label>
      <span>{{ t('theme.useroptions.useroptions.profile.options.securityoption.002') }}</span>
      <input v-model="form.newPassword" type="password" autocomplete="new-password" :placeholder="t('theme.useroptions.useroptions.profile.options.securityoption.003')">
    </label>
    <label><span>{{ t('theme.useroptions.useroptions.profile.options.securityoption.004') }}</span><input v-model="form.repeatPassword" type="password" autocomplete="new-password"></label>
    <div class="notice-panel">
      <strong>{{ t('theme.useroptions.useroptions.profile.options.securityoption.005') }}</strong>
      <p>{{ t('theme.useroptions.useroptions.profile.options.securityoption.006') }}</p>
    </div>
  </section>
</template>
