<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { loadBadge, type BadgeDefinition } from '@engine/content/contentData'
import Badge from '@theme/userOptions/pages/badges/Badge.vue'

const props = defineProps<{ id: string }>()
const badge = ref<BadgeDefinition | null>(null)
const loading = ref(true)
const error = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = false
  badge.value = null
  try {
    badge.value = await loadBadge(props.id)
    const siteTitle = appBootstrap.site.title || 'FoxesCraft'
    document.title = `${badge.value.title} — ${siteTitle}`
  } catch (requestError) {
    console.error('[FoxesCraft] Badge HTML page failed', requestError)
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
watch(() => props.id, () => void load())
</script>

<template><Badge :loading="loading" :error="error" :badge="badge" /></template>
