<script setup lang="ts">
import { t } from '@/i18n'

import { computed, markRaw, toRefs } from 'vue'
import type { StaticPageDefinition } from '@engine/content/contentData'
import RuntimeTpl from '@engine/runtime/RuntimeTpl.vue'
import { loadRuntimePageTemplates, runtimePageTemplate, runtimePageTemplatesState } from '@engine/runtime/pageTemplates'

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
    accountTitle.textContent = props.isGuest ? t('theme.useroptions.pages.startgame.003') : t('theme.useroptions.pages.startgame.004', [props.login])
  }
  if (accountDescription) {
    accountDescription.textContent = props.isGuest
      ? t('theme.useroptions.pages.startgame.005')
      : t('theme.useroptions.pages.startgame.006')
  }
  if (!props.isGuest) registerAction?.remove()

  if (windowsIcon) windowsIcon.src = props.windowsIcon
  if (downloadLabel) downloadLabel.textContent = props.downloading ? t('theme.useroptions.pages.startgame.007') : 'Windows x64'
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
const pageTemplate = runtimePageTemplate('start-game')
const runtimeTemplateComponents = markRaw({})
const runtimeTemplateContext: Record<string, unknown> = {
  t,
  ...toRefs(props),
  emit,
  hydratedHtml,
  handleAction,
}
void loadRuntimePageTemplates().catch((reason: unknown) => {
  console.error('[FoxesCraft] StartGame.tpl failed to load', reason)
})
</script>

<template>
  <div v-if="runtimePageTemplatesState.error" class="system-message system-message--error" role="alert">
    <strong>{{ t('engine.runtime.pagetemplates.003') }}</strong>
    <p>{{ runtimePageTemplatesState.error }}</p>
  </div>
  <RuntimeTpl
    v-else-if="pageTemplate"
    :template-id="pageTemplate.id"
    :module-url="pageTemplate.moduleUrl"
    :revision="pageTemplate.revision"
    :context="runtimeTemplateContext"
    :components="runtimeTemplateComponents"
  />
  <div v-else class="runtime-panel-skeleton" aria-hidden="true"><span /><span /><span /></div>
</template>
