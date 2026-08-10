<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { t } from '@/i18n'
import { hcaptchaRequired, hcaptchaSiteKey, type HCaptchaForm } from '@/security/hcaptcha'

type WidgetId = string | number
interface HCaptchaApi {
  render(container: HTMLElement, options: Record<string, unknown>): WidgetId
  reset(widgetId?: WidgetId): void
  remove?(widgetId: WidgetId): void
}

declare global {
  interface Window { hcaptcha?: HCaptchaApi }
}

const props = defineProps<{ form: HCaptchaForm; disabled?: boolean }>()
const emit = defineEmits<{ 'update:token': [token: string] }>()
const container = ref<HTMLElement | null>(null)
const error = ref('')
let widgetId: WidgetId | null = null
let loader: Promise<HCaptchaApi> | null = null

function sdk(): Promise<HCaptchaApi> {
  if (window.hcaptcha) return Promise.resolve(window.hcaptcha)
  if (loader) return loader
  loader = new Promise((resolve, reject) => {
    const existing = document.querySelector<HTMLScriptElement>('script[data-foxescraft-hcaptcha]')
    const complete = () => window.hcaptcha ? resolve(window.hcaptcha) : reject(new Error(t('engine.security.hcaptcha.002')))
    if (existing) {
      existing.addEventListener('load', complete, { once: true })
      existing.addEventListener('error', () => reject(new Error(t('engine.security.hcaptcha.002'))), { once: true })
      window.setTimeout(() => { if (window.hcaptcha) resolve(window.hcaptcha) }, 0)
      return
    }
    const script = document.createElement('script')
    script.src = 'https://js.hcaptcha.com/1/api.js?render=explicit'
    script.async = true
    script.defer = true
    script.dataset.foxescraftHcaptcha = '1'
    script.addEventListener('load', complete, { once: true })
    script.addEventListener('error', () => reject(new Error(t('engine.security.hcaptcha.002'))), { once: true })
    document.head.append(script)
  })
  return loader
}

async function renderWidget(): Promise<void> {
  if (!hcaptchaRequired(props.form) || !container.value || widgetId !== null) return
  try {
    const api = await sdk()
    await nextTick()
    if (!container.value || widgetId !== null) return
    widgetId = api.render(container.value, {
      sitekey: hcaptchaSiteKey(),
      theme: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
      callback: (token: string) => { error.value = ''; emit('update:token', token) },
      'expired-callback': () => emit('update:token', ''),
      'error-callback': () => { emit('update:token', ''); error.value = t('engine.security.hcaptcha.002') },
    })
  } catch (reason) {
    console.error('[FoxesCraft] hCaptcha initialization failed', reason)
    error.value = t('engine.security.hcaptcha.002')
  }
}

function reset(): void {
  emit('update:token', '')
  if (widgetId !== null && window.hcaptcha) window.hcaptcha.reset(widgetId)
}

defineExpose({ reset })

onMounted(() => void renderWidget())
watch(() => props.form, () => { reset(); void renderWidget() })
onBeforeUnmount(() => {
  emit('update:token', '')
  if (widgetId !== null && window.hcaptcha?.remove) window.hcaptcha.remove(widgetId)
  widgetId = null
})
</script>

<template>
  <div v-if="hcaptchaRequired(form)" class="hcaptcha-field" :aria-disabled="disabled || undefined">
    <div ref="container" class="hcaptcha-field__widget" />
    <small v-if="error" class="hcaptcha-field__error" role="alert">{{ error }}</small>
    <small v-else class="hcaptcha-field__hint">{{ t('engine.security.hcaptcha.001') }}</small>
  </div>
</template>
