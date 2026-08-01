<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { loadBadges, type BadgeDefinition } from '@engine/content/contentData'
import Badges from '@theme/userOptions/pages/badges/Badges.vue'

const badges = ref<readonly BadgeDefinition[]>([])
const loading = ref(true)
const error = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = false
  try {
    badges.value = await loadBadges()
    const siteTitle = appBootstrap.site.title || 'FoxesCraft'
    document.title = `Бейджи — ${siteTitle}`
  } catch (requestError) {
    console.error('[FoxesCraft] Badge catalog failed', requestError)
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
</script>

<template><Badges :badges="badges" :loading="loading" :error="error" /></template>
