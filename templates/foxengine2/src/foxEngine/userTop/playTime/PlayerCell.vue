<script setup lang="ts">
interface ServerSession { serverName:string; totalTime:number; lastPlayed:number }
interface Player { login:string; colorScheme?:string; serversOnline:string|ServerSession[]|{servers?:Record<string,Omit<ServerSession,'serverName'>>} }
interface RankingEntry { player:Player; seconds:number; lastPlayed:number }
interface Segment { name:string; width:number; color:string }
defineProps<{ index:number; entry:RankingEntry; formatDuration:(seconds:number)=>string; formatDate:(timestamp:number)=>string; safeAccent:(value?:string)=>string; segments:(player:Player)=>Segment[] }>()
const emit=defineEmits<{ profile:[login:string] }>()
</script>
<template>
  <button class="ranking-row" type="button" @click="emit('profile',entry.player.login)">
    <span class="ranking-position">{{ index+1 }}</span>
    <span class="ranking-avatar" :style="{ '--player-accent': safeAccent(entry.player.colorScheme) }">{{ entry.player.login.slice(0,1).toUpperCase() }}</span>
    <span class="ranking-player"><strong>{{ entry.player.login }}</strong><small>{{ formatDate(entry.lastPlayed) }}</small></span>
    <span class="ranking-time"><strong>{{ formatDuration(entry.seconds) }}</strong><span class="ranking-bar"><i v-for="segment in segments(entry.player)" :key="segment.name" :title="segment.name" :style="{ width:`${segment.width}%`, background:segment.color }" /></span></span>
  </button>
</template>
