<script setup lang="ts">
import { defineComponent, markRaw, shallowRef, watch, type Component, type RenderFunction } from 'vue'
import { t } from '@/i18n'

interface RuntimeTemplateModule {
  render?: RenderFunction
  templateId?: string
}

const props = defineProps<{
  templateId: string
  moduleUrl: string
  revision: number
  context: Record<string, unknown>
  components?: Record<string, Component>
}>()

const compiled = shallowRef<Component | null>(null)
const loadError = shallowRef('')
let loadGeneration = 0

watch(
  () => [props.templateId, props.moduleUrl, props.revision, props.components] as const,
  ([templateId, moduleUrl]) => {
    const generation = ++loadGeneration
    compiled.value = null
    loadError.value = ''
    if (!templateId || !moduleUrl) return

    void import(/* @vite-ignore */ moduleUrl)
      .then((loaded: RuntimeTemplateModule) => {
        if (generation !== loadGeneration) return
        if (loaded.templateId !== templateId || typeof loaded.render !== 'function') {
          throw new Error(`Runtime TPL module integrity failure: ${templateId}`)
        }
        compiled.value = markRaw(defineComponent({
          name: `FoxRuntimeTpl_${templateId.replaceAll('-', '_')}`,
          components: props.components ?? {},
          setup: () => props.context,
          render: loaded.render,
        }))
      })
      .catch((reason: unknown) => {
        if (generation !== loadGeneration) return
        const message = reason instanceof Error ? reason.message : String(reason)
        loadError.value = message
        console.error(`[FoxesCraft] Runtime TPL module failed: ${templateId}`, reason)
      })
  },
  { immediate: true },
)
</script>

<template>
  <div v-if="loadError" class="system-message system-message--error" role="alert">
    <strong>{{ t('engine.runtime.tpl.001') }}</strong>
    <p>{{ loadError }}</p>
  </div>
  <component :is="compiled" v-else-if="compiled" />
  <div v-else class="runtime-panel-skeleton" aria-hidden="true"><span /><span /><span /></div>
</template>
