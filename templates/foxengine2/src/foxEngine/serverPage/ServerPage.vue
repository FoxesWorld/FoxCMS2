<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { serverImageUrl } from '@/domain/serverImage'
import ServerMods from './ServerMods.vue'

interface MonitorServer { serverName:string; status:string; version?:string; playersOnline?:number; playersMax?:number; favicon?:string }
interface ServerDetails { serverName:string; serverVersion?:string; serverImage?:string; serverDescription?:string; checkLib?:string }
interface ServerMod { modName:string; modPicture?:string; modDesc?:string }
const props = defineProps<{ loading:boolean; error:string; monitor:MonitorServer|null; details:ServerDetails|null; mods:ServerMod[] }>()
const coverFailed = ref(false)
const coverUrl = computed(() => serverImageUrl(props.details?.serverImage ?? ''))
watch(coverUrl, () => { coverFailed.value = false })
</script>

<template>
  <div v-if="loading" class="content-skeleton" aria-label="Загрузка"><span /><span /><span /></div>
  <div v-else-if="error || !details" class="system-message system-message--error"><strong>Сервер недоступен</strong><p>{{ error }}</p></div>
  <article v-else class="content-surface server-page">
    <section class="server-hero">
      <img v-if="coverUrl && !coverFailed" class="server-hero__cover" :src="coverUrl" :alt="details.serverName" @error="coverFailed = true">
      <div class="server-hero__overlay" />
      <div class="server-hero__content">
        <header class="server-hero__header">
          <div class="server-hero__identity">
            <img v-if="monitor?.favicon" :src="monitor.favicon" :alt="details.serverName">
            <span v-else>F</span>
            <div><span class="eyebrow">FoxesCraft server</span><h1>{{ details.serverName }}</h1><p>{{ details.serverVersion || monitor?.version || 'Версия уточняется' }}</p></div>
          </div>
          <div class="server-hero__status">
            <strong>{{ monitor?.status === 'online' ? `Online · ${monitor.playersOnline ?? 0} / ${monitor.playersMax ?? 0}` : 'Offline' }}</strong>
          </div>
        </header>

      </div>
    </section>

    <section class="server-panel server-page__about">
      <header><span class="eyebrow">Информация</span><h2>О сервере</h2></header>
      <p>{{ details.serverDescription || 'Описание готовится.' }}</p>
      <footer>
        <strong>{{ details.checkLib === 'true' ? 'Проверка библиотек включена' : 'Проверка отключена' }}</strong>
        <span>{{ details.checkLib === 'true' ? 'Лаунчер проверяет и восстанавливает файлы.' : 'Клиент запускается без проверки.' }}</span>
      </footer>
    </section>

    <ServerMods :mods="mods" />

  </article>
</template>
