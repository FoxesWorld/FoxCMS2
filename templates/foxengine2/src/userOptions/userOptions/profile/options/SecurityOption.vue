<script setup lang="ts">
import type { ProfileSettingsFormModel } from '@engine/contracts/user-pages'

defineProps<{ form: ProfileSettingsFormModel; requireCurrentPassword: boolean }>()
</script>

<template>
  <section class="settings-panel">
    <label v-if="requireCurrentPassword">
      <span>Текущий пароль</span>
      <input v-model="form.currentPassword" type="password" autocomplete="current-password" required>
    </label>
    <label>
      <span>Новый пароль</span>
      <input v-model="form.newPassword" type="password" autocomplete="new-password" placeholder="Оставьте пустым, если не меняете">
    </label>
    <label><span>Повтор нового пароля</span><input v-model="form.repeatPassword" type="password" autocomplete="new-password"></label>
    <div class="notice-panel">
      <strong>Внешние подключения</strong>
      <p>Подключения к внешним сервисам будут доступны только через собственные проверяемые OAuth-контракты.</p>
    </div>
  </section>
</template>
