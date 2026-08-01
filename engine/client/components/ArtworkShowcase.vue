<script setup lang="ts">
withDefaults(defineProps<{
  src: string
  alt: string
  caption?: string
  variant?: 'invite' | 'explain'
  eager?: boolean
}>(), {
  caption: '',
  variant: 'invite',
  eager: false,
})
</script>

<template>
  <figure class="abby-showcase" :class="`abby-showcase--${variant}`">
    <div class="abby-showcase__media">
      <img
        :src="src"
        :alt="alt"
        :loading="eager ? 'eager' : 'lazy'"
        :fetchpriority="eager ? 'high' : 'auto'"
        decoding="async"
      >
    </div>

    <figcaption v-if="caption" class="abby-showcase__caption">
      {{ caption }}
    </figcaption>
  </figure>
</template>
