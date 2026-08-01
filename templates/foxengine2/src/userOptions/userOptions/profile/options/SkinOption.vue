<script setup lang="ts">
const props = defineProps<{ selectedName: string; selectedSize: number; busy: boolean; locked: boolean }>()
const emit = defineEmits<{ select: [event: Event]; upload: []; remove: [] }>()

function formatBytes(value: number): string {
  if (value < 1024) return `${value} Б`
  return `${(value / 1024).toFixed(value >= 10240 ? 0 : 1)} КБ`
}
</script>

<template>
  <section class="minecraft-resource-card minecraft-resource-card--skin">
    <header>
      <span class="minecraft-resource-card__icon"><i class="fa-solid fa-user" aria-hidden="true" /></span>
      <div><strong>Скин игрока</strong><small>Основной слой и внешний слой модели</small></div>
    </header>
    <ul>
      <li>PNG</li>
      <li>Стандартные и HD-размеры</li>
      <li>Проверка серверной политикой</li>
    </ul>
    <label class="file-button minecraft-resource-card__picker" :class="{ 'has-file': selectedName }">
      <input type="file" accept="image/png" :disabled="busy || locked" @change="emit('select', $event)">
      <i class="fa-solid fa-folder-open" aria-hidden="true" />
      <span>{{ selectedName || 'Выбрать PNG-скин' }}</span>
      <small v-if="selectedName">{{ formatBytes(selectedSize) }}</small>
    </label>
    <div class="minecraft-resource-card__actions">
      <button class="button button--primary" type="button" :disabled="!selectedName || busy || locked" @click="emit('upload')">
        {{ busy ? 'Загрузка…' : 'Применить скин' }}
      </button>
      <button class="button button--ghost" type="button" :disabled="busy || locked" @click="emit('remove')">Сбросить</button>
    </div>
  </section>
</template>
