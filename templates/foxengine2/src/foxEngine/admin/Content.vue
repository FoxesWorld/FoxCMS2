<script setup lang="ts">
import { t } from '@/i18n'

import { computed, defineAsyncComponent, ref, watch } from 'vue'
import type { BadgeDefinition, StaticPageDefinition } from '@engine/content/contentData'
import type { RuntimePageTemplatesDocument } from '@engine/runtime/pageTemplates'
import type {
  BadgeCatalogRow, BadgePageDraft, ProjectPageDraft, SystemPageDraft,
} from '@modules/AdminPanel/client/useAdminPanel'
import StaticPage from '@theme/userOptions/content/StaticPage.vue'
import BadgePage from '@theme/userOptions/pages/badges/Badge.vue'

const CodeEditor = defineAsyncComponent(() => import('@theme/foxEngine/editor/CodeEditor.vue'))

const props = defineProps<{
  projectPages: ProjectPageDraft[]
  pageTemplates: RuntimePageTemplatesDocument | null
  pageTemplatesStorageReady: boolean
  systemPages: SystemPageDraft[]
  badgePages: BadgePageDraft[]
  badges: BadgeCatalogRow[]
  loading: boolean
}>()

const emit = defineEmits<{
  saveProjectPages: []
  savePageTemplate: [templateId: string, source: string]
  reloadPageTemplates: []
  saveBadgePage: [page: BadgePageDraft]
  deleteBadgePage: [page: BadgePageDraft]
}>()

const mode = ref<'project' | 'templates' | 'system' | 'badges'>('project')
const projectWorkspaceTab = ref<'editor' | 'preview'>('editor')
const badgeWorkspaceTab = ref<'editor' | 'preview'>('editor')
const selectedProjectId = ref('')
const selectedPageTemplateId = ref('')
const selectedSystemPageId = ref('')
const selectedBadgeName = ref('')

const selectedProject = computed(() => props.projectPages.find((page) => page.id === selectedProjectId.value) ?? null)
const selectedPageTemplate = computed(() => props.pageTemplates?.templates.find((entry) => entry.id === selectedPageTemplateId.value) ?? null)
const pageTemplateSource = computed({
  get: () => selectedPageTemplate.value?.source ?? '',
  set: (value: string) => { if (selectedPageTemplate.value) selectedPageTemplate.value.source = value },
})
const selectedSystemPage = computed(() => props.systemPages.find((page) => page.id === selectedSystemPageId.value) ?? null)
const selectedBadge = computed(() => props.badges.find((badge) => badge.badgeName === selectedBadgeName.value) ?? null)
const selectedBadgePage = computed(() => {
  const badge = selectedBadge.value
  return badge ? props.badgePages.find((page) => page.slug === badge.pageSlug) ?? null : null
})

const forbiddenPreviewElements = 'script,style,iframe,object,embed,form,input,button,textarea,select,option,link,meta,base,svg,math'
const previewUrlAttributes = new Set(['href', 'src', 'xlink:href', 'formaction'])

function isUnsafePreviewUrl(value: string): boolean {
  const normalized = value.trim().replace(/[\u0000-\u0020]+/g, '')
  return /^(?:javascript|vbscript|data:text\/html)/i.test(normalized)
}

function sanitizePreviewHtml(html: string): string {
  const parsed = new DOMParser().parseFromString(html, 'text/html')
  parsed.body.querySelectorAll(forbiddenPreviewElements).forEach((element) => element.remove())
  parsed.body.querySelectorAll('*').forEach((element) => {
    for (const attribute of [...element.attributes]) {
      const name = attribute.name.toLowerCase()
      if (name.startsWith('on') || name === 'style' || name === 'srcdoc'
        || (previewUrlAttributes.has(name) && isUnsafePreviewUrl(attribute.value))) {
        element.removeAttribute(attribute.name)
      }
    }
  })
  return parsed.body.innerHTML
}

function hydrateBadgePreviewHtml(page: BadgePageDraft, badge: BadgeCatalogRow): string {
  const parsed = new DOMParser().parseFromString(sanitizePreviewHtml(page.html), 'text/html')
  parsed.querySelectorAll('[data-badge-title]').forEach((element) => { element.textContent = badge.badgeName })
  parsed.querySelectorAll('[data-badge-description]').forEach((element) => { element.textContent = badge.description })
  parsed.querySelectorAll<HTMLImageElement>('[data-badge-image]').forEach((element) => {
    if (badge.img && !isUnsafePreviewUrl(badge.img)) {
      element.src = badge.img
      element.alt = badge.badgeName
      element.hidden = false
    } else {
      element.removeAttribute('src')
      element.hidden = true
    }
  })
  return sanitizePreviewHtml(parsed.body.innerHTML)
}

const projectPreviewPage = computed<StaticPageDefinition | null>(() => {
  const page = selectedProject.value
  return page ? { id: page.id, title: page.title, html: sanitizePreviewHtml(page.html) } : null
})

const badgePreviewPage = computed<BadgeDefinition | null>(() => {
  const page = selectedBadgePage.value
  const badge = selectedBadge.value
  if (!page || !badge) return null
  return {
    id: page.slug,
    databaseId: badge.id,
    badgeName: badge.badgeName,
    title: badge.badgeName,
    description: badge.description,
    image: badge.img || null,
    html: hydrateBadgePreviewHtml(page, badge),
    pageConfigured: true,
  }
})

function preventPreviewNavigation(event: Event): void {
  const target = event.target instanceof Element ? event.target : null
  if (target?.closest('a')) event.preventDefault()
}

watch(() => props.projectPages, (pages) => {
  if (!pages.some((page) => page.id === selectedProjectId.value)) selectedProjectId.value = pages[0]?.id ?? ''
}, { immediate: true, deep: true })

watch(() => props.pageTemplates?.templates ?? [], (templates) => {
  if (!templates.some((entry) => entry.id === selectedPageTemplateId.value)) selectedPageTemplateId.value = templates[0]?.id ?? ''
}, { immediate: true, deep: true })

watch(() => props.systemPages, (pages) => {
  if (!pages.some((page) => page.id === selectedSystemPageId.value)) selectedSystemPageId.value = pages[0]?.id ?? ''
}, { immediate: true, deep: true })

watch(() => props.badges, (badges) => {
  if (!badges.some((badge) => badge.badgeName === selectedBadgeName.value)) selectedBadgeName.value = badges[0]?.badgeName ?? ''
}, { immediate: true, deep: true })



watch(selectedProjectId, () => { projectWorkspaceTab.value = 'editor' })
watch(selectedPageTemplateId, () => { pageTemplateSource.value = selectedPageTemplate.value?.source ?? '' })
watch(selectedBadgeName, () => { badgeWorkspaceTab.value = 'editor' })

function escapeHtml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}

function badgeTemplate(badge: BadgeCatalogRow, slug: string): string {
  return t('theme.foxengine.admin.content.045', [escapeHtml(badge.badgeName), slug])
}

function createBadgePage(): void {
  const badge = selectedBadge.value
  if (!badge || selectedBadgePage.value) return
  props.badgePages.push({
    badgeName: badge.badgeName,
    slug: badge.pageSlug,
    html: badgeTemplate(badge, badge.pageSlug),
  })
  badgeWorkspaceTab.value = 'editor'
}

function deleteBadgePage(): void {
  const page = selectedBadgePage.value
  if (!page || !window.confirm(t('theme.foxengine.admin.content.046', [page.slug]))) return
  emit('deleteBadgePage', page)
}

function saveSelectedPageTemplate(): void {
  const template = selectedPageTemplate.value
  if (!template?.source?.trim()) return
  emit('savePageTemplate', template.id, template.source)
}

</script>

<template>
  <section class="admin-content-editor">
    <header class="admin-content-editor__header">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.content.001') }}</span>
        <h2>{{ mode === 'project' ? t('theme.foxengine.admin.content.002') : mode === 'templates' ? t('theme.foxengine.admin.runtimeoptions.002') : mode === 'system' ? t('theme.foxengine.admin.content.047') : t('theme.foxengine.admin.content.003') }}</h2>
        <p v-if="mode === 'project'">{{ t('theme.foxengine.admin.content.004') }}</p>
        <p v-else-if="mode === 'templates'">{{ t('theme.foxengine.admin.runtimeoptions.003') }}</p>
        <p v-else-if="mode === 'system'">{{ t('theme.foxengine.admin.content.048') }}</p>
        <p v-else>{{ t('theme.foxengine.admin.content.005') }}</p>
      </div>
      <div class="admin-content-editor__modes">
        <button type="button" :class="{ active: mode === 'project' }" @click="mode = 'project'">
          <i class="fa-solid fa-newspaper" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.002') }}</span>
        </button>
        <button type="button" :class="{ active: mode === 'templates' }" @click="mode = 'templates'">
          <i class="fa-solid fa-file-code" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.runtimeoptions.002') }}</span>
        </button>
        <button type="button" :class="{ active: mode === 'system' }" @click="mode = 'system'">
          <i class="fa-solid fa-window-maximize" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.047') }}</span>
        </button>
        <button type="button" :class="{ active: mode === 'badges' }" @click="mode = 'badges'">
          <i class="fa-solid fa-award" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.003') }}</span>
        </button>
      </div>
    </header>

    <div v-if="mode === 'project'" class="admin-content-editor__workspace">
      <aside class="admin-content-editor__list">
        <button
          v-for="page in projectPages"
          :key="page.id"
          type="button"
          :class="{ active: selectedProjectId === page.id }"
          @click="selectedProjectId = page.id"
        >
          <i class="fa-solid fa-newspaper" aria-hidden="true" />
          <span><strong>{{ page.title }}</strong><small>{{ page.id }}</small></span>
        </button>
      </aside>

      <form v-if="selectedProject" class="admin-badge-html-editor admin-project-html-editor" @submit.prevent="emit('saveProjectPages')">
        <header class="admin-content-form__title">
          <div><span class="eyebrow">{{ t('theme.foxengine.admin.content.006') }}</span><h3>{{ selectedProject.title }}</h3><p><code>pages/content/{{ selectedProject.id }}.html</code></p></div>
          <a class="button button--ghost" :href="`/#/${selectedProject.id}`" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.008') }}</span></a>
        </header>

        <div class="admin-content-form__grid">
          <label><span>{{ t('theme.foxengine.admin.content.009') }}</span><input :value="selectedProject.id" type="text" readonly></label>
          <label><span>{{ t('theme.foxengine.admin.content.010') }}</span><input :value="`pages/content/${selectedProject.id}.html`" type="text" readonly></label>
        </div>

        <div class="admin-html-workbench__tabs" role="tablist" :aria-label="t('theme.foxengine.admin.content.012')">
          <button type="button" role="tab" :aria-selected="projectWorkspaceTab === 'editor'" :class="{ active: projectWorkspaceTab === 'editor' }" @click="projectWorkspaceTab = 'editor'">
            <i class="fa-solid fa-code" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.013') }}</span>
          </button>
          <button type="button" role="tab" :aria-selected="projectWorkspaceTab === 'preview'" :class="{ active: projectWorkspaceTab === 'preview' }" @click="projectWorkspaceTab = 'preview'">
            <i class="fa-solid fa-eye" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.014') }}</span>
          </button>
        </div>

        <div v-if="projectWorkspaceTab === 'editor'" class="admin-badge-html-editor__source" role="tabpanel">
          <span>{{ t('theme.foxengine.admin.content.015') }}</span>
          <CodeEditor
            :key="`project-${selectedProject.id}`"
            v-model="selectedProject.html"
            language="html"
            :aria-label="t('theme.foxengine.admin.content.017', [selectedProject.id])"
            min-height="620px"
          />
          <small>{{ t('theme.foxengine.admin.content.018') }} <code>{{ t('theme.foxengine.admin.content.019') }}</code> {{ t('theme.foxengine.admin.content.020') }} <code>&lt;h1&gt;</code>{{ t('theme.foxengine.admin.content.021') }}</small>
        </div>

        <section v-else class="admin-html-preview" role="tabpanel" @click.capture="preventPreviewNavigation">
          <header class="admin-html-preview__header">
            <div><strong>{{ t('theme.foxengine.admin.content.022') }}</strong><small>{{ t('theme.foxengine.admin.content.023') }}</small></div>
            <span class="admin-html-preview__status"><i class="fa-solid fa-bolt" aria-hidden="true" /> {{ t('theme.foxengine.admin.content.024') }}</span>
          </header>
          <div class="admin-html-preview__stage admin-html-preview__stage--project">
            <StaticPage v-if="projectPreviewPage" :page="projectPreviewPage" />
          </div>
        </section>

        <footer class="admin-content-form__footer">
          <button class="button button--primary" type="submit" :disabled="loading"><i class="fa-solid fa-floppy-disk" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.025') }} {{ selectedProject.id }}.html</span></button>
        </footer>
      </form>
    </div>


    <div v-else-if="mode === 'templates'" class="admin-content-editor__workspace">
      <aside class="admin-content-editor__list">
        <button
          v-for="entry in pageTemplates?.templates ?? []"
          :key="entry.id"
          type="button"
          :class="{ active: selectedPageTemplateId === entry.id }"
          @click="selectedPageTemplateId = entry.id"
        >
          <i class="fa-solid fa-file-code" aria-hidden="true" />
          <span><strong>{{ entry.file }}</strong><small>{{ entry.id }} · {{ t('theme.foxengine.admin.runtimeoptions.012') }} {{ entry.revision }}</small></span>
        </button>
      </aside>

      <form v-if="selectedPageTemplate" class="admin-badge-html-editor admin-project-html-editor" @submit.prevent="saveSelectedPageTemplate">
        <header class="admin-content-form__title">
          <div>
            <span class="eyebrow">{{ t('theme.foxengine.admin.runtimeoptions.007') }}</span>
            <h3>{{ selectedPageTemplate.file }}</h3>
            <p><code>pages/templates/{{ selectedPageTemplate.file }}</code> · {{ t('theme.foxengine.admin.runtimeoptions.012') }} {{ selectedPageTemplate.revision }}</p>
          </div>
          <span class="admin-html-preview__status" :class="{ ready: pageTemplatesStorageReady }">
            <i class="fa-solid" :class="pageTemplatesStorageReady ? 'fa-circle-check' : 'fa-triangle-exclamation'" aria-hidden="true" />
            {{ pageTemplatesStorageReady ? t('theme.foxengine.admin.runtimeoptions.004') : t('theme.foxengine.admin.runtimeoptions.005') }}
          </span>
        </header>

        <div class="admin-content-form__grid">
          <label><span>{{ t('theme.foxengine.admin.content.009') }}</span><input :value="selectedPageTemplate.id" type="text" readonly></label>
          <label><span>{{ t('theme.foxengine.admin.content.010') }}</span><input :value="`pages/templates/${selectedPageTemplate.file}`" type="text" readonly></label>
        </div>

        <div class="admin-badge-html-editor__source">
          <span>{{ t('theme.foxengine.admin.content.015') }}</span>
          <CodeEditor
            :key="`page-template-${selectedPageTemplate.id}-${selectedPageTemplate.revision}`"
            v-model="pageTemplateSource"
            language="html"
            :aria-label="selectedPageTemplate.file"
            min-height="680px"
          />
          <small>{{ t('theme.foxengine.admin.runtimeoptions.035') }}</small>
        </div>

        <footer class="admin-content-form__footer">
          <button class="button button--ghost" type="button" :disabled="loading" @click="emit('reloadPageTemplates')">
            <i class="fa-solid fa-rotate" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.runtimeoptions.036') }}</span>
          </button>
          <button class="button button--primary" type="submit" :disabled="loading || !pageTemplateSource.trim()">
            <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" aria-hidden="true" />
            <span>{{ loading ? t('theme.foxengine.admin.runtimeoptions.037') : t('theme.foxengine.admin.runtimeoptions.038') }}</span>
          </button>
        </footer>
      </form>
    </div>

    <div v-else-if="mode === 'system'" class="admin-content-editor__workspace">
      <aside class="admin-content-editor__list">
        <button
          v-for="page in systemPages"
          :key="page.id"
          type="button"
          :class="{ active: selectedSystemPageId === page.id }"
          @click="selectedSystemPageId = page.id"
        >
          <i class="fa-solid fa-window-maximize" aria-hidden="true" />
          <span><strong>{{ page.title }}</strong><small>{{ page.route }}</small></span>
        </button>
      </aside>

      <article v-if="selectedSystemPage" class="admin-content-form admin-system-page-detail">
        <header class="admin-content-form__title">
          <div>
            <span class="eyebrow">{{ t('theme.foxengine.admin.content.049') }}</span>
            <h3>{{ selectedSystemPage.title }}</h3>
            <p>{{ selectedSystemPage.description }}</p>
          </div>
          <RouterLink class="button button--ghost" :to="selectedSystemPage.route">
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true" />
            <span>{{ t('theme.foxengine.admin.content.057') }}</span>
          </RouterLink>
        </header>

        <div class="admin-system-page-detail__notice">
          <i class="fa-solid fa-code" aria-hidden="true" />
          <span><strong>{{ t('theme.foxengine.admin.content.058') }}</strong><small>{{ t('theme.foxengine.admin.content.050') }}</small></span>
        </div>

        <dl class="admin-system-page-detail__meta">
          <div><dt>{{ t('theme.foxengine.admin.content.051') }}</dt><dd><code>{{ selectedSystemPage.route }}</code></dd></div>
          <div><dt>{{ t('theme.foxengine.admin.content.052') }}</dt><dd><code>{{ selectedSystemPage.view }}</code></dd></div>
          <div><dt>{{ t('theme.foxengine.admin.content.053') }}</dt><dd><code>{{ selectedSystemPage.source }}</code></dd></div>
          <div><dt>{{ t('theme.foxengine.admin.content.054') }}</dt><dd><code>{{ selectedSystemPage.capability }}</code></dd></div>
          <div><dt>{{ t('theme.foxengine.admin.content.055') }}</dt><dd>{{ selectedSystemPage.editable ? t('theme.foxengine.admin.content.056') : t('theme.foxengine.admin.content.050') }}</dd></div>
        </dl>
      </article>

      <div v-else class="admin-content-empty-page">
        <i class="fa-solid fa-window-maximize" aria-hidden="true" />
        <strong>{{ t('theme.foxengine.admin.content.047') }}</strong>
        <p>{{ t('theme.foxengine.admin.content.048') }}</p>
      </div>
    </div>

    <div v-else-if="mode === 'badges'" class="admin-content-editor__workspace">
      <aside class="admin-content-editor__list admin-content-editor__list--badges">
        <button
          v-for="badge in badges"
          :key="badge.id"
          type="button"
          :class="{ active: selectedBadgeName === badge.badgeName }"
          @click="selectedBadgeName = badge.badgeName"
        >
          <img v-if="badge.img" :src="badge.img" alt="">
          <i v-else class="fa-solid fa-award" aria-hidden="true" />
          <span><strong>{{ badge.badgeName }}</strong><small>{{ badge.pageConfigured ? t('theme.foxengine.admin.content.026', [badge.pageSlug]) : t('theme.foxengine.admin.content.027', [badge.pageSlug]) }}</small></span>
        </button>
      </aside>

      <div v-if="selectedBadge" class="admin-content-form">
        <header class="admin-content-form__title">
          <div><span class="eyebrow">{{ t('theme.foxengine.admin.content.028') }}</span><h3>{{ selectedBadge.badgeName }}</h3><p>{{ selectedBadge.description }}</p></div>
          <img v-if="selectedBadge.img" class="admin-content-form__badge-image" :src="selectedBadge.img" alt="">
        </header>

        <div v-if="!selectedBadgePage" class="admin-content-empty-page">
          <i class="fa-solid fa-plus" aria-hidden="true" />
          <strong>{{ t('theme.foxengine.admin.content.029') }}</strong>
          <p>{{ t('theme.foxengine.admin.content.030') }}</p>
          <button class="button button--primary" type="button" @click="createBadgePage"><i class="fa-solid fa-plus" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.031') }}</span></button>
        </div>

        <form v-else class="admin-badge-html-editor" @submit.prevent="emit('saveBadgePage', selectedBadgePage)">
          <div class="admin-content-form__grid">
            <label><span>{{ t('theme.foxengine.admin.content.032') }}</span><input :value="selectedBadgePage.badgeName" type="text" readonly></label>
            <label><span>{{ t('theme.foxengine.admin.content.033') }}</span><input :value="`data/badges/${selectedBadgePage.slug}.html`" type="text" readonly><small><code>/#/badges/{{ selectedBadgePage.slug }}</code></small></label>
          </div>

          <div class="admin-badge-html-editor__actions">
            <a class="button button--ghost" :href="`/#/badges/${selectedBadgePage.slug}`" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.008') }}</span></a>
          </div>

          <div class="admin-html-workbench__tabs" role="tablist" :aria-label="t('theme.foxengine.admin.content.036')">
            <button type="button" role="tab" :aria-selected="badgeWorkspaceTab === 'editor'" :class="{ active: badgeWorkspaceTab === 'editor' }" @click="badgeWorkspaceTab = 'editor'">
              <i class="fa-solid fa-code" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.013') }}</span>
            </button>
            <button type="button" role="tab" :aria-selected="badgeWorkspaceTab === 'preview'" :class="{ active: badgeWorkspaceTab === 'preview' }" @click="badgeWorkspaceTab = 'preview'">
              <i class="fa-solid fa-eye" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.014') }}</span>
            </button>
          </div>

          <div v-if="badgeWorkspaceTab === 'editor'" class="admin-badge-html-editor__source" role="tabpanel">
            <span>{{ t('theme.foxengine.admin.content.037') }}</span>
            <CodeEditor
              :key="`badge-${selectedBadgePage.slug}`"
              v-model="selectedBadgePage.html"
              language="html"
              :aria-label="t('theme.foxengine.admin.content.039')"
              min-height="560px"
            />
            <small>{{ t('theme.foxengine.admin.content.040') }} <code>data-badge-page</code>, <code>data-badge-title</code>, <code>data-badge-description</code>, <code>data-badge-image</code> {{ t('theme.foxengine.admin.content.041') }} <code>data-badge-history</code>{{ t('theme.foxengine.admin.content.042') }}</small>
          </div>

          <section v-else class="admin-html-preview" role="tabpanel" @click.capture="preventPreviewNavigation">
            <header class="admin-html-preview__header">
              <div><strong>{{ t('theme.foxengine.admin.content.022') }}</strong><small>{{ t('theme.foxengine.admin.content.043') }}</small></div>
              <span class="admin-html-preview__status"><i class="fa-solid fa-bolt" aria-hidden="true" /> {{ t('theme.foxengine.admin.content.024') }}</span>
            </header>
            <div class="admin-html-preview__stage admin-html-preview__stage--badge">
              <BadgePage :loading="false" :error="false" :badge="badgePreviewPage" />
            </div>
          </section>

          <footer class="admin-content-form__footer admin-content-form__footer--split">
            <button class="button admin-content-delete-page" type="button" @click="deleteBadgePage"><i class="fa-solid fa-trash-can" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.044') }} {{ selectedBadgePage.slug }}.html</span></button>
            <button class="button button--primary" type="submit" :disabled="loading"><i class="fa-solid fa-floppy-disk" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.content.025') }} {{ selectedBadgePage.slug }}.html</span></button>
          </footer>
        </form>
      </div>
    </div>

  </section>
</template>
