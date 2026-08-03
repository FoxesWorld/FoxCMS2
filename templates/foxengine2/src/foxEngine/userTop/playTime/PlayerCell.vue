<script setup lang="ts">
interface ServerSession { serverName:string; totalTime:number; lastPlayed:number }
interface Player { uuid:string; login:string; colorScheme?:string; skinHead?:string; serversOnline:string|ServerSession[]|{servers?:Record<string,Omit<ServerSession,'serverName'>>} }
interface RankingEntry { player:Player; seconds:number; lastPlayed:number }
interface Segment { name:string; width:number; color:string }
defineProps<{ index:number; entry:RankingEntry; formatDuration:(seconds:number)=>string; formatDate:(timestamp:number)=>string; safeAccent:(value?:string)=>string; segments:(player:Player)=>Segment[] }>()
const emit=defineEmits<{ profile:[login:string] }>()

function hideBrokenImage(event:Event):void {
  const image=event.currentTarget
  if (image instanceof HTMLImageElement) image.hidden=true
}
</script>
<template>
  <button class="ranking-row" type="button" @click="emit('profile',entry.player.login)">
    <span class="ranking-position">{{ index+1 }}</span>
    <span class="ranking-avatar" :style="{ '--player-accent': safeAccent(entry.player.colorScheme) }">
      <span class="ranking-avatar__fallback" aria-hidden="true">{{ entry.player.login.slice(0,1).toUpperCase() }}</span>
      <img
        v-if="entry.player.skinHead"
        class="ranking-avatar__image"
        :src="entry.player.skinHead"
        :alt="`Голова скина игрока ${entry.player.login}`"
        decoding="async"
        draggable="false"
        @error="hideBrokenImage"
      >
    </span>
    <span class="ranking-player"><strong>{{ entry.player.login }}</strong><small>{{ formatDate(entry.lastPlayed) }}</small></span>
    <span class="ranking-time"><strong>{{ formatDuration(entry.seconds) }}</strong><span class="ranking-bar"><i v-for="segment in segments(entry.player)" :key="segment.name" :title="segment.name" :style="{ width:`${segment.width}%`, background:segment.color }" /></span></span>
  </button>
</template>
