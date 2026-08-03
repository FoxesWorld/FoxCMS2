<script setup lang="ts">
import { t } from '@/i18n'

defineProps<{ siteTitle: string; siteStatus: string; logoUrl: string }>()
const emit = defineEmits<{ activate: [] }>()
</script>

<template>
  <button class="brand legacy-brand" type="button" :aria-label="t('theme.logo.001')" @click="emit('activate')">
    <img class="legacy-brand__image" :src="logoUrl" alt="">
    <span class="legacy-brand__copy">
      <strong>{{ siteTitle }}</strong>
      <small>{{ siteStatus || t('theme.logo.002') }}</small>
    </span>
  </button>
</template>
