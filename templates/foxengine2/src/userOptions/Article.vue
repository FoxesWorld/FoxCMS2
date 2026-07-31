<script setup lang="ts">
defineProps<{ title: string; body: string; savedAt: string }>()
const emit = defineEmits<{ 'update:title': [value: string]; 'update:body': [value: string]; clear: []; export: [] }>()
function input(event: Event, field: 'title' | 'body'): void { emit(`update:${field}` as 'update:title', (event.target as HTMLInputElement | HTMLTextAreaElement).value) }
</script>
<template>
  <article class="content-surface draft-page">
    <header><span class="eyebrow">Локальная мастерская</span><h1>Черновик гайда</h1><p class="lead">Лёгкий редактор без стороннего WYSIWYG. Черновик хранится только в этом браузере.</p></header>
    <label><span>Название</span><input :value="title" type="text" maxlength="120" placeholder="О чём будет гайд" @input="input($event, 'title')"></label>
    <label><span>Текст</span><textarea :value="body" rows="18" placeholder="Структура, команды, примеры, примечания…" @input="input($event, 'body')" /></label>
    <div class="draft-actions"><span>{{ savedAt ? `Сохранено в ${savedAt}` : 'Изменения сохраняются автоматически' }}</span><button class="button button--ghost" type="button" @click="emit('clear')">Очистить</button><button class="button button--primary" type="button" @click="emit('export')">Экспортировать Markdown</button></div>
  </article>
</template>
