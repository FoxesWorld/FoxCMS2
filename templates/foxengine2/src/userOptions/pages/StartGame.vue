<script setup lang="ts">
import { computed } from 'vue'
import type { StaticPageDefinition } from '@engine/content/contentData'

const props = defineProps<{
  page: StaticPageDefinition | null
  loading: boolean
  error: boolean
  isGuest: boolean
  login: string
  vkLink: string
  discordLink: string
  downloading: boolean
  downloadError: string
  windowsIcon: string
}>()

const emit = defineEmits<{
  navigate: [route: string]
  download: []
  external: [url: string]
}>()

const hydratedHtml = computed(() => {
  if (!props.page) return ''

  const document = new DOMParser().parseFromString(props.page.html, 'text/html')
  const accountTitle = document.querySelector('[data-start-account-title]')
  const accountDescription = document.querySelector('[data-start-account-description]')
  const registerAction = document.querySelector('[data-start-action="register"]')
  const downloadAction = document.querySelector('[data-start-action="download"]')
  const downloadLabel = document.querySelector('[data-start-download-label]')
  const downloadError = document.querySelector('[data-start-download-error]')
  const windowsIcon = document.querySelector<HTMLImageElement>('[data-start-windows-icon]')

  if (accountTitle) {
    accountTitle.textContent = props.isGuest ? 'Создайте аккаунт' : `Аккаунт ${props.login} готов`
  }
  if (accountDescription) {
    accountDescription.textContent = props.isGuest
      ? 'Регистрация связывает игровой профиль, прогресс и доступ к сервисам сообщества.'
      : 'Первый этап уже завершён. Можно загружать FoxesCraft.'
  }
  if (!props.isGuest) registerAction?.remove()

  if (windowsIcon) windowsIcon.src = props.windowsIcon
  if (downloadLabel) downloadLabel.textContent = props.downloading ? 'Подготовка…' : 'Windows x64'
  if (downloadAction) {
    downloadAction.setAttribute('aria-disabled', props.downloading ? 'true' : 'false')
    downloadAction.classList.toggle('is-disabled', props.downloading)
  }
  if (downloadError) {
    if (props.downloadError) downloadError.textContent = props.downloadError
    else downloadError.remove()
  }

  hydrateExternalAction(document, 'vk', props.vkLink)
  hydrateExternalAction(document, 'discord', props.discordLink)
  return document.body.innerHTML
})

function hydrateExternalAction(document: Document, action: string, url: string): void {
  const element = document.querySelector<HTMLAnchorElement>(`[data-start-action="${action}"]`)
  if (!element) return
  if (!url) {
    element.remove()
    return
  }
  element.href = url
  element.target = '_blank'
  element.rel = 'noopener noreferrer'
}

function handleAction(event: MouseEvent): void {
  const target = event.target instanceof Element
    ? event.target.closest<HTMLElement>('[data-start-action]')
    : null
  if (!target) return

  const action = target.dataset.startAction ?? ''
  if (!action) return
  event.preventDefault()

  if (action === 'register') {
    emit('navigate', 'register')
    return
  }
  if (action === 'download') {
    if (!props.downloading) emit('download')
    return
  }
  if (action === 'vk' && props.vkLink) {
    emit('external', props.vkLink)
    return
  }
  if (action === 'discord' && props.discordLink) emit('external', props.discordLink)
}
</script>

<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <div
    v-else-if="page"
    class="static-page-html start-page-runtime"
    @click.capture="handleAction"
    v-html="hydratedHtml"
  />
  <div v-else-if="error" class="system-message system-message--error">
    <strong>Страница запуска недоступна</strong>
    <p>Не удалось загрузить runtime-страницу <code>data/pages/start.html</code>.</p>
  </div>
</template>
