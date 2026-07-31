<script setup lang="ts">
defineProps<{ isGuest:boolean; login:string; vkLink:string; discordLink:string; downloading:boolean; downloadError:string; windowsIcon:string }>()
const emit=defineEmits<{ navigate:[route:string]; download:[]; external:[url:string] }>()
</script>
<template>
  <article class="content-surface prose-page start-page">
    <header><span class="eyebrow">Пять шагов</span><h1>Начать игру</h1><p class="lead">Создайте аккаунт и запустите FoxesCraft. JVM, лаунчер и обновления устанавливаются автоматически.</p></header>
    <ol class="journey-steps">
      <li><span class="journey-steps__number">01</span><div><h2>{{ isGuest ? 'Создайте аккаунт' : `Аккаунт ${login} готов` }}</h2><p>{{ isGuest ? 'Регистрация связывает игровой профиль, прогресс и доступ к сервисам сообщества.' : 'Первый этап уже завершён. Можно загружать FoxesCraft.' }}</p><button v-if="isGuest" class="button button--primary" type="button" @click="emit('navigate','register')">Регистрация</button></div></li>
      <li><span class="journey-steps__number">02</span><div><h2>Загрузите FoxesCraft</h2><p>Компактный нативный bootstrapper не требует заранее установленной Java.</p><div class="download-actions"><button class="button button--primary" type="button" :disabled="downloading" @click="emit('download')"><img :src="windowsIcon" alt="">{{ downloading ? 'Подготовка…' : 'Windows x64' }}</button></div><p v-if="downloadError" class="form-error">{{ downloadError }}</p></div></li>
      <li><span class="journey-steps__number">03</span><div><h2>Запустите FoxesCraft.exe</h2><p>Bootstrapper проверит собственный SHA-256, установит требуемую JVM и загрузит доверенный production launcher.</p></div></li>
      <li><span class="journey-steps__number">04</span><div><h2>Следите за обновлениями</h2><p>Bootstrapper и launcher обновляются автоматически. Новости и технические работы публикуются в официальных сообществах.</p><div class="download-actions"><button v-if="vkLink" class="button button--ghost" type="button" @click="emit('external',vkLink)">VK</button><button v-if="discordLink" class="button button--ghost" type="button" @click="emit('external',discordLink)">Discord</button></div></div></li>
      <li><span class="journey-steps__number">05</span><div><h2>Запускайте экспедицию</h2><p>Авторизуйтесь в лаунчере, выберите сервер и дождитесь синхронизации сборки. Добро пожаловать в Лисий Мир.</p></div></li>
    </ol>
  </article>
</template>
