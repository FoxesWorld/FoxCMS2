<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { CSSProperties } from 'vue'
import { useRoute } from 'vue-router'
import Header from '@theme/Header.vue'
import Footer from '@theme/Footer.vue'
import Slider from '@theme/Slider.vue'
import RightBlock from '@theme/RightBlock.vue'
import CookiePopup from '@theme/CookiePopup.vue'
import ButtonUp from '@theme/ButtonUp.vue'
import ToastViewport from '@theme/ToastViewport.vue'
import { themeAsset } from '@engine/domain/bootstrap'
import { useEngineShell } from '@engine/shell/useEngineShell'
import { restoreQueuedToast } from '@engine/notifications/toasts'
import { getSeasonBackground, getSiteSeason } from '@theme/domain/season'

type ColorTheme = 'light' | 'dark'

const NewsFeed = defineAsyncComponent(() => import('@theme/news/NewsFeed.vue'))

const THEME_STORAGE_KEY = 'foxescraft.color-theme'
const shell = useEngineShell()
const route = useRoute()
const isHome = computed(() => route.name === 'home')
const isAdmin = computed(() => route.name === 'admin')
const activeSeason = getSiteSeason()
const seasonalStyle = {
  '--season-background-image': `url("${getSeasonBackground(shell.bootstrap, activeSeason)}")`,
} as CSSProperties
const logoUrl = themeAsset(shell.bootstrap, 'img/logo.png')

function readStoredTheme(): ColorTheme | null {
  try {
    const value = window.localStorage.getItem(THEME_STORAGE_KEY)
    return value === 'light' || value === 'dark' ? value : null
  } catch {
    return null
  }
}

const systemThemeQuery = window.matchMedia('(prefers-color-scheme: dark)')
const storedTheme = readStoredTheme()
const followsSystemTheme = ref(storedTheme === null)
const colorTheme = ref<ColorTheme>(storedTheme ?? (systemThemeQuery.matches ? 'dark' : 'light'))

function applyTheme(theme: ColorTheme): void {
  document.documentElement.dataset.theme = theme
  document.documentElement.style.colorScheme = theme
  document.querySelector<HTMLMetaElement>('#foxescraft-theme-color')?.setAttribute(
    'content',
    theme === 'dark' ? '#201b18' : '#d9d4ce',
  )
}

function persistTheme(theme: ColorTheme): void {
  try {
    window.localStorage.setItem(THEME_STORAGE_KEY, theme)
  } catch {
    // The theme remains active for the current session when storage is unavailable.
  }
}

function toggleTheme(): void {
  followsSystemTheme.value = false
  colorTheme.value = colorTheme.value === 'dark' ? 'light' : 'dark'
  persistTheme(colorTheme.value)
}

function handleSystemThemeChange(event: MediaQueryListEvent): void {
  if (followsSystemTheme.value) colorTheme.value = event.matches ? 'dark' : 'light'
}

applyTheme(colorTheme.value)
watch(colorTheme, applyTheme)

onMounted(() => {
  restoreQueuedToast()
  systemThemeQuery.addEventListener('change', handleSystemThemeChange)
})
onBeforeUnmount(() => systemThemeQuery.removeEventListener('change', handleSystemThemeChange))
</script>

<template>
  <div
    class="app-shell legacy-shell"
    :data-season="activeSeason"
    :data-theme="colorTheme"
    :style="seasonalStyle"
  >
    <a class="skip-link" href="#main-content">Перейти к содержимому</a>
    <Header
      :display-name="shell.displayName.value"
      :profile-photo="shell.profilePhoto.value"
      :is-guest="shell.isGuest.value"
      :site-title="shell.siteTitle"
      :site-status="shell.bootstrap.site.status"
      :logo-url="logoUrl"
      :primary-items="shell.primaryItems.value"
      :guest-items="shell.guestItems.value"
      :account-items="shell.accountItems.value"
      :color-theme="colorTheme"
      @activate="shell.activate"
      @toggle-theme="toggleTheme"
    />

    <main id="main-content" class="legacy-main">
      <div class="page-width legacy-main__inner" :class="{ 'legacy-main__inner--admin': isAdmin }">
        <Slider v-if="isHome" />
        <div class="content-layout" :class="{ 'content-layout--admin': isAdmin }">
          <section class="content-column">
            <RouterView />
            <NewsFeed v-if="isHome" />
          </section>
          <RightBlock v-if="!isAdmin" />
        </div>
      </div>
    </main>

    <Footer
      :site-title="shell.siteTitle"
      :service-version="shell.serviceVersion"
      :items="shell.footerItems.value"
      @activate="shell.activate"
    />
    <ToastViewport />
    <CookiePopup />
    <ButtonUp />
  </div>
</template>
