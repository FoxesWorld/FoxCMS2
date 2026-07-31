<script setup lang="ts">
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import { useServerMonitor } from '@engine/shell/useServerMonitor'
import ServerEntry from './ServerEntry.vue'
import TotalOnline from './TotalOnline.vue'
const icon=themeAsset(appBootstrap,'icons/monitor.png')
const { servers,total,loading,error,openServer }=useServerMonitor()
</script>
<template>
  <section class="sidebar-card legacy-sidebar-card">
    <div class="sidebar-card__heading legacy-card-title"><img :src="icon" alt="" aria-hidden="true"><div><strong>Мониторинг</strong><small>Состояние игровых миров</small></div></div>
    <div class="server-monitor">
      <div v-if="loading" class="sidebar-placeholder">Получаем состояние серверов…</div>
      <div v-else-if="error && !servers.length" class="sidebar-placeholder">Мониторинг временно недоступен.</div>
      <template v-else>
        <ServerEntry v-for="server in servers" :key="server.serverName" :server="server" @open="openServer" />
        <TotalOnline :online="total.online" :max="total.max" />
      </template>
    </div>
  </section>
</template>
