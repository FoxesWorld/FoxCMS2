<script setup lang="ts">
import { useRouter } from 'vue-router'
import { appBootstrap } from '@engine/app/context'
import { bootstrapString, themeAsset } from '@engine/domain/bootstrap'
import Welcome from '@theme/userOptions/content/Welcome.vue'
const router = useRouter()
const isGuest = bootstrapString(appBootstrap, 'groupTag', 'guest') === 'guest'
const name = bootstrapString(appBootstrap, 'realname', bootstrapString(appBootstrap, 'login', 'путник'))
const artwork = themeAsset(appBootstrap, 'img/AbbyAnderson/AbbyAndFoxInviting2.png')
function navigate(route: string): void { void router.push({ name: route }) }
</script>
<template><Welcome :name="name" :is-guest="isGuest" :artwork="artwork" @navigate="navigate" /></template>
