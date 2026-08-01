<script setup lang="ts">
import type { StaticPageDefinition } from '@engine/content/contentData'

defineProps<{ page: StaticPageDefinition }>()
</script>

<template>
  <div class="static-page-html" v-html="page.html" />
</template>
