<script setup lang="ts">
import { t } from '@/i18n'
import { computed, defineAsyncComponent, ref } from 'vue'
import type { RuntimeUserOptionsDocument, RuntimeUserOptionsTemplate } from '@engine/runtime/userOptions'

const CodeEditor = defineAsyncComponent(() => import('@theme/foxEngine/editor/CodeEditor.vue'))
const props = defineProps<{
  document: RuntimeUserOptionsDocument | null
  loading: boolean
  updatedAt: string
  storageReady: boolean
}>()
const emit = defineEmits<{
  save: [templateId: string, source: string]
  reload: []
}>()
type EditableRuntimeTemplate = RuntimeUserOptionsTemplate
const selectedId = ref<EditableRuntimeTemplate['id']>('profile-settings')
const templates = computed<EditableRuntimeTemplate[]>(() => [
  ...(props.document ? [props.document.templates.profileSettings, props.document.templates.adminPanel] : []),
])
const allStorageReady = computed(() => props.storageReady)
const selectedPath = computed(() => selected.value
  ? `templates/foxengine2/userOptions/${selected.value.file}`
  : '')
const selected = computed(() => templates.value.find((entry) => entry.id === selectedId.value) ?? templates.value[0] ?? null)
const sourceModel = computed({
  get: () => selected.value?.source ?? '',
  set: (value: string) => { if (selected.value) selected.value.source = value },
})
function save(): void {
  if (selected.value?.source) emit('save', selected.value.id, selected.value.source)
}
</script>

<template>
  <section class="runtime-options-admin">
    <header class="runtime-options-admin__hero">
      <div><span class="eyebrow">{{ t('theme.foxengine.admin.runtimeoptions.001') }}</span><h2>{{ t('theme.foxengine.admin.runtimeoptions.002') }}</h2><p>{{ t('theme.foxengine.admin.runtimeoptions.003') }}</p></div>
      <div class="runtime-options-admin__status" :class="{ ready: allStorageReady }"><i class="fa-solid" :class="allStorageReady ? 'fa-circle-check' : 'fa-triangle-exclamation'" /><span><strong>{{ allStorageReady ? t('theme.foxengine.admin.runtimeoptions.004') : t('theme.foxengine.admin.runtimeoptions.005') }}</strong><small>{{ updatedAt || t('theme.foxengine.admin.runtimeoptions.006') }}</small></span></div>
    </header>
    <div class="runtime-options-admin__notice"><i class="fa-solid fa-file-code" /><div><strong>{{ t('theme.foxengine.admin.runtimeoptions.007') }}</strong><p>{{ t('theme.foxengine.admin.runtimeoptions.008') }}</p></div></div>
    <div v-if="!document" class="runtime-panel-skeleton runtime-panel-skeleton--admin" aria-hidden="true"><span /><span /><span /><span /></div>
    <template v-else>
      <nav class="runtime-options-admin__modes" :aria-label="t('theme.foxengine.admin.runtimeoptions.013')">
        <button v-for="entry in templates" :key="entry.id" type="button" :class="{ active: selected?.id === entry.id }" @click="selectedId = entry.id"><i class="fa-solid" :class="entry.id === 'profile-settings' ? 'fa-user-gear' : 'fa-shield-halved'" /><span>{{ entry.file }}</span></button>
      </nav>
      <section v-if="selected" class="runtime-options-admin__section runtime-tpl-editor">
        <header><div><h3>{{ selected.file }}</h3><p><code>{{ selectedPath }}</code> · {{ t('theme.foxengine.admin.runtimeoptions.012') }} {{ selected.revision }}</p></div></header>
        <CodeEditor v-model="sourceModel" language="html" :aria-label="selected.file" min-height="680px" />
        <p class="runtime-tpl-editor__hint">{{ t('theme.foxengine.admin.runtimeoptions.035') }}</p>
      </section>
      <footer class="runtime-options-admin__footer"><button class="button button--ghost" type="button" :disabled="loading" @click="emit('reload')"><i class="fa-solid fa-rotate" />{{ t('theme.foxengine.admin.runtimeoptions.036') }}</button><button class="button button--primary" type="button" :disabled="loading || !selected?.source" @click="save"><i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" />{{ loading ? t('theme.foxengine.admin.runtimeoptions.037') : t('theme.foxengine.admin.runtimeoptions.038') }}</button></footer>
    </template>
  </section>
</template>
