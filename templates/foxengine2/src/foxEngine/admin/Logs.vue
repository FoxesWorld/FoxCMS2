<script setup lang="ts">
import type { LogEntry } from '@modules/AdminPanel/client/useAdminPanel'

type LogFile = 'lastlog' | 'error' | 'access'
const props = defineProps<{ file: LogFile; entries: LogEntry[]; autoRefresh: boolean }>()
const emit = defineEmits<{
  'update:file': [value: LogFile]
  'update:autoRefresh': [value: boolean]
  reload: []
  clear: []
}>()

function updateFile(event: Event): void { emit('update:file', (event.target as HTMLSelectElement).value as LogFile) }
function updateAuto(event: Event): void { emit('update:autoRefresh', (event.target as HTMLInputElement).checked) }
</script>

<template>
  <section class="admin-section">
    <div class="admin-toolbar">
      <select :value="file" @change="updateFile">
        <option value="lastlog">lastlog.log</option>
        <option value="error">error.log</option>
        <option value="access">access.log</option>
      </select>
      <label class="admin-auto-refresh"><input :checked="autoRefresh" type="checkbox" @change="updateAuto"><span>Обновлять каждые 10 секунд</span></label>
      <button class="button button--ghost" type="button" @click="emit('reload')">Обновить</button>
      <button class="button button--ghost" type="button" @click="emit('clear')">Очистить</button>
    </div>

    <div class="admin-log">
      <p v-if="!props.entries.length">Журнал пуст.</p>
      <div v-for="(entry, index) in props.entries" :key="index" class="admin-log-line" :class="`admin-log-line--${entry.tone}`">
        <b>{{ entry.time }}</b><b>{{ entry.level }}</b><span>{{ entry.message }}</span>
      </div>
    </div>
  </section>
</template>
