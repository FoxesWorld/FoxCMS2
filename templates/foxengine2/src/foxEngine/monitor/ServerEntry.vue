<script setup lang="ts">
import type { ServerStatus } from '@engine/contracts/sidebar'
defineProps<{ server:ServerStatus }>()
const emit=defineEmits<{ open:[name:string] }>()
</script>
<template>
  <button class="server-row" type="button" @click="emit('open',server.serverName)">
    <img v-if="server.favicon" :src="server.favicon" :alt="`${server.serverName} icon`">
    <span v-else class="server-row__fallback">F</span>
    <span class="server-row__identity"><strong>{{ server.serverName }}</strong><small>{{ server.version || 'Версия уточняется' }}</small></span>
    <span class="server-row__online" :class="{ 'server-row__online--offline': server.status !== 'online' }">{{ server.status === 'online' ? `${server.playersOnline ?? 0}/${server.playersMax ?? 0}` : 'offline' }}</span>
  </button>
</template>
