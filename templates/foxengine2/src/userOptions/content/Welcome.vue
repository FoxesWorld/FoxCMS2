<script setup lang="ts">
import ArtworkShowcase from '@theme/foxEngine/ArtworkShowcase.vue'

defineProps<{ name: string; isGuest: boolean; artwork: string }>()
const emit = defineEmits<{ navigate: [route: string] }>()
</script>

<template>
  <article class="content-surface welcome-native">
    <div class="welcome-native__copy">
      <span class="eyebrow">Лисий Мир 3.0</span>
      <h2>Привет, {{ name }}!</h2>
      <p v-if="isGuest" class="lead">Добро пожаловать в мир приключений, сюжетных выборов и технологических экспериментов.</p>
      <p v-else class="lead">Рада видеть тебя снова. Твой путь, достижения и открытия сохранены.</p>
      <div class="feature-grid">
        <div><strong>Живой мир</strong><span>Истории, выборы и события сообщества.</span></div>
        <div><strong>Прогресс</strong><span>Достижения, игровые профили и уникальные бейджи.</span></div>
        <div><strong>Своя атмосфера</strong><span>Лисы, технологии и самостоятельные игровые миры.</span></div>
      </div>
      <div class="hero__actions">
        <button class="button button--primary button--large" type="button" @click="emit('navigate', 'start')">В путь</button>
        <button class="button button--ghost button--large" type="button" @click="emit('navigate', 'about')">Оглядеться</button>
      </div>
    </div>
    <ArtworkShowcase
      :src="artwork"
      alt="Эбби и лиса приглашают в Лисий Мир"
      caption="Добро пожаловать в Лисий Мир 3.0"
      variant="invite"
      eager
    />
  </article>
</template>
