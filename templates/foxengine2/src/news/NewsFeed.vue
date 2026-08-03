<script setup lang="ts">
import { t } from '@/i18n'

import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { appBootstrap } from '@/app/context'
import type { NewsDraft, NewsPost } from '@modules/News/client/types'
import { deleteNewsPost, formatNewsDate, loadNews, saveNewsPost, toggleNewsLike } from '@modules/News/client/newsApi'
import NewsEditor from './NewsEditor.vue'

const props = withDefaults(defineProps<{ archive?: boolean }>(), { archive: false })
const router = useRouter()
const posts = ref<NewsPost[]>([])
const loading = ref(true)
const loadingMore = ref(false)
const saving = ref(false)
const error = ref('')
const loadMoreError = ref('')
const total = ref(0)
const hasMore = ref(false)
const pageSize = computed(() => props.archive ? 12 : 6)
const isAdmin = String(appBootstrap.user.groupTag ?? '') === 'admin'
const canCreate = ref(isAdmin)
const creating = ref(false)
const editingId = ref<number | null>(null)

async function reload(): Promise<void> {
  loading.value = true
  error.value = ''
  loadMoreError.value = ''
  try {
    const response = await loadNews(pageSize.value, 0)
    posts.value = response.items
    total.value = response.total
    hasMore.value = response.hasMore
    canCreate.value = isAdmin || response.canCreate
  } catch {
    error.value = t('theme.news.newsfeed.019')
  } finally {
    loading.value = false
  }
}

async function loadMore(): Promise<void> {
  if (loading.value || loadingMore.value || !hasMore.value) return
  loadingMore.value = true
  loadMoreError.value = ''
  try {
    const response = await loadNews(pageSize.value, posts.value.length)
    const known = new Set(posts.value.map((post) => post.id))
    posts.value.push(...response.items.filter((post) => !known.has(post.id)))
    total.value = response.total
    hasMore.value = response.hasMore
    canCreate.value = isAdmin || response.canCreate
  } catch {
    loadMoreError.value = t('theme.news.newsfeed.019')
  } finally {
    loadingMore.value = false
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
  if (!window.confirm(t('theme.news.newsfeed.020', [post.title]))) return
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

function openArchive(): void {
  void router.push({ name: 'news-list' })
    .catch((failure: unknown) => reportNavigationFailure('News archive', failure))
}

function openHome(): void {
  void router.push({ name: 'home' })
    .catch((failure: unknown) => reportNavigationFailure('Home', failure))
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
        <span class="eyebrow">{{ t('theme.news.newsfeed.001') }}</span>
        <h2 id="news-feed-title">{{ archive ? t('theme.news.newsarchive.001') : t('theme.news.newsfeed.002') }}</h2>
        <p>{{ archive ? t('theme.news.newsarchive.002') : t('theme.news.newsfeed.003') }}</p>
      </div>
      <div class="news-feed__actions">
        <button v-if="archive" class="button button--ghost" type="button" @click="openHome">
          <i class="fa-solid fa-arrow-left" aria-hidden="true" />
          <span>{{ t('theme.news.newsarchive.003') }}</span>
        </button>
        <button v-else class="button button--ghost" type="button" @click="openArchive">
          <span>{{ t('theme.news.newsarchive.001') }}</span>
          <i class="fa-solid fa-arrow-right" aria-hidden="true" />
        </button>
        <button v-if="canCreate && !creating" class="button button--primary" type="button" @click="creating = true">
          <i class="fa-solid fa-plus" aria-hidden="true" />
          <span>{{ t('theme.news.newsfeed.004') }}</span>
        </button>
      </div>
    </header>



    <article v-if="creating" class="news-card news-card--editor">
      <NewsEditor :saving="saving" @save="save($event)" @cancel="creating = false" />
    </article>

    <div v-if="loading" class="news-grid" :aria-label="t('theme.news.newsfeed.005')">
      <article v-for="index in (archive ? 6 : 3)" :key="index" class="news-card news-card--loading"><span /><span /><span /></article>
    </div>
    <p v-else-if="error" class="system-message system-message--error">{{ error }}</p>
    <div v-else-if="posts.length === 0" class="news-feed__empty">
      <strong>{{ t('theme.news.newsfeed.006') }}</strong>
      <span v-if="canCreate">{{ t('theme.news.newsfeed.007') }}</span>
      <span v-else>{{ t('theme.news.newsfeed.008') }}</span>
      <button v-if="canCreate" class="button button--primary" type="button" @click="creating = true">
        <i class="fa-solid fa-plus" aria-hidden="true" />
        <span>{{ t('theme.news.newsfeed.004') }}</span>
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
          <button class="news-card__cover" type="button" :aria-label="t('theme.news.newsfeed.009', [post.title])" @click="open(post)">
            <img v-if="post.coverImage" :src="post.coverImage" :alt="t('theme.news.newsfeed.010', [post.title])" loading="lazy" decoding="async">
            <i v-else class="fa-solid fa-newspaper" aria-hidden="true" />
          </button>

          <div class="news-card__main">
            <div class="news-card__topline">
              <time>{{ formatNewsDate(post.publishedAt || post.createdAt) }}</time>
              <span v-if="!post.isPublished" class="news-status">{{ t('theme.news.newsfeed.011') }}</span>
            </div>

            <div class="news-card__headline">
              <button class="news-card__title" type="button" @click="open(post)">{{ post.title }}</button>
              <div class="news-card__metrics" :aria-label="t('theme.news.newsfeed.012')">
                <button type="button" :title="t('theme.news.newsfeed.013')" @click="open(post)">
                  <i class="fa-solid fa-comments" aria-hidden="true" /><b>{{ post.commentsCount }}</b>
                </button>
                <button
                  type="button"
                  :title="t('theme.news.newsfeed.014')"
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
              <span>{{ t('theme.news.newsfeed.015') }} <strong :style="{ color: post.authorColor || undefined }">{{ post.authorName }}</strong></span>
            </button>

            <span class="news-card__views"><i class="fa-solid fa-eye" aria-hidden="true" /> {{ t('theme.news.newsfeed.016') }} <strong>{{ post.viewsCount }}</strong></span>
            <button v-if="post.canEdit" class="news-card__edit" type="button" @click="editingId = post.id"><i class="fa-solid fa-pen-to-square" aria-hidden="true" /><span>{{ t('theme.news.newsfeed.017') }}</span></button>
            <button class="button button--primary news-card__read" type="button" @click="open(post)"><span>{{ t('theme.news.newsfeed.018') }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true" /></button>
          </footer>
        </template>
      </article>
    </div>

    <footer v-if="archive && !loading && posts.length" class="news-archive__footer">
      <span>{{ t('theme.news.newsarchive.004', [posts.length, total]) }}</span>
      <span v-if="loadMoreError" class="news-archive__error" role="alert">{{ loadMoreError }}</span>
      <button v-if="hasMore" class="button button--ghost" type="button" :disabled="loadingMore" @click="loadMore">
        <i class="fa-solid" :class="loadingMore ? 'fa-spinner' : 'fa-angles-down'" aria-hidden="true" />
        <span>{{ loadingMore ? t('theme.news.newsarchive.005') : t('theme.news.newsarchive.006') }}</span>
      </button>
      <strong v-else>{{ t('theme.news.newsarchive.007') }}</strong>
    </footer>
  </section>
</template>
