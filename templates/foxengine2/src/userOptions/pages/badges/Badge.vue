<script setup lang="ts">
import { t } from '@/i18n'
import { markRaw, toRefs } from 'vue'
import RuntimeTpl from '@engine/runtime/RuntimeTpl.vue'
import { loadRuntimePageTemplates, runtimePageTemplate, runtimePageTemplatesState } from '@engine/runtime/pageTemplates'

import type { BadgeDefinition } from '@engine/content/contentData'

const props = defineProps<{ loading: boolean; error: boolean; badge: BadgeDefinition | null }>()
const pageTemplate = runtimePageTemplate('badge')
const runtimeTemplateComponents = markRaw({})
const runtimeTemplateContext: Record<string, unknown> = { t, ...toRefs(props) }
void loadRuntimePageTemplates().catch((reason: unknown) => {
  console.error('[FoxesCraft] Badge.tpl failed to load', reason)
})
</script>

<template>
  <div v-if="runtimePageTemplatesState.error" class="system-message system-message--error" role="alert">
    <strong>{{ t('engine.runtime.pagetemplates.003') }}</strong>
    <p>{{ t('engine.runtime.pagetemplates.004') }}</p>
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
