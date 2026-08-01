<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { appBootstrap } from '@/app/context'
import { loadStaticPages, type StaticPageDefinition } from '@/content/contentData'
import { themeAsset } from '@/domain/bootstrap'
import StaticContent from '@theme/userOptions/content/StaticContent.vue'

const props = defineProps<{ pageId: string }>()
const page = ref<StaticPageDefinition | null>(null)
const loading = ref(true)
const error = ref(false)

function resolvePageImage(entry: StaticPageDefinition): StaticPageDefinition {
  if (!entry.image || entry.image.startsWith('/')) return entry
  return { ...entry, image: themeAsset(appBootstrap, entry.image.replace(/^assets\//, '')) }
}

async function load(): Promise<void> {
  loading.value = true
  error.value = false
  try {
    const entry = (await loadStaticPages())[props.pageId] ?? null
    page.value = entry ? resolvePageImage(entry) : null
    error.value = !page.value
    if (page.value?.title) {
      const siteTitle = appBootstrap.site.title || 'FoxesCraft'
      document.title = `${page.value.title} — ${siteTitle}`
    }
  } catch (requestError) {
    console.error('[FoxesCraft] Project page content failed', requestError)
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
watch(() => props.pageId, () => void load())
</script>

<template><StaticContent :page-id="pageId" :loading="loading" :error="error" :page="page" /></template>
