<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import type { NavigationDefinition } from '@engine/domain/bootstrap'
import Logo from '@theme/Logo.vue'
import UserBlock from '@theme/UserBlock.vue'

interface Props {
  displayName: string
  isGuest: boolean
  siteTitle: string
  siteStatus: string
  logoUrl: string
  primaryItems: NavigationDefinition[]
  guestItems: NavigationDefinition[]
  accountItems: NavigationDefinition[]
  colorTheme: 'light' | 'dark'
}

defineProps<Props>()
const emit = defineEmits<{
  activate: [item: NavigationDefinition]
  toggleTheme: []
}>()
const route = useRoute()

const mobileOpen = ref(false)
const isMobile = ref(false)
const menuToggle = ref<HTMLButtonElement | null>(null)
const mobileNavigation = ref<HTMLElement | null>(null)
const desktopMedia = '(min-width: 981px)'
let desktopQuery: MediaQueryList | null = null
let rootOverflow = ''
let bodyOverflow = ''
let bodyOverscroll = ''

function focusableNavigationItems(): HTMLElement[] {
  const navigation = mobileNavigation.value
  if (!navigation) return []

  return Array.from(
    navigation.querySelectorAll<HTMLElement>(
      'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => !element.hasAttribute('hidden'))
}

function setPageScrollLocked(locked: boolean): void {
  if (locked) {
    rootOverflow = document.documentElement.style.overflow
    bodyOverflow = document.body.style.overflow
    bodyOverscroll = document.body.style.overscrollBehavior
    document.documentElement.style.overflow = 'hidden'
    document.body.style.overflow = 'hidden'
    document.body.style.overscrollBehavior = 'none'
    return
  }

  document.documentElement.style.overflow = rootOverflow
  document.body.style.overflow = bodyOverflow
  document.body.style.overscrollBehavior = bodyOverscroll
}

function openMenu(): void {
  if (isMobile.value) mobileOpen.value = true
}

function closeMenu(restoreFocus = true): void {
  if (!mobileOpen.value) return

  mobileOpen.value = false
  if (restoreFocus) void nextTick(() => menuToggle.value?.focus())
}

function toggleMenu(): void {
  mobileOpen.value ? closeMenu() : openMenu()
}

function activate(item: NavigationDefinition): void {
  closeMenu(false)
  emit('activate', item)
}

function isCurrent(item: NavigationDefinition): boolean {
  return Boolean(item.route && route.name === item.route)
}

function handleDocumentKeydown(event: KeyboardEvent): void {
  if (!mobileOpen.value || !isMobile.value) return

  if (event.key === 'Escape') {
    event.preventDefault()
    closeMenu()
    return
  }

  if (event.key !== 'Tab') return

  const items = focusableNavigationItems()
  if (items.length === 0) {
    event.preventDefault()
    mobileNavigation.value?.focus()
    return
  }

  const first = items[0]
  const last = items[items.length - 1]
  const active = document.activeElement

  if (event.shiftKey && active === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

function handleDesktopChange(event: MediaQueryListEvent): void {
  isMobile.value = !event.matches
  if (event.matches) closeMenu(false)
}

watch(mobileOpen, async (isOpen) => {
  setPageScrollLocked(isOpen && isMobile.value)
  if (!isOpen) return

  await nextTick()
  if (mobileOpen.value) focusableNavigationItems()[0]?.focus()
})

onMounted(() => {
  desktopQuery = window.matchMedia(desktopMedia)
  isMobile.value = !desktopQuery.matches
  desktopQuery.addEventListener('change', handleDesktopChange)
  document.addEventListener('keydown', handleDocumentKeydown)
})

onBeforeUnmount(() => {
  desktopQuery?.removeEventListener('change', handleDesktopChange)
  document.removeEventListener('keydown', handleDocumentKeydown)
  setPageScrollLocked(false)
})
</script>

<template>
  <header
    id="header"
    class="site-header legacy-header"
    :class="{ 'site-header--menu-open': mobileOpen && isMobile }"
  >
    <div class="site-header__inner page-width">
      <Logo
        :site-title="siteTitle"
        :site-status="siteStatus"
        :logo-url="logoUrl"
        @activate="primaryItems[0] && activate(primaryItems[0])"
      />

      <nav
        id="primary-navigation"
        ref="mobileNavigation"
        class="primary-nav legacy-nav"
        :class="{ 'primary-nav--open': mobileOpen && isMobile }"
        :aria-hidden="isMobile && !mobileOpen ? 'true' : undefined"
        :inert="isMobile && !mobileOpen"
        aria-label="Основная навигация"
        tabindex="-1"
      >
        <div class="mobile-nav__heading" aria-hidden="true">
          <span>Навигация</span>
          <small>{{ siteTitle }}</small>
        </div>

        <button
          v-for="item in primaryItems"
          :key="`${item.owner}:${item.route}:${item.label}`"
          class="primary-nav__item"
          :class="{ 'is-current': isCurrent(item) }"
          type="button"
          :aria-current="isCurrent(item) ? 'page' : undefined"
          @click="activate(item)"
        >{{ item.label }}</button>

        <div class="mobile-account-actions">
          <span class="mobile-account-actions__label">Аккаунт</span>
          <button
            v-for="item in isGuest ? guestItems : accountItems"
            :key="`${item.intent}:${item.label}`"
            type="button"
            @click="activate(item)"
          >{{ item.label }}</button>
        </div>
      </nav>

      <div class="header-controls">
        <button
          class="theme-toggle"
          type="button"
          :aria-label="colorTheme === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'"
          :aria-pressed="colorTheme === 'dark'"
          :title="colorTheme === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'"
          @click="emit('toggleTheme')"
        >
          <span class="theme-toggle__track" aria-hidden="true">
            <span class="theme-toggle__thumb">
              <span class="theme-toggle__sun">☀</span>
              <span class="theme-toggle__moon">☾</span>
            </span>
          </span>
          <span class="theme-toggle__label">
            {{ colorTheme === 'dark' ? 'Тёмная' : 'Светлая' }}
          </span>
        </button>

        <UserBlock
          :display-name="displayName"
          :is-guest="isGuest"
          :guest-items="guestItems"
          :account-items="accountItems"
          @activate="activate"
        />

        <button
          ref="menuToggle"
          class="menu-toggle"
          :class="{ 'is-open': mobileOpen }"
          type="button"
          :aria-expanded="mobileOpen"
          aria-controls="primary-navigation"
          :aria-label="mobileOpen ? 'Закрыть меню' : 'Открыть меню'"
          @click="toggleMenu"
        >
          <span class="menu-toggle__line" />
          <span class="menu-toggle__line" />
          <span class="menu-toggle__line" />
        </button>
      </div>

      <div
        v-if="mobileOpen && isMobile"
        class="mobile-nav-backdrop"
        aria-hidden="true"
        @click="closeMenu()"
      />
    </div>
  </header>
</template>
