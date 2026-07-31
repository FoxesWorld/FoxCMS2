<script setup lang="ts">
const props = defineProps<{ selectedName: string; busy: boolean }>()
const emit = defineEmits<{ select: [event: Event]; upload: []; remove: [] }>()
</script>

<template>
  <section>
    <h2>Плащ</h2>
    <p>Плащ загружается безопасным движковым контрактом и не зависит от старого jQuery runtime.</p>
    <label class="file-button"><input type="file" accept="image/png" @change="emit('select', $event)"><span>{{ selectedName || 'Выбрать PNG' }}</span></label>
    <div><button class="button button--primary" type="button" :disabled="!selectedName || busy" @click="emit('upload')">{{ busy ? 'Обработка…' : 'Загрузить' }}</button><button class="button button--ghost" type="button" :disabled="busy" @click="emit('remove')">Удалить</button></div>
  </section>
</template>
