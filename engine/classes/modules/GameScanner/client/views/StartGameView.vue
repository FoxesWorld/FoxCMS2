<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import StartGamePage from '@theme/userOptions/pages/StartGame.vue'
import { appBootstrap } from '@/app/context'
import { bootstrapNumber, bootstrapString, themeAsset } from '@/domain/bootstrap'

const router = useRouter()
const isGuest = bootstrapNumber(appBootstrap, 'user_group', 5) === 5
const login = bootstrapString(appBootstrap, 'login')
const vkLink = bootstrapString(appBootstrap, 'vkLink')
const discordLink = bootstrapString(appBootstrap, 'discordLink')
const downloading = ref(false)
const downloadError = ref('')
const windowsIcon = themeAsset(appBootstrap, 'icons/svg/windows.svg')

function downloadFile(url: string, filename: string): void {
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.append(anchor)
  anchor.click()
  anchor.remove()
}

function downloadBootstrapper(): void {
  downloading.value = true
  downloadError.value = ''

  try {
    downloadFile('/api/bootstrap/download.php?platform=windows-x86_64', 'FoxesCraft.exe')
  } catch (error) {
    console.error('[FoxesCraft] Bootstrapper download failed', error)
    downloadError.value = 'Не удалось начать загрузку. Попробуйте ещё раз позже.'
  } finally {
    downloading.value = false
  }
}

function openExternal(url: string): void {
  if (url) window.open(url, '_blank', 'noopener,noreferrer')
}
</script>
<template><StartGamePage :is-guest="isGuest" :login="login" :vk-link="vkLink" :discord-link="discordLink" :downloading="downloading" :download-error="downloadError" :windows-icon="windowsIcon" @navigate="router.push({ name: $event })" @download="downloadBootstrapper" @external="openExternal" /></template>
