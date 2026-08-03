<script setup lang="ts">
import { t } from '@/i18n'

import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import StartGamePage from '@theme/userOptions/pages/StartGame.vue'
import { appBootstrap } from '@/app/context'
import { loadStaticPages, type StaticPageDefinition } from '@/content/contentData'
import { bootstrapString, themeAsset } from '@/domain/bootstrap'

const props = withDefaults(defineProps<{ pageId?: string }>(), { pageId: 'start' })
const router = useRouter()
const isGuest = bootstrapString(appBootstrap, 'groupTag', 'guest') === 'guest'
const login = bootstrapString(appBootstrap, 'login')
const vkLink = bootstrapString(appBootstrap, 'vkLink')
const discordLink = bootstrapString(appBootstrap, 'discordLink')
const downloading = ref(false)
const downloadError = ref('')
const windowsIcon = themeAsset(appBootstrap, 'icons/svg/windows.svg')
const page = ref<StaticPageDefinition | null>(null)
const loading = ref(true)
const error = ref(false)

async function loadPage(): Promise<void> {
  loading.value = true
  error.value = false
  try {
    page.value = (await loadStaticPages())[props.pageId] ?? null
    error.value = !page.value
    if (page.value?.title) {
      const siteTitle = appBootstrap.site.title || 'FoxesCraft'
      document.title = `${page.value.title} — ${siteTitle}`
    }
  } catch (requestError) {
    console.error('[FoxesCraft] Start page content failed', requestError)
    page.value = null
    error.value = true
  } finally {
    loading.value = false
  }
}

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
  } catch (downloadFailure) {
    console.error('[FoxesCraft] Bootstrapper download failed', downloadFailure)
    downloadError.value = t('modules.gamescanner.startgameview.001')
  } finally {
    downloading.value = false
  }
}

function openExternal(url: string): void {
  if (url) window.open(url, '_blank', 'noopener,noreferrer')
}

onMounted(() => void loadPage())
watch(() => props.pageId, () => void loadPage())
</script>

<template>
  <StartGamePage
    :page="page"
    :loading="loading"
    :error="error"
    :is-guest="isGuest"
    :login="login"
    :vk-link="vkLink"
    :discord-link="discordLink"
    :downloading="downloading"
    :download-error="downloadError"
    :windows-icon="windowsIcon"
    @navigate="router.push({ name: $event })"
    @download="downloadBootstrapper"
    @external="openExternal"
  />
</template>
