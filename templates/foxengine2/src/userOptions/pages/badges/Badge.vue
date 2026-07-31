<script setup lang="ts">
import type { BadgeDefinition } from '@engine/content/contentData'
defineProps<{ loading: boolean; error: boolean; badge: BadgeDefinition | null }>()
</script>
<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <article v-else-if="badge" class="content-surface badge-page">
    <header class="badge-page__header">
      <div class="badge-page__visual"><img v-if="badge.image" :src="badge.image" :alt="badge.title"><span v-else>B</span></div>
      <div><span class="eyebrow">FoxesCraft badge</span><h1>{{ badge.title }}</h1><p class="lead">{{ badge.description }}</p></div>
    </header>
    <section class="badge-story"><p v-for="paragraph in badge.paragraphs" :key="paragraph">{{ paragraph }}</p></section>
  </article>
  <div v-else-if="error" class="system-message system-message--error"><strong>Бэйдж не найден</strong><p>Возможно, ссылка устарела или описание отсутствует.</p></div>
</template>
