<script setup lang="ts">
import { t } from '@/i18n'

defineProps<{ title: string; body: string; savedAt: string }>()
const emit = defineEmits<{ 'update:title': [value: string]; 'update:body': [value: string]; clear: []; export: [] }>()
function input(event: Event, field: 'title' | 'body'): void { emit(`update:${field}` as 'update:title', (event.target as HTMLInputElement | HTMLTextAreaElement).value) }
</script>
<template>
  <article class="content-surface draft-page">
    <header><span class="eyebrow">{{ t('theme.useroptions.article.001') }}</span><h1>{{ t('theme.useroptions.article.002') }}</h1><p class="lead">{{ t('theme.useroptions.article.003') }}</p></header>
    <label><span>{{ t('theme.useroptions.article.004') }}</span><input :value="title" type="text" maxlength="120" :placeholder="t('theme.useroptions.article.005')" @input="input($event, 'title')"></label>
    <label><span>{{ t('theme.useroptions.article.006') }}</span><textarea :value="body" rows="18" :placeholder="t('theme.useroptions.article.007')" @input="input($event, 'body')" /></label>
    <div class="draft-actions"><span>{{ savedAt ? t('theme.useroptions.article.008', [savedAt]) : t('theme.useroptions.article.009') }}</span><button class="button button--ghost" type="button" @click="emit('clear')">{{ t('theme.useroptions.article.010') }}</button><button class="button button--primary" type="button" @click="emit('export')">{{ t('theme.useroptions.article.011') }}</button></div>
  </article>
</template>
