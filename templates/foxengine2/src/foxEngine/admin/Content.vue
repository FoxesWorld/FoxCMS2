<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type {
  BadgeCatalogRow, BadgePageDraft, ContentSectionDraft, ProjectPageDraft,
} from '@modules/AdminPanel/client/useAdminPanel'

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
const selectedProjectId = ref('')
const selectedBadgeName = ref('')

const selectedProject = computed(() => props.projectPages.find((page) => page.id === selectedProjectId.value) ?? null)
const selectedBadge = computed(() => props.badges.find((badge) => badge.badgeName === selectedBadgeName.value) ?? null)
const selectedBadgePage = computed(() => {
  const badge = selectedBadge.value
  return badge ? props.badgePages.find((page) => page.slug === badge.pageSlug) ?? null : null
})

const badgePreviewDocument = computed(() => {
  const page = selectedBadgePage.value
  const badge = selectedBadge.value
  if (!page || !badge) return ''

  const parsed = new DOMParser().parseFromString(page.html, 'text/html')
  parsed.querySelectorAll('[data-badge-title]').forEach((element) => { element.textContent = badge.badgeName })
  parsed.querySelectorAll('[data-badge-description]').forEach((element) => { element.textContent = badge.description })
  parsed.querySelectorAll<HTMLImageElement>('[data-badge-image]').forEach((element) => {
    if (badge.img) {
      element.src = badge.img
      element.alt = badge.badgeName
    } else {
      element.removeAttribute('src')
      element.hidden = true
    }
  })

  return `<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'; font-src 'none';">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{color-scheme:dark;font-family:system-ui,sans-serif;background:#0b100d;color:#eef4f0}
*{box-sizing:border-box}body{margin:0;padding:22px;background:#0b100d}article{display:grid;gap:24px;max-width:980px;margin:auto;padding:26px;border:1px solid #2b3930;border-radius:20px;background:#121a15}header{display:grid;grid-template-columns:132px minmax(0,1fr);align-items:center;gap:24px;padding-bottom:22px;border-bottom:1px solid #2b3930}header img{width:108px;height:108px;object-fit:contain}h1{margin:6px 0 10px;font-size:clamp(2rem,6vw,4rem);line-height:1}.eyebrow{color:#78d59a;font-size:.75rem;font-weight:900;text-transform:uppercase;letter-spacing:.14em}.lead,p,li{color:#aebdb4;line-height:1.7}h2{margin:0 0 12px}.badge-story{max-width:850px}.manifest-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}.manifest-grid>div,.notice-panel{padding:16px;border:1px solid #2b3930;border-radius:14px;background:#172119}@media(max-width:620px){header{grid-template-columns:1fr}}
</style>
</head>
<body>${parsed.body.innerHTML}</body>
</html>`
})

watch(() => props.projectPages, (pages) => {
  if (!pages.some((page) => page.id === selectedProjectId.value)) selectedProjectId.value = pages[0]?.id ?? ''
}, { immediate: true, deep: true })

watch(() => props.badges, (badges) => {
  if (!badges.some((badge) => badge.badgeName === selectedBadgeName.value)) selectedBadgeName.value = badges[0]?.badgeName ?? ''
}, { immediate: true, deep: true })

function emptySection(): ContentSectionDraft {
  return { title: '', paragraphs: [], items: [], cards: [], notice: null }
}

function paragraphsText(values: string[]): string {
  return values.join('\n\n')
}

function linesText(values: string[]): string {
  return values.join('\n')
}

function splitParagraphs(value: string): string[] {
  return value.split(/\n\s*\n/g).map((entry) => entry.trim()).filter(Boolean)
}

function splitLines(value: string): string[] {
  return value.split(/\r?\n/g).map((entry) => entry.trim()).filter(Boolean)
}

function updateParagraphs(section: ContentSectionDraft, event: Event): void {
  section.paragraphs = splitParagraphs((event.target as HTMLTextAreaElement).value)
}

function updateItems(section: ContentSectionDraft, event: Event): void {
  section.items = splitLines((event.target as HTMLTextAreaElement).value)
}

function addSection(sections: ContentSectionDraft[]): void {
  sections.push(emptySection())
}

function removeSection(sections: ContentSectionDraft[], index: number): void {
  sections.splice(index, 1)
}

function addCard(section: ContentSectionDraft): void {
  section.cards.push({ title: 'Новая карточка', text: '' })
}

function toggleNotice(section: ContentSectionDraft): void {
  section.notice = section.notice ? null : { title: 'Важно', text: '' }
}

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
        <h2>Страницы проекта</h2>
        <p>Страницы проекта хранятся в JSON, а полные представления бейджей — отдельными HTML-файлами.</p>
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

      <form v-if="selectedProject" class="admin-content-form" @submit.prevent="emit('saveProjectPages')">
        <header class="admin-content-form__title">
          <div><span class="eyebrow">{{ selectedProject.id }}</span><h3>{{ selectedProject.title }}</h3></div>
          <button class="button button--primary" type="submit" :disabled="loading"><i class="fa-solid fa-floppy-disk" aria-hidden="true" /><span>Сохранить страницы</span></button>
        </header>

        <div class="admin-content-form__grid">
          <label><span>ID маршрута</span><input v-model="selectedProject.id" type="text" readonly></label>
          <label><span>Макет</span><select v-model="selectedProject.layout"><option value="default">Обычная страница</option><option value="rules">Нумерованные правила</option></select></label>
          <label><span>Надпись</span><input v-model.trim="selectedProject.eyebrow" type="text" maxlength="120"></label>
          <label><span>Дата обновления</span><input v-model.trim="selectedProject.updated" type="text" maxlength="80"></label>
          <label class="wide"><span>Заголовок</span><input v-model.trim="selectedProject.title" type="text" maxlength="180" required></label>
          <label class="wide"><span>Краткое описание</span><textarea v-model.trim="selectedProject.summary" rows="4" maxlength="1200" /></label>
          <label class="wide"><span>Изображение</span><input v-model.trim="selectedProject.image" type="text" maxlength="512" placeholder="img/... или /uploads/..."></label>
          <label><span>Alt изображения</span><input v-model.trim="selectedProject.imageAlt" type="text" maxlength="240"></label>
          <label><span>Подпись изображения</span><input v-model.trim="selectedProject.imageCaption" type="text" maxlength="240"></label>
        </div>

        <div class="admin-content-sections">
          <header><h3>Секции</h3><button class="button button--ghost" type="button" @click="addSection(selectedProject.sections)"><i class="fa-solid fa-plus" aria-hidden="true" /><span>Добавить секцию</span></button></header>
          <article v-for="(section, sectionIndex) in selectedProject.sections" :key="sectionIndex" class="admin-content-section">
            <header><strong>Секция {{ sectionIndex + 1 }}</strong><button type="button" class="danger" @click="removeSection(selectedProject.sections, sectionIndex)"><i class="fa-solid fa-trash-can" aria-hidden="true" /></button></header>
            <label><span>Заголовок секции</span><input v-model.trim="section.title" type="text" maxlength="180"></label>
            <label><span>Абзацы — разделяются пустой строкой</span><textarea :value="paragraphsText(section.paragraphs)" rows="7" @input="updateParagraphs(section, $event)" /></label>
            <label><span>Элементы списка — по одному на строку</span><textarea :value="linesText(section.items)" rows="5" @input="updateItems(section, $event)" /></label>
            <div class="admin-content-cards">
              <header><strong>Карточки</strong><button type="button" @click="addCard(section)"><i class="fa-solid fa-plus" aria-hidden="true" /> Добавить</button></header>
              <div v-for="(card, cardIndex) in section.cards" :key="cardIndex" class="admin-content-card-row"><input v-model.trim="card.title" maxlength="160" placeholder="Заголовок"><textarea v-model.trim="card.text" rows="3" maxlength="3000" placeholder="Текст карточки" /><button type="button" class="danger" @click="section.cards.splice(cardIndex, 1)"><i class="fa-solid fa-trash-can" aria-hidden="true" /></button></div>
            </div>
            <button class="admin-content-notice-toggle" type="button" @click="toggleNotice(section)">{{ section.notice ? 'Удалить notice-блок' : 'Добавить notice-блок' }}</button>
            <div v-if="section.notice" class="admin-content-notice-fields"><input v-model.trim="section.notice.title" maxlength="180" placeholder="Заголовок notice"><textarea v-model.trim="section.notice.text" rows="4" maxlength="5000" placeholder="Текст notice" /></div>
          </article>
        </div>

        <footer class="admin-content-form__footer"><button class="button button--primary" type="submit" :disabled="loading"><i class="fa-solid fa-floppy-disk" aria-hidden="true" /><span>Сохранить страницы проекта</span></button></footer>
      </form>
    </div>

    <div v-else class="admin-content-editor__workspace">
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

          <label class="admin-badge-html-editor__source">
            <span>Полная HTML-разметка страницы</span>
            <textarea v-model="selectedBadgePage.html" rows="30" spellcheck="false" />
            <small>Обязательны: <code>data-badge-page</code>, <code>data-badge-title</code>, <code>data-badge-description</code>, <code>data-badge-image</code> и <code>data-badge-history</code>. Данные из БД подставляются сервером. Скрипты, style, iframe, формы и inline-события запрещены.</small>
          </label>

          <section class="admin-badge-html-preview">
            <header><strong>Sandbox-preview</strong><small>Vue обновляет preview, но HTML не получает права выполнять JavaScript.</small></header>
            <iframe title="Предпросмотр HTML-страницы бейджа" sandbox="" :srcdoc="badgePreviewDocument" />
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
