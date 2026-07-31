<script setup lang="ts">
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import Info from '@theme/userOptions/pages/Info.vue'
const artwork = themeAsset(appBootstrap, 'img/AbbyAnderson/AbbyAndTheFoxExplaining2.png')
</script>
<template><Info :artwork="artwork" /></template>
