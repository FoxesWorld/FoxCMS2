<script setup lang="ts">
interface Feedback { type:string; message:string }
interface RegisterForm { login:string; email:string; password1:string; password2:string; dataProcessing:boolean; acceptRules:boolean }
interface Strength { score:number; label:string; width:string }
defineProps<{ form:RegisterForm; strength:Strength; submitting:boolean; feedback:Feedback|null }>()
const emit=defineEmits<{ submit:[]; generate:[]; navigate:[route:string] }>()
</script>
<template>
  <article class="content-surface account-page">
    <div class="account-page__intro"><span class="eyebrow">Новая учётная запись</span><h1>Регистрация</h1><p class="lead">Один аккаунт для лаунчера, профиля, прогресса и сервисов сообщества.</p></div>
    <form class="account-form" @submit.prevent="emit('submit')">
      <label><span>Логин</span><input v-model="form.login" name="login" type="text" autocomplete="username" required maxlength="32" placeholder="Уникальный логин"></label>
      <label><span>Электронная почта</span><input v-model="form.email" name="email" type="email" autocomplete="email" required placeholder="Настоящая почта для восстановления"></label>
      <label><span>Пароль</span><div class="input-with-action"><input v-model="form.password1" name="password1" type="password" autocomplete="new-password" required placeholder="Минимум 8 символов"><button type="button" @click="emit('generate')">Сгенерировать</button></div></label>
      <div class="password-meter"><div class="password-meter__track"><span :style="{ width: strength.width }" /></div><small>Надёжность: {{ strength.label }}</small></div>
      <label><span>Повторите пароль</span><input v-model="form.password2" name="password2" type="password" autocomplete="new-password" required placeholder="Повтор пароля"></label>
      <label class="check-field"><input v-model="form.dataProcessing" type="checkbox" required><span>Даю согласие на обработку данных, необходимых для работы аккаунта.</span></label>
      <label class="check-field"><input v-model="form.acceptRules" type="checkbox" required><span>Принимаю <button class="inline-link" type="button" @click="emit('navigate','rules')">правила проекта</button>.</span></label>
      <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
      <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? 'Создаём аккаунт…' : 'Зарегистрироваться' }}</button>
    </form>
  </article>
</template>
