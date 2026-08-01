<script setup lang="ts">
import ArtworkShowcase from '@theme/foxEngine/ArtworkShowcase.vue'
import type { StaticPageDefinition } from '@engine/content/contentData'

defineProps<{ page: StaticPageDefinition }>()
</script>

<template>
  <article class="content-surface prose-page static-content-page" :class="`static-content-page--${page.layout}`">
    <header class="prose-page__header" :class="{ 'has-artwork': page.image }">
      <div>
        <span v-if="page.eyebrow" class="eyebrow">{{ page.eyebrow }}</span>
        <h1>{{ page.title }}</h1>
        <p v-if="page.summary" class="lead">{{ page.summary }}</p>
        <small v-if="page.updated" class="page-updated">Обновлено: {{ page.updated }}</small>
      </div>
      <ArtworkShowcase
        v-if="page.image"
        :src="page.image"
        :alt="page.imageAlt || page.title"
        :caption="page.imageCaption"
        variant="explain"
      />
    </header>

    <ol v-if="page.layout === 'rules'" class="rules-list">
      <li v-for="(section, index) in page.sections.filter((entry) => entry.title)" :key="`${section.title}-${index}`">
        <span>{{ String(index + 1).padStart(2, '0') }}</span>
        <div>
          <h2>{{ section.title }}</h2>
          <p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p>
          <ul v-if="section.items.length"><li v-for="item in section.items" :key="item">{{ item }}</li></ul>
        </div>
      </li>
    </ol>

    <template v-for="(section, index) in page.sections" :key="`${section.title}-${index}`">
      <section v-if="page.layout !== 'rules' && (section.title || section.paragraphs.length || section.items.length || section.cards.length)">
        <h2 v-if="section.title">{{ section.title }}</h2>
        <p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p>
        <ul v-if="section.items.length"><li v-for="item in section.items" :key="item">{{ item }}</li></ul>
        <div v-if="section.cards.length" class="manifest-grid">
          <div v-for="card in section.cards" :key="card.title"><strong>{{ card.title }}</strong><p>{{ card.text }}</p></div>
        </div>
      </section>
      <div v-if="section.notice" class="notice-panel">
        <strong>{{ section.notice.title }}</strong>
        <p>{{ section.notice.text }}</p>
      </div>
    </template>
  </article>
</template>
