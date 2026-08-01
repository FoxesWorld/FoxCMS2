<script setup lang="ts">
import type { BadgeDefinition } from '@engine/content/contentData'

defineProps<{ loading: boolean; error: boolean; badge: BadgeDefinition | null }>()
</script>

<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <div
    v-else-if="badge"
    class="badge-runtime-page"
    :data-badge-route="badge.id"
    v-html="badge.html"
  />
  <div v-else-if="error" class="system-message system-message--error">
    <strong>Страница бейджа не найдена</strong>
    <p>Для этого URL отсутствует HTML-страница или связанный бейдж удалён из базы данных.</p>
  </div>
</template>
