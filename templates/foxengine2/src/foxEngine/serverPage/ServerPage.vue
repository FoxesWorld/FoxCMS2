<script setup lang="ts">
import ServerMods from './ServerMods.vue'
interface MonitorServer { serverName:string; status:string; version?:string; playersOnline?:number; playersMax?:number; favicon?:string }
interface ServerDetails { serverName:string; serverVersion?:string; serverImage?:string; serverDescription?:string; checkLib?:string }
interface ServerMod { modName:string; modPicture?:string; modDesc?:string }
defineProps<{ loading:boolean; error:string; monitor:MonitorServer|null; details:ServerDetails|null; mods:ServerMod[] }>()
</script>
<template>
  <div v-if="loading" class="content-skeleton" aria-label="Загрузка"><span /><span /><span /></div>
  <div v-else-if="error || !details" class="system-message system-message--error"><strong>Сервер недоступен</strong><p>{{ error }}</p></div>
  <article v-else class="content-surface server-page">
    <header class="server-page__header">
      <div class="server-page__identity"><img v-if="monitor?.favicon" :src="monitor.favicon" :alt="details.serverName"><span v-else class="server-page__fallback">F</span><div><span class="eyebrow">Minecraft server</span><h1>{{ details.serverName }}</h1><p>{{ details.serverVersion || monitor?.version || 'Версия уточняется' }}</p></div></div>
      <div class="server-page__state" :class="{ 'server-page__state--offline': monitor?.status !== 'online' }"><strong>{{ monitor?.status === 'online' ? 'Online' : 'Offline' }}</strong><span>{{ monitor?.status === 'online' ? `${monitor.playersOnline ?? 0} / ${monitor.playersMax ?? 0} игроков` : 'Сервер не отвечает' }}</span></div>
    </header>
    <img v-if="details.serverImage" class="server-page__cover" :src="details.serverImage" :alt="details.serverName">
    <section><h2>О сервере</h2><p>{{ details.serverDescription || 'Описание сервера готовится.' }}</p><div class="security-badge" :class="{ 'security-badge--warning': details.checkLib !== 'true' }"><strong>{{ details.checkLib === 'true' ? 'Проверенные библиотеки' : 'Требуется проверка библиотек' }}</strong><span>{{ details.checkLib === 'true' ? 'Компоненты сервера проходят контроль целостности.' : 'Часть компонентов использует старый механизм проверки.' }}</span></div></section>
    <ServerMods :mods="mods" />
  </article>
</template>
