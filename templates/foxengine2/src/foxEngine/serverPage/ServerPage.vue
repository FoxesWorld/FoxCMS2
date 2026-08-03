<script setup lang="ts">
import { t } from '@/i18n'

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
  <div v-if="loading" class="content-skeleton" :aria-label="t('theme.foxengine.serverpage.serverpage.001')"><span /><span /><span /></div>
  <div v-else-if="error || !details" class="system-message system-message--error"><strong>{{ t('theme.foxengine.serverpage.serverpage.002') }}</strong><p>{{ error }}</p></div>
  <article v-else class="content-surface server-page">
    <section class="server-hero">
      <img v-if="coverUrl && !coverFailed" class="server-hero__cover" :src="coverUrl" :alt="details.serverName" @error="coverFailed = true">
      <div class="server-hero__overlay" />
      <div class="server-hero__content">
        <header class="server-hero__header">
          <div class="server-hero__identity">
            <img v-if="monitor?.favicon" :src="monitor.favicon" :alt="details.serverName">
            <span v-else>F</span>
            <div><span class="eyebrow">{{ t('theme.foxengine.serverpage.serverpage.003') }}</span><h1>{{ details.serverName }}</h1><p>{{ details.serverVersion || monitor?.version || t('theme.foxengine.serverpage.serverpage.004') }}</p></div>
          </div>
          <div class="server-hero__status">
            <strong>{{ monitor?.status === 'online' ? t('theme.foxengine.serverpage.serverpage.005', [monitor.playersOnline ?? 0, monitor.playersMax ?? 0]) : t('theme.foxengine.serverpage.serverpage.006') }}</strong>
          </div>
        </header>

      </div>
    </section>

    <section class="server-panel server-page__about">
      <header><span class="eyebrow">{{ t('theme.foxengine.serverpage.serverpage.007') }}</span><h2>{{ t('theme.foxengine.serverpage.serverpage.008') }}</h2></header>
      <p>{{ details.serverDescription || t('theme.foxengine.serverpage.serverpage.009') }}</p>
      <footer>
        <strong>{{ details.checkLib === 'true' ? t('theme.foxengine.serverpage.serverpage.010') : t('theme.foxengine.serverpage.serverpage.011') }}</strong>
        <span>{{ details.checkLib === 'true' ? t('theme.foxengine.serverpage.serverpage.012') : t('theme.foxengine.serverpage.serverpage.013') }}</span>
      </footer>
    </section>

    <ServerMods :mods="mods" />

  </article>
</template>
