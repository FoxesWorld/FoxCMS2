<script setup lang="ts">
import type { NavigationDefinition } from '@engine/domain/bootstrap'

defineProps<{ siteTitle: string; serviceVersion: string; items: NavigationDefinition[] }>()
const emit = defineEmits<{ activate: [item: NavigationDefinition] }>()
const currentYear = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer legacy-footer">
    <div class="page-width site-footer__inner">
      <div class="legacy-footer__identity">
        <strong>{{ siteTitle }}</strong>
        <p>Лисий Мир продолжается. 2016–{{ currentYear }}</p>
      </div>
      <nav class="footer-nav" aria-label="Документы и информация">
        <button v-for="item in items" :key="item.route" type="button" @click="emit('activate', item)">
          {{ item.label }}
        </button>
      </nav>
      <span class="site-footer__status">FoxEngine {{ serviceVersion }}</span>
    </div>
  </footer>
</template>
