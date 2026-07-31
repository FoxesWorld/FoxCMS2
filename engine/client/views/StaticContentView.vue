<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { loadStaticPages, type StaticPageDefinition } from '@engine/content/contentData'
import StaticContent from '@theme/userOptions/content/StaticContent.vue'
const props = defineProps<{ pageId: string }>()
const page = ref<StaticPageDefinition | null>(null)
const loading = ref(true)
const error = ref(false)
async function load(): Promise<void> {
  loading.value = true; error.value = false
  try { page.value = (await loadStaticPages())[props.pageId] ?? null; error.value = !page.value }
  catch (requestError) { console.error('[FoxesCraft] Static content failed', requestError); error.value = true }
  finally { loading.value = false }
}
onMounted(() => void load())
watch(() => props.pageId, () => void load())
</script>
<template><StaticContent :page-id="pageId" :loading="loading" :error="error" :page="page" /></template>
