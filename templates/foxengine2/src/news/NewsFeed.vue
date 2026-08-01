<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { appBootstrap } from '@/app/context'
import type { NewsDraft, NewsPost } from '@modules/News/client/types'
import { deleteNewsPost, formatNewsDate, loadNews, saveNewsPost, toggleNewsLike } from '@modules/News/client/newsApi'
import NewsEditor from './NewsEditor.vue'

const router = useRouter()
const posts = ref<NewsPost[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const isAdmin = String(appBootstrap.user.groupTag ?? '') === 'admin'
const canCreate = ref(isAdmin)
const creating = ref(false)
const editingId = ref<number | null>(null)

async function reload(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await loadNews(6)
    posts.value = response.items
    canCreate.value = isAdmin || response.canCreate
  } catch {
    error.value = 'Не удалось загрузить новости.'
  } finally {
    loading.value = false
  }
}

async function save(draft: NewsDraft, id = 0): Promise<void> {
  saving.value = true
  try {
    await saveNewsPost(id, draft)
    creating.value = false
    editingId.value = null
    await reload()
  } finally {
    saving.value = false
  }
}

async function remove(post: NewsPost): Promise<void> {
  if (!window.confirm(`Удалить публикацию «${post.title}»?`)) return
  await deleteNewsPost(post.id)
  editingId.value = null
  await reload()
}

async function like(post: NewsPost): Promise<void> {
  const response = await toggleNewsLike(post.id)
  post.likedByViewer = response.liked
  post.likesCount = response.likesCount
}

function reportNavigationFailure(scope: string, failure: unknown): void {
  console.error(`[FoxesCraft] ${scope} navigation failed`, failure)
}

function open(post: NewsPost): void {
  const id = String((post as NewsPost & { id?: unknown }).id ?? '').trim()
  if (!/^[1-9]\d*$/.test(id)) {
    console.error('[FoxesCraft] News post has invalid identifier', post)
    return
  }
  void router.push({ name: 'news', params: { id } })
    .catch((failure: unknown) => reportNavigationFailure('News', failure))
}

function openAuthor(post: NewsPost): void {
  const login = String(post.authorLogin ?? '').trim()
  if (!login) return
  void router.push({ name: 'profile', params: { value: login } })
    .catch((failure: unknown) => reportNavigationFailure('Author profile', failure))
}

onMounted(() => void reload())
</script>

<template>
  <section class="news-feed" aria-labelledby="news-feed-title">
    <header class="news-feed__header">
      <div>
        <span class="eyebrow">FoxesCraft Chronicle</span>
        <h2 id="news-feed-title">Новости</h2>
        <p>Обновления проекта, игровые события и важные объявления.</p>
      </div>
      <button v-if="canCreate && !creating" class="button button--primary" type="button" @click="creating = true">
        <i class="fa-solid fa-plus" aria-hidden="true" />
        <span>Добавить новость</span>
      </button>
    </header>



    <article v-if="creating" class="news-card news-card--editor">
      <NewsEditor :saving="saving" @save="save($event)" @cancel="creating = false" />
    </article>

    <div v-if="loading" class="news-grid" aria-label="Загрузка новостей">
      <article v-for="index in 3" :key="index" class="news-card news-card--loading"><span /><span /><span /></article>
    </div>
    <p v-else-if="error" class="system-message system-message--error">{{ error }}</p>
    <div v-else-if="posts.length === 0" class="news-feed__empty">
      <strong>Новостей пока нет</strong>
      <span v-if="canCreate">Нажмите «Добавить новость». Автор будет назначен автоматически.</span>
      <span v-else>Первая публикация появится здесь.</span>
      <button v-if="canCreate" class="button button--primary" type="button" @click="creating = true">
        <i class="fa-solid fa-plus" aria-hidden="true" />
        <span>Добавить новость</span>
      </button>
    </div>

    <div v-else class="news-grid">
      <article v-for="post in posts" :key="post.id" class="news-card" :class="{ 'news-card--draft': !post.isPublished }">
        <NewsEditor
          v-if="editingId === post.id"
          :initial="post"
          :saving="saving"
          allow-delete
          @save="save($event, post.id)"
          @cancel="editingId = null"
          @remove="remove(post)"
        />

        <template v-else>
          <button class="news-card__cover" type="button" :aria-label="`Открыть публикацию ${post.title}`" @click="open(post)">
            <img v-if="post.coverImage" :src="post.coverImage" :alt="`Обложка публикации ${post.title}`" loading="lazy" decoding="async">
            <i v-else class="fa-solid fa-newspaper" aria-hidden="true" />
          </button>

          <div class="news-card__main">
            <div class="news-card__topline">
              <time>{{ formatNewsDate(post.publishedAt || post.createdAt) }}</time>
              <span v-if="!post.isPublished" class="news-status">Черновик</span>
            </div>

            <div class="news-card__headline">
              <button class="news-card__title" type="button" @click="open(post)">{{ post.title }}</button>
              <div class="news-card__metrics" aria-label="Активность публикации">
                <button type="button" title="Комментарии" @click="open(post)">
                  <i class="fa-solid fa-comments" aria-hidden="true" /><b>{{ post.commentsCount }}</b>
                </button>
                <button
                  type="button"
                  title="Нравится"
                  :class="{ 'is-active': post.likedByViewer }"
                  :disabled="!post.isPublished"
                  @click="like(post)"
                >
                  <i class="fa-solid fa-heart" aria-hidden="true" /><b>{{ post.likesCount }}</b>
                </button>
              </div>
            </div>

            <p class="news-card__summary">{{ post.summary }}</p>
          </div>

          <footer class="news-card__footer">
            <button class="news-card__author" type="button" @click="openAuthor(post)">
              <span class="news-card__author-avatar">
                <img v-if="post.authorPhoto" :src="post.authorPhoto" alt="">
                <b v-else>{{ post.authorName.slice(0, 1).toUpperCase() }}</b>
              </span>
              <span>Автор: <strong :style="{ color: post.authorColor || undefined }">{{ post.authorName }}</strong></span>
            </button>

            <span class="news-card__views"><i class="fa-solid fa-eye" aria-hidden="true" /> Просмотры: <strong>{{ post.viewsCount }}</strong></span>
            <button v-if="post.canEdit" class="news-card__edit" type="button" @click="editingId = post.id"><i class="fa-solid fa-pen-to-square" aria-hidden="true" /><span>Редактировать</span></button>
            <button class="button button--primary news-card__read" type="button" @click="open(post)"><span>Читать далее</span><i class="fa-solid fa-arrow-right" aria-hidden="true" /></button>
          </footer>
        </template>
      </article>
    </div>
  </section>
</template>
