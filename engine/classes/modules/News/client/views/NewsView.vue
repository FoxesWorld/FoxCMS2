<script setup lang="ts">
import { computed } from 'vue'
import NewsArticle from '@theme/news/NewsArticle.vue'
const props = defineProps<{ id: string }>()
const postId = computed(() => Number(props.id))
</script>
<template><NewsArticle :id="postId" /></template>
