<script setup lang="ts">
interface Feedback { type:string; message:string }
interface ResetForm { password:string; confirmation:string }
defineProps<{ form:ResetForm; tokenAvailable:boolean; submitting:boolean; feedback:Feedback|null }>()
const emit=defineEmits<{ submit:[]; navigate:[route:string] }>()
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">Одноразовое восстановление</span><h1>Новый пароль</h1><p class="lead">Ссылка действует один час и становится недействительной сразу после успешного изменения пароля.</p></div>
    <form class="account-form" @submit.prevent="emit('submit')">
      <label><span>Новый пароль</span><input v-model="form.password" type="password" autocomplete="new-password" required placeholder="Минимум 10 символов"></label>
      <label><span>Повторите пароль</span><input v-model="form.confirmation" type="password" autocomplete="new-password" required placeholder="Тот же пароль ещё раз"></label>
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting || !tokenAvailable">{{ submitting ? 'Сохраняем…' : 'Изменить пароль' }}</button>
      <button class="text-button" type="button" @click="emit('navigate','lost-password')">Запросить новую ссылку</button>
    </form>
  </article>
</template>
