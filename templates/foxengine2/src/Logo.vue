<script setup lang="ts">
defineProps<{ siteTitle: string; siteStatus: string; logoUrl: string }>()
const emit = defineEmits<{ activate: [] }>()
</script>

<template>
  <button class="brand legacy-brand" type="button" aria-label="На главную" @click="emit('activate')">
    <img class="legacy-brand__image" :src="logoUrl" alt="">
    <span class="legacy-brand__copy">
      <strong>{{ siteTitle }}</strong>
      <small>{{ siteStatus || 'Лисий Мир' }}</small>
    </span>
  </button>
</template>
