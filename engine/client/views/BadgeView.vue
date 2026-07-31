<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { loadBadges, type BadgeDefinition } from '@engine/content/contentData'
import Badge from '@theme/userOptions/pages/badges/Badge.vue'
const props = defineProps<{ id: string }>()
const badge = ref<BadgeDefinition | null>(null)
const loading = ref(true)
const error = ref(false)
async function load(): Promise<void> {
  loading.value = true; error.value = false
  try {
    const normalized = props.id.toLowerCase()
    badge.value = (await loadBadges()).find((entry) => entry.id.toLowerCase() === normalized) ?? null
    error.value = !badge.value
  } catch (requestError) { console.error('[FoxesCraft] Badge content failed', requestError); error.value = true }
  finally { loading.value = false }
}
onMounted(() => void load())
watch(() => props.id, () => void load())
</script>
<template><Badge :loading="loading" :error="error" :badge="badge" /></template>
