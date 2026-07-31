<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import Article from '@theme/userOptions/Article.vue'
const STORAGE_KEY = 'foxescraft.guide-draft.v2'
const title = ref('')
const body = ref('')
const savedAt = ref('')
let timer: number | undefined
function save(): void { localStorage.setItem(STORAGE_KEY, JSON.stringify({ title: title.value, body: body.value })); savedAt.value = new Intl.DateTimeFormat('ru', { hour: '2-digit', minute: '2-digit' }).format(new Date()) }
function clear(): void { title.value = ''; body.value = ''; localStorage.removeItem(STORAGE_KEY); savedAt.value = '' }
function exportDraft(): void {
  const blob = new Blob([`# ${title.value || 'Без названия'}

${body.value}`], { type: 'text/markdown;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a'); anchor.href = url; anchor.download = `${(title.value || 'foxescraft-guide').replace(/[^\p{L}\p{N}_-]+/gu, '-')}.md`; anchor.click(); URL.revokeObjectURL(url)
}
onMounted(() => { try { const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}'); title.value = stored.title ?? ''; body.value = stored.body ?? '' } catch { localStorage.removeItem(STORAGE_KEY) } })
watch([title, body], () => { window.clearTimeout(timer); timer = window.setTimeout(save, 500) })
</script>
<template><Article v-model:title="title" v-model:body="body" :saved-at="savedAt" @clear="clear" @export="exportDraft" /></template>
