<script setup lang="ts">
interface ServerMod { modName:string; modPicture?:string; modDesc?:string }
defineProps<{ mods:ServerMod[] }>()
</script>
<template>
  <section>
    <h2>Основные модификации</h2>
    <p v-if="!mods.length" class="empty-state">Список модификаций пока не опубликован.</p>
    <div v-else class="mods-grid"><article v-for="mod in mods" :key="mod.modName" class="mod-card"><img v-if="mod.modPicture" :src="mod.modPicture" :alt="mod.modName"><div><h3>{{ mod.modName }}</h3><p>{{ mod.modDesc || 'Описание отсутствует.' }}</p></div></article></div>
  </section>
</template>
