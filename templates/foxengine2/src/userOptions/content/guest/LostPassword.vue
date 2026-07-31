<script setup lang="ts">
interface Feedback { type:string; message:string }
defineProps<{ email:string; submitting:boolean; feedback:Feedback|null }>()
const emit=defineEmits<{ 'update:email':[value:string]; submit:[]; navigate:[route:string] }>()
function update(event:Event):void{emit('update:email',(event.target as HTMLInputElement).value)}
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">Восстановление аккаунта</span><h1>Восстановление доступа</h1><p class="lead">Укажите почту аккаунта. Сервер отправит дальнейшие инструкции, если адрес найден.</p></div>
    <form class="account-form" @submit.prevent="emit('submit')">
      <label><span>Электронная почта</span><input :value="email" type="email" autocomplete="email" required placeholder="mail@example.com" @input="update"></label>
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? 'Отправляем…' : 'Восстановить доступ' }}</button>
      <button class="text-button" type="button" @click="emit('navigate','auth')">Вернуться ко входу</button>
    </form>
  </article>
</template>
