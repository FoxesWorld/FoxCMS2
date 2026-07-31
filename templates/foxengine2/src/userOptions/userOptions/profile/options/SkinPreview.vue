<script setup lang="ts">
defineProps<{ front: string; back: string; loading: boolean }>()
</script>

<template>
  <div class="skin-preview" :class="{ 'skin-preview--loading': loading }">
    <figure><img v-if="front" :src="front" alt="Скин спереди"><figcaption>Спереди</figcaption></figure>
    <figure><img v-if="back" :src="back" alt="Скин сзади"><figcaption>Сзади</figcaption></figure>
  </div>
</template>
