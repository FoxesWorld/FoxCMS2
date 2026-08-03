<script setup lang="ts">
import { t } from '@/i18n'

interface ServerMod { modName:string; modPicture?:string; modDesc?:string }
defineProps<{ mods:ServerMod[] }>()
</script>
<template>
  <section>
    <h2>{{ t('theme.foxengine.serverpage.servermods.001') }}</h2>
    <p v-if="!mods.length" class="empty-state">{{ t('theme.foxengine.serverpage.servermods.002') }}</p>
    <div v-else class="mods-grid"><article v-for="mod in mods" :key="mod.modName" class="mod-card"><img v-if="mod.modPicture" :src="mod.modPicture" :alt="mod.modName"><div><h3>{{ mod.modName }}</h3><p>{{ mod.modDesc || t('theme.foxengine.serverpage.servermods.003') }}</p></div></article></div>
  </section>
</template>
