<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import type { BadgeDefinition, StaticPageDefinition } from '@engine/content/contentData'
import type {
  BadgeCatalogRow, BadgeClaimKeyRow, BadgeClaimUsageMode, BadgePageDraft, IssuedBadgeClaimCode, ProjectPageDraft,
} from '@modules/AdminPanel/client/useAdminPanel'
import StaticPage from '@theme/userOptions/content/StaticPage.vue'
import BadgePage from '@theme/userOptions/pages/badges/Badge.vue'

const CodeEditor = defineAsyncComponent(() => import('@theme/foxEngine/editor/CodeEditor.vue'))

const props = defineProps<{
  projectPages: ProjectPageDraft[]
  badgePages: BadgePageDraft[]
  badges: BadgeCatalogRow[]
  claimKeys: BadgeClaimKeyRow[]
  issuedCode: IssuedBadgeClaimCode | null
  loading: boolean
}>()

const emit = defineEmits<{
  saveProjectPages: []
  saveBadgePage: [page: BadgePageDraft]
  deleteBadgePage: [page: BadgePageDraft]
  issueClaimKey: [badgeId: number, usageMode: BadgeClaimUsageMode]
  revokeClaimKey: [keyId: number]
  clearIssuedCode: []
}>()

const mode = ref<'project' | 'badges' | 'claims'>('project')
const projectWorkspaceTab = ref<'editor' | 'preview'>('editor')
const badgeWorkspaceTab = ref<'editor' | 'preview'>('editor')
const selectedProjectId = ref('')
const selectedBadgeName = ref('')
const selectedClaimBadgeId = ref(0)
const claimUsageMode = ref<BadgeClaimUsageMode>('single')
const copiedCode = ref(false)

const selectedProject = computed(() => props.projectPages.find((page) => page.id === selectedProjectId.value) ?? null)
const selectedBadge = computed(() => props.badges.find((badge) => badge.badgeName === selectedBadgeName.value) ?? null)
const selectedBadgePage = computed(() => {
  const badge = selectedBadge.value
  return badge ? props.badgePages.find((page) => page.slug === badge.pageSlug) ?? null : null
})
const selectedClaimBadge = computed(() => props.badges.find((badge) => badge.id === selectedClaimBadgeId.value) ?? null)
const selectedClaimKeys = computed(() => props.claimKeys
  .filter((entry) => entry.badgeId === selectedClaimBadgeId.value)
  .sort((left, right) => right.createdAt - left.createdAt))

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

watch(() => props.badges, (badges) => {
  if (!badges.some((badge) => badge.id === selectedClaimBadgeId.value)) selectedClaimBadgeId.value = badges[0]?.id ?? 0
}, { immediate: true, deep: true })

watch(() => props.issuedCode, () => { copiedCode.value = false })

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

function formatClaimDate(value: number | null): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value * 1000))
}

function issueClaimKey(): void {
  if (!selectedClaimBadge.value || props.loading) return
  emit('issueClaimKey', selectedClaimBadge.value.id, claimUsageMode.value)
}

function revokeClaimKey(entry: BadgeClaimKeyRow): void {
  if (!entry.enabled || !window.confirm(`Отозвать код для бейджа «${entry.badgeName}»?`)) return
  emit('revokeClaimKey', entry.id)
}

async function copyIssuedCode(): Promise<void> {
  const token = props.issuedCode?.token
  if (!token) return
  try {
    await navigator.clipboard.writeText(token)
    copiedCode.value = true
  } catch {
    const area = document.createElement('textarea')
    area.value = token
    area.style.position = 'fixed'
    area.style.opacity = '0'
    document.body.appendChild(area)
    area.select()
    copiedCode.value = document.execCommand('copy')
    area.remove()
  }
}
</script>

<template>
  <section class="admin-content-editor">
    <header class="admin-content-editor__header">
      <div>
        <span class="eyebrow">Runtime content</span>
        <h2>{{ mode === 'project' ? 'Страницы проекта' : mode === 'badges' ? 'HTML-страницы бейджей' : 'Коды получения бейджей' }}</h2>
        <p v-if="mode === 'project'">Страницы проекта хранятся отдельными HTML-файлами и редактируются через CodeMirror 5.</p>
        <p v-else-if="mode === 'badges'">Полные представления бейджей хранятся отдельно от каталога и редактируются через CodeMirror 5.</p>
        <p v-else>Выпуск, контроль применений и отзыв одноразовых и многоразовых кодов.</p>
      </div>
      <div class="admin-content-editor__modes">
        <button type="button" :class="{ active: mode === 'project' }" @click="mode = 'project'">
          <i class="fa-solid fa-newspaper" aria-hidden="true" /><span>Страницы проекта</span>
        </button>
        <button type="button" :class="{ active: mode === 'badges' }" @click="mode = 'badges'">
          <i class="fa-solid fa-award" aria-hidden="true" /><span>HTML-страницы бейджей</span>
        </button>
        <button type="button" :class="{ active: mode === 'claims' }" @click="mode = 'claims'">
          <i class="fa-solid fa-key" aria-hidden="true" /><span>Коды получения</span>
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

    <div v-else class="admin-content-editor__workspace admin-content-editor__workspace--claims">
      <aside class="admin-content-editor__list admin-content-editor__list--badges">
        <button
          v-for="badge in badges"
          :key="`claim-${badge.id}`"
          type="button"
          :class="{ active: selectedClaimBadgeId === badge.id }"
          @click="selectedClaimBadgeId = badge.id"
        >
          <img v-if="badge.img" :src="badge.img" alt="">
          <i v-else class="fa-solid fa-key" aria-hidden="true" />
          <span>
            <strong>{{ badge.badgeName }}</strong>
            <small>{{ claimKeys.filter((entry) => entry.badgeId === badge.id).length }} кодов</small>
          </span>
        </button>
      </aside>

      <div v-if="selectedClaimBadge" class="admin-content-form admin-claim-keys">
        <header class="admin-content-form__title">
          <div>
            <span class="eyebrow">Badge claim access</span>
            <h3>{{ selectedClaimBadge.badgeName }}</h3>
            <p>Каждый код привязан только к этому бейджу. В базе хранится SHA-256-хеш, а открытое значение показывается один раз.</p>
          </div>
          <img v-if="selectedClaimBadge.img" class="admin-content-form__badge-image" :src="selectedClaimBadge.img" alt="">
        </header>

        <section class="admin-claim-issuer">
          <label>
            <span>Режим кода</span>
            <select v-model="claimUsageMode">
              <option value="single">Одноразовый — одна успешная активация</option>
              <option value="reusable">Многоразовый — для разных профилей</option>
            </select>
          </label>
          <button class="button button--primary" type="button" :disabled="loading" @click="issueClaimKey">
            <i class="fa-solid fa-key" aria-hidden="true" />
            <span>Выпустить код</span>
          </button>
        </section>

        <section v-if="issuedCode && issuedCode.entry.badgeId === selectedClaimBadge.id" class="admin-issued-code" role="status">
          <header>
            <div>
              <strong>Новый {{ issuedCode.entry.usageMode === 'reusable' ? 'многоразовый' : 'одноразовый' }} код</strong>
              <small>Скопируйте сейчас. После закрытия открытое значение восстановить нельзя.</small>
            </div>
            <button type="button" aria-label="Закрыть" @click="emit('clearIssuedCode')">×</button>
          </header>
          <div class="admin-issued-code__value">
            <code>{{ issuedCode.token }}</code>
            <button class="button button--ghost" type="button" @click="copyIssuedCode">
              <i class="fa-solid" :class="copiedCode ? 'fa-check' : 'fa-copy'" aria-hidden="true" />
              <span>{{ copiedCode ? 'Скопировано' : 'Копировать' }}</span>
            </button>
          </div>
        </section>

        <section class="admin-claim-key-list">
          <header>
            <div><h4>Выпущенные коды</h4><p>Полное значение не хранится и здесь не отображается.</p></div>
            <span>{{ selectedClaimKeys.length }}</span>
          </header>

          <div v-if="selectedClaimKeys.length" class="admin-claim-key-rows">
            <article v-for="entry in selectedClaimKeys" :key="entry.id" class="admin-claim-key-row" :class="{ 'is-disabled': !entry.enabled }">
              <div class="admin-claim-key-row__identity">
                <span class="admin-claim-key-row__mode" :class="entry.accessMode === 'public' ? 'is-public' : `is-${entry.usageMode}`">
                  {{ entry.accessMode === 'public' ? 'Публичный' : entry.usageMode === 'reusable' ? 'Многоразовый' : 'Одноразовый' }}
                </span>
                <strong>••••••{{ entry.tokenHint }}</strong>
                <small>#{{ entry.id }} · создан {{ formatClaimDate(entry.createdAt) }}</small>
              </div>
              <dl>
                <div><dt>Использований</dt><dd>{{ entry.usesCount }}</dd></div>
                <div><dt>Последнее</dt><dd>{{ formatClaimDate(entry.lastClaimedAt) }}</dd></div>
                <div><dt>Состояние</dt><dd>{{ !entry.enabled ? 'Отозван' : entry.usageMode === 'single' && entry.usesCount > 0 ? 'Использован' : 'Активен' }}</dd></div>
              </dl>
              <button
                class="button admin-content-delete-page"
                type="button"
                :disabled="!entry.enabled || loading"
                @click="revokeClaimKey(entry)"
              >
                <i class="fa-solid fa-ban" aria-hidden="true" /><span>Отозвать</span>
              </button>
            </article>
          </div>

          <div v-else class="admin-content-empty-page admin-content-empty-page--compact">
            <i class="fa-solid fa-key" aria-hidden="true" />
            <strong>Кодов пока нет</strong>
            <p>Выпустите одноразовый или многоразовый код для выбранного бейджа.</p>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>
