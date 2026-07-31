<script setup lang="ts">
type LogFile='lastlog'|'error'|'access'
defineProps<{ file:LogFile; lines:string[]; autoRefresh:boolean }>()
const emit=defineEmits<{ 'update:file':[value:LogFile]; 'update:autoRefresh':[value:boolean]; reload:[]; clear:[] }>()
function updateFile(event:Event):void{emit('update:file',(event.target as HTMLSelectElement).value as LogFile)}
function updateAuto(event:Event):void{emit('update:autoRefresh',(event.target as HTMLInputElement).checked)}
</script>
<template>
  <section class="admin-section">
    <div class="admin-toolbar">
      <select :value="file" @change="updateFile"><option value="lastlog">lastlog.log</option><option value="error">error.log</option><option value="access">access.log</option></select>
      <label class="admin-auto-refresh"><input :checked="autoRefresh" type="checkbox" @change="updateAuto"><span>Обновлять каждые 10 секунд</span></label>
      <button class="button button--ghost" type="button" @click="emit('reload')">Обновить</button><button class="button button--ghost" type="button" @click="emit('clear')">Очистить</button>
    </div>
    <pre class="admin-log"><code v-for="(line,index) in lines" :key="`${index}-${line}`">{{ line }}
</code></pre>
  </section>
</template>
