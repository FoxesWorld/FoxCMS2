<script setup lang="ts">
import type { StaticPageDefinition } from '@engine/content/contentData'
import StaticPage from './StaticPage.vue'

defineProps<{ pageId: string; loading: boolean; error: boolean; page: StaticPageDefinition | null }>()
</script>

<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <StaticPage v-else-if="page" :page="page" />
  <div v-else-if="error" class="system-message system-message--error">
    <strong>Страница не найдена</strong>
    <p>Запрошенный материал отсутствует в runtime-реестре темы.</p>
  </div>
</template>
