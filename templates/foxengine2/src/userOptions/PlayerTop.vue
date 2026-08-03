<script setup lang="ts">
import { t } from '@/i18n'

import PlayerCell from '@theme/foxEngine/userTop/playTime/PlayerCell.vue'
interface ServerSession { serverName:string; totalTime:number; lastPlayed:number }
interface Player { uuid:string; login:string; colorScheme?:string; skinHead?:string; serversOnline:string | ServerSession[] | { servers?:Record<string,Omit<ServerSession,'serverName'>> } }
interface RankingEntry { player:Player; seconds:number; lastPlayed:number }
interface Segment { name:string; width:number; color:string }
const props=defineProps<{
  loading:boolean; error:string; serverNames:string[]; activeServers:Set<string>; selectedServer:string; ranking:RankingEntry[]
  formatDuration:(seconds:number)=>string; formatDate:(timestamp:number)=>string; safeAccent:(value?:string)=>string; segments:(player:Player)=>Segment[]
}>()
const emit=defineEmits<{ 'update:selectedServer':[value:string]; profile:[login:string] }>()
function updateServer(event:Event):void{emit('update:selectedServer',(event.target as HTMLSelectElement).value)}
</script>
<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <div v-else-if="error" class="system-message system-message--error"><strong>{{ t('theme.useroptions.playertop.001') }}</strong><p>{{ error }}</p></div>
  <article v-else class="content-surface ranking-page">
    <header class="ranking-header"><div><span class="eyebrow">{{ t('theme.useroptions.playertop.002') }}</span><h1>{{ t('theme.useroptions.playertop.003') }}</h1><p class="lead">{{ t('theme.useroptions.playertop.004') }}</p></div><label><span>{{ t('theme.useroptions.playertop.005') }}</span><select :value="selectedServer" @change="updateServer"><option value="all">{{ t('theme.useroptions.playertop.006') }}</option><option v-for="name in serverNames" :key="name" :value="name">{{ name }}{{ activeServers.has(name) ? '' : t('theme.useroptions.playertop.007') }}</option></select></label></header>
    <div class="ranking-list">
      <PlayerCell v-for="(entry,index) in ranking" :key="entry.player.login" :index="index" :entry="entry" :format-duration="formatDuration" :format-date="formatDate" :safe-accent="safeAccent" :segments="segments" @profile="emit('profile',$event)" />
    </div>
    <p v-if="!ranking.length" class="empty-state">{{ t('theme.useroptions.playertop.008') }}</p>
  </article>
</template>
