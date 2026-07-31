<script setup lang="ts">
import type { ProfileSettingsFormModel } from '@engine/contracts/user-pages'

defineProps<{ form: ProfileSettingsFormModel }>()
</script>

<template>
  <section class="settings-panel">
    <label>
      <span>Логин</span>
      <input v-model="form.login" type="text" minlength="3" maxlength="64" pattern="[A-Za-z0-9_.-]+" required autocomplete="username">
      <small>Логин можно изменить; UUID аккаунта останется прежним.</small>
    </label>
    <label><span>Отображаемое имя</span><input v-model="form.realname" type="text" maxlength="64" autocomplete="name"></label>
    <label><span>Статус</span><input v-model="form.userStatus" type="text" maxlength="32" placeholder="Короткая строка в профиле"></label>
    <label><span>Страна или регион</span><input v-model="form.land" type="text" maxlength="64" autocomplete="country-name"></label>
    <label><span>Электронная почта</span><input v-model="form.email" type="email" required autocomplete="email"></label>
  </section>
</template>
