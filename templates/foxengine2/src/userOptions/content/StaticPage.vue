<script setup lang="ts">
import type { StaticPageDefinition } from '@engine/content/contentData'
defineProps<{ page: StaticPageDefinition }>()
</script>
<template>
  <article class="content-surface prose-page static-content-page">
    <header><span class="eyebrow">{{ page.eyebrow }}</span><h1>{{ page.title }}</h1><p class="lead">{{ page.summary }}</p><small v-if="page.updated" class="page-updated">Обновлено: {{ page.updated }}</small></header>
    <section v-for="section in page.sections" :key="section.title"><h2>{{ section.title }}</h2><p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p><ul v-if="section.items"><li v-for="item in section.items" :key="item">{{ item }}</li></ul></section>
  </article>
</template>
