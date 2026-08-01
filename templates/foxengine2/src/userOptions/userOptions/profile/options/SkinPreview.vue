<script setup lang="ts">
defineProps<{ front: string; back: string; loading: boolean }>()
</script>

<template>
  <div class="skin-preview" :class="{ 'skin-preview--loading': loading }" aria-live="polite">
    <div v-if="loading" class="skin-preview__loading"><i class="fa-solid fa-rotate" aria-hidden="true" /><span>Строим preview</span></div>
    <figure>
      <div class="skin-preview__model"><img v-if="front" :src="front" alt="Скин спереди"><span v-else>FRONT</span></div>
      <figcaption>Спереди</figcaption>
    </figure>
    <figure>
      <div class="skin-preview__model"><img v-if="back" :src="back" alt="Скин сзади"><span v-else>BACK</span></div>
      <figcaption>Сзади</figcaption>
    </figure>
  </div>
</template>
