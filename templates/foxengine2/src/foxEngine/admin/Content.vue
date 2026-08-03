<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import type { BadgeDefinition, StaticPageDefinition } from '@engine/content/contentData'
import type {
  BadgeCatalogRow, BadgePageDraft, ProjectPageDraft,
} from '@modules/AdminPanel/client/useAdminPanel'
import StaticPage from '@theme/userOptions/content/StaticPage.vue'
import BadgePage from '@theme/userOptions/pages/badges/Badge.vue'

const CodeEditor = defineAsyncComponent(() => import('@theme/foxEngine/editor/CodeEditor.vue'))

const props = defineProps<{
  projectPages: ProjectPageDraft[]
  badgePages: BadgePageDraft[]
  badges: BadgeCatalogRow[]
  loading: boolean
}>()

const emit = defineEmits<{
  saveProjectPages: []
  saveBadgePage: [page: BadgePageDraft]
  deleteBadgePage: [page: BadgePageDraft]
}>()

const mode = ref<'project' | 'badges'>('project')
const projectWorkspaceTab = ref<'editor' | 'preview'>('editor')
const badgeWorkspaceTab = ref<'editor' | 'preview'>('editor')
const selectedProjectId = ref('')
const selectedBadgeName = ref('')

const selectedProject = computed(() => props.projectPages.find((page) => page.id === selectedProjectId.value) ?? null)
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

watch(() => props.badges, (badges) => {
  if (!badges.some((badge) => badge.badgeName === selectedBadgeName.value)) selectedBadgeName.value = badges[0]?.badgeName ?? ''
}, { immediate: true, deep: true })



watch(selectedProjectId, () => { projectWorkspaceTab.value = 'editor' })
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
  return `<article class="content-surface badge-page badge-page--runtime" data-badge-page="1" data-badge-name="${escapeHtml(badge.badgeName)}" data-badge-slug="${slug}">
  <header class="badge-page__header">
    <div class="badge-page__visual">
      <img data-badge-image src="" alt="" loading="eager" decoding="async">
    </div>
    <div>
      <span class="eyebrow" data-badge-eyebrow>FoxesCraft badge</span>
      <h1 data-badge-title></h1>
      <p class="lead" data-badge-description></p>
    </div>
  </header>
  <section class="badge-story" data-badge-history>
    <h2>История бейджа</h2>
    <p>Добавьте полное описание, происхождение и историю этого бейджа.</p>
  </section>
</article>
`
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
  if (!page || !window.confirm(`Удалить HTML-файл data/badges/${page.slug}.html? Запись в БД останется.`)) return
  emit('deleteBadgePage', page)
}

</script>

<template>
  <section class="admin-content-editor">
    <header class="admin-content-editor__header">
      <div>
        <span class="eyebrow">Runtime content</span>
        <h2>{{ mode === 'project' ? 'Страницы проекта' : 'HTML-страницы бейджей' }}</h2>
        <p v-if="mode === 'project'">Страницы проекта хранятся отдельными HTML-файлами и редактируются через CodeMirror 5.</p>
        <p v-else>Полные представления бейджей хранятся отдельно от каталога и редактируются через CodeMirror 5.</p>
      </div>
      <div class="admin-content-editor__modes">
        <button type="button" :class="{ active: mode === 'project' }" @click="mode = 'project'">
          <i class="fa-solid fa-newspaper" aria-hidden="true" /><span>Страницы проекта</span>
        </button>
        <button type="button" :class="{ active: mode === 'badges' }" @click="mode = 'badges'">
          <i class="fa-solid fa-award" aria-hidden="true" /><span>HTML-страницы бейджей</span>
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
          <div><span class="eyebrow">HTML-страница проекта</span><h3>{{ selectedProject.title }}</h3><p><code>data/pages/{{ selectedProject.id }}.html</code></p></div>
          <a class="button button--ghost" :href="`/#/${selectedProject.id}`" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up" aria-hidden="true" /><span>Открыть страницу</span></a>
        </header>

        <div class="admin-content-form__grid">
          <label><span>ID маршрута</span><input :value="selectedProject.id" type="text" readonly></label>
          <label><span>HTML-файл</span><input :value="`data/pages/${selectedProject.id}.html`" type="text" readonly></label>
        </div>

        <div class="admin-html-workbench__tabs" role="tablist" aria-label="Режим страницы проекта">
          <button type="button" role="tab" :aria-selected="projectWorkspaceTab === 'editor'" :class="{ active: projectWorkspaceTab === 'editor' }" @click="projectWorkspaceTab = 'editor'">
            <i class="fa-solid fa-code" aria-hidden="true" /><span>Редактор</span>
          </button>
          <button type="button" role="tab" :aria-selected="projectWorkspaceTab === 'preview'" :class="{ active: projectWorkspaceTab === 'preview' }" @click="projectWorkspaceTab = 'preview'">
            <i class="fa-solid fa-eye" aria-hidden="true" /><span>Превью</span>
          </button>
        </div>

        <div v-if="projectWorkspaceTab === 'editor'" class="admin-badge-html-editor__source" role="tabpanel">
          <span>Полная HTML-разметка страницы проекта</span>
          <CodeEditor
            :key="`project-${selectedProject.id}`"
            v-model="selectedProject.html"
            language="html"
            :aria-label="`HTML-разметка страницы проекта ${selectedProject.id}`"
            min-height="620px"
          />
          <small>Обязательны один корневой <code>&lt;article data-project-page&gt;</code> и непустой <code>&lt;h1&gt;</code>. Скрипты, style, iframe, формы и inline-события запрещены серверной политикой.</small>
        </div>

        <section v-else class="admin-html-preview" role="tabpanel" @click.capture="preventPreviewNavigation">
          <header class="admin-html-preview__header">
            <div><strong>Прямое превью</strong><small>Используются реальные компоненты и CSS активной темы.</small></div>
            <span class="admin-html-preview__status"><i class="fa-solid fa-bolt" aria-hidden="true" /> Live</span>
          </header>
          <div class="admin-html-preview__stage admin-html-preview__stage--project">
            <StaticPage v-if="projectPreviewPage" :page="projectPreviewPage" />
          </div>
        </section>

        <footer class="admin-content-form__footer">
          <button class="button button--primary" type="submit" :disabled="loading"><i class="fa-solid fa-floppy-disk" aria-hidden="true" /><span>Сохранить {{ selectedProject.id }}.html</span></button>
        </footer>
      </form>
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
          <span><strong>{{ badge.badgeName }}</strong><small>{{ badge.pageConfigured ? `HTML: ${badge.pageSlug}.html` : `Ожидается: ${badge.pageSlug}.html` }}</small></span>
        </button>
      </aside>

      <div v-if="selectedBadge" class="admin-content-form">
        <header class="admin-content-form__title">
          <div><span class="eyebrow">Данные из MySQL</span><h3>{{ selectedBadge.badgeName }}</h3><p>{{ selectedBadge.description }}</p></div>
          <img v-if="selectedBadge.img" class="admin-content-form__badge-image" :src="selectedBadge.img" alt="">
        </header>

        <div v-if="!selectedBadgePage" class="admin-content-empty-page">
          <i class="fa-solid fa-plus" aria-hidden="true" />
          <strong>Полная HTML-страница ещё не создана</strong>
          <p>Название, краткое описание и изображение уже находятся в badgesList. Создайте отдельное полное представление с историей бейджа.</p>
          <button class="button button--primary" type="button" @click="createBadgePage"><i class="fa-solid fa-plus" aria-hidden="true" /><span>Создать HTML-страницу</span></button>
        </div>

        <form v-else class="admin-badge-html-editor" @submit.prevent="emit('saveBadgePage', selectedBadgePage)">
          <div class="admin-content-form__grid">
            <label><span>Привязка к badgesList.badgeName</span><input :value="selectedBadgePage.badgeName" type="text" readonly></label>
            <label><span>Файл и маршрут</span><input :value="`data/badges/${selectedBadgePage.slug}.html`" type="text" readonly><small><code>/#/badges/{{ selectedBadgePage.slug }}</code></small></label>
          </div>

          <div class="admin-badge-html-editor__actions">
            <a class="button button--ghost" :href="`/#/badges/${selectedBadgePage.slug}`" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up" aria-hidden="true" /><span>Открыть страницу</span></a>
          </div>

          <div class="admin-html-workbench__tabs" role="tablist" aria-label="Режим страницы бейджа">
            <button type="button" role="tab" :aria-selected="badgeWorkspaceTab === 'editor'" :class="{ active: badgeWorkspaceTab === 'editor' }" @click="badgeWorkspaceTab = 'editor'">
              <i class="fa-solid fa-code" aria-hidden="true" /><span>Редактор</span>
            </button>
            <button type="button" role="tab" :aria-selected="badgeWorkspaceTab === 'preview'" :class="{ active: badgeWorkspaceTab === 'preview' }" @click="badgeWorkspaceTab = 'preview'">
              <i class="fa-solid fa-eye" aria-hidden="true" /><span>Превью</span>
            </button>
          </div>

          <div v-if="badgeWorkspaceTab === 'editor'" class="admin-badge-html-editor__source" role="tabpanel">
            <span>Полная HTML-разметка страницы</span>
            <CodeEditor
              :key="`badge-${selectedBadgePage.slug}`"
              v-model="selectedBadgePage.html"
              language="html"
              aria-label="HTML-разметка страницы бейджа"
              min-height="560px"
            />
            <small>Обязательны: <code>data-badge-page</code>, <code>data-badge-title</code>, <code>data-badge-description</code>, <code>data-badge-image</code> и <code>data-badge-history</code>. Данные из БД подставляются сервером. Скрипты, style, iframe, формы и inline-события запрещены.</small>
          </div>

          <section v-else class="admin-html-preview" role="tabpanel" @click.capture="preventPreviewNavigation">
            <header class="admin-html-preview__header">
              <div><strong>Прямое превью</strong><small>Используются реальные компоненты, данные бейджа и CSS активной темы.</small></div>
              <span class="admin-html-preview__status"><i class="fa-solid fa-bolt" aria-hidden="true" /> Live</span>
            </header>
            <div class="admin-html-preview__stage admin-html-preview__stage--badge">
              <BadgePage :loading="false" :error="false" :badge="badgePreviewPage" />
            </div>
          </section>

          <footer class="admin-content-form__footer admin-content-form__footer--split">
            <button class="button admin-content-delete-page" type="button" @click="deleteBadgePage"><i class="fa-solid fa-trash-can" aria-hidden="true" /><span>Удалить {{ selectedBadgePage.slug }}.html</span></button>
            <button class="button button--primary" type="submit" :disabled="loading"><i class="fa-solid fa-floppy-disk" aria-hidden="true" /><span>Сохранить {{ selectedBadgePage.slug }}.html</span></button>
          </footer>
        </form>
      </div>
    </div>

  </section>
</template>
