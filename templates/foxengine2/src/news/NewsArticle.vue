<script setup lang="ts">
import { t } from '@/i18n'

import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import type { NewsComment, NewsDetailResponse, NewsDraft, NewsPost } from '@modules/News/client/types'
import {
  addNewsComment,
  deleteNewsComment,
  deleteNewsPost,
  formatNewsDate,
  loadNewsPost,
  saveNewsPost,
  toggleNewsLike,
} from '@modules/News/client/newsApi'
import NewsEditor from './NewsEditor.vue'

const props = defineProps<{ id: number }>()
const router = useRouter()
const post = ref<NewsPost | null>(null)
const comments = ref<NewsComment[]>([])
const canComment = ref(false)
const loading = ref(true)
const saving = ref(false)
const editing = ref(false)
const comment = ref('')
const error = ref('')
let trackedPostId: number | null = null

async function load(trackView = false): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const shouldTrackView = trackView && trackedPostId !== props.id
    const response: NewsDetailResponse = await loadNewsPost(props.id, shouldTrackView)
    if (shouldTrackView) trackedPostId = props.id
    post.value = response.post
    comments.value = response.comments
    canComment.value = response.canComment
  } catch {
    post.value = null
    error.value = t('theme.news.newsarticle.016')
  } finally {
    loading.value = false
  }
}

async function save(draft: NewsDraft): Promise<void> {
  if (!post.value) return
  saving.value = true
  try {
    await saveNewsPost(post.value.id, draft)
    editing.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  if (!post.value || !window.confirm(t('theme.news.newsarticle.017', [post.value.title]))) return
  await deleteNewsPost(post.value.id)
  await router.push({ name: 'home' })
}

async function like(): Promise<void> {
  if (!post.value) return
  const response = await toggleNewsLike(post.value.id)
  post.value.likedByViewer = response.liked
  post.value.likesCount = response.likesCount
}

async function publishComment(): Promise<void> {
  if (!post.value || !comment.value.trim()) return
  await addNewsComment(post.value.id, comment.value.trim())
  comment.value = ''
  await load()
}

async function removeComment(item: NewsComment): Promise<void> {
  if (!window.confirm(t('theme.news.newsarticle.018'))) return
  await deleteNewsComment(item.id)
  await load()
}

function openAuthor(login: string): void {
  void router.push({ name: 'profile', params: { value: login } })
}

watch(() => props.id, () => {
  trackedPostId = null
  void load(true)
}, { immediate: true })
</script>

<template>
  <div v-if="loading" class="content-skeleton" :aria-label="t('theme.news.newsarticle.001')"><span /><span /><span /></div>
  <div v-else-if="error || !post" class="system-message system-message--error">
    <strong>{{ t('theme.news.newsarticle.002') }}</strong>
    <p>{{ error }}</p>
  </div>

  <article v-else class="content-surface news-article" :class="{ 'news-article--draft': !post.isPublished }">
    <NewsEditor
      v-if="editing"
      :initial="post"
      :saving="saving"
      allow-delete
      @save="save"
      @cancel="editing = false"
      @remove="remove"
    />

    <template v-else>
      <header class="news-article__header">
        <button class="news-author news-author--button" type="button" @click="openAuthor(post.authorLogin)">
          <span class="news-author__avatar">
            <img v-if="post.authorPhoto" :src="post.authorPhoto" alt="">
            <b v-else>{{ post.authorName.slice(0, 1).toUpperCase() }}</b>
          </span>
          <span>
            <strong :style="{ color: post.authorColor || undefined }">{{ post.authorName }}</strong>
            <small>{{ post.authorGroup || `@${post.authorLogin}` }}</small>
          </span>
        </button>

        <div class="news-article__tools">
          <span v-if="!post.isPublished" class="news-status">{{ t('theme.news.newsarticle.003') }}</span>
          <button v-if="post.canEdit" class="button button--ghost" type="button" @click="editing = true">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true" />
            <span>{{ t('theme.news.newsarticle.004') }}</span>
          </button>
        </div>
      </header>

      <section class="news-article__hero">
        <div class="news-article__intro">
          <span class="eyebrow">{{ t('theme.news.newsarticle.005') }}</span>
          <h1>{{ post.title }}</h1>
          <p class="news-article__summary">{{ post.summary }}</p>
          <div class="news-article__date">
            <time>{{ formatNewsDate(post.publishedAt || post.createdAt) }}</time>
          </div>
        </div>

        <figure class="news-article__cover-frame" :class="{ 'is-empty': !post.coverImage }">
          <img
            v-if="post.coverImage"
            class="news-article__cover"
            :src="post.coverImage"
            :alt="t('theme.news.newsarticle.006', [post.title])"
            decoding="async"
          >
          <i v-else class="fa-solid fa-newspaper" aria-hidden="true" />
        </figure>
      </section>

      <div class="news-article__divider" aria-hidden="true"><span /></div>

      <div class="news-article__content fr-view" v-html="post.content || ''" />

      <footer class="news-article__reactions">
        <button
          class="news-reaction news-reaction--large"
          :class="{ 'is-active': post.likedByViewer }"
          type="button"
          :disabled="!post.isPublished"
          @click="like"
        >
          <i class="fa-solid fa-heart" aria-hidden="true" />
          <span>{{ post.likesCount }}</span>
        </button>
        <span><i class="fa-solid fa-comments" aria-hidden="true" /><b>{{ post.commentsCount }}</b> {{ t('theme.news.newsarticle.007') }}</span>
        <span><i class="fa-solid fa-eye" aria-hidden="true" /><b>{{ post.viewsCount }}</b> {{ t('theme.news.newsarticle.008') }}</span>
      </footer>
    </template>
  </article>

  <section v-if="post" class="content-surface news-comments" aria-labelledby="news-comments-title">
    <header>
      <div>
        <span class="eyebrow">{{ t('theme.news.newsarticle.009') }}</span>
        <h2 id="news-comments-title">{{ t('theme.news.newsarticle.010') }}</h2>
      </div>
      <strong>{{ comments.length }}</strong>
    </header>

    <form v-if="canComment" class="news-comment-form" @submit.prevent="publishComment">
      <textarea v-model="comment" rows="4" maxlength="2000" :placeholder="t('theme.news.newsarticle.011')" required />
      <div class="news-comment-form__footer">
        <small>{{ comment.length }} / 2000</small>
        <button class="button button--primary" type="submit" :disabled="!comment.trim()">
          <i class="fa-solid fa-paper-plane" aria-hidden="true" /><span>{{ t('theme.news.newsarticle.012') }}</span>
        </button>
      </div>
    </form>
    <p v-else class="news-comments__notice">{{ t('theme.news.newsarticle.013') }}</p>

    <div v-if="comments.length" class="news-comment-list">
      <article v-for="item in comments" :key="item.id" class="news-comment">
        <button class="news-author news-author--button" type="button" @click="openAuthor(item.authorLogin)">
          <span class="news-author__avatar">
            <img v-if="item.authorPhoto" :src="item.authorPhoto" alt="">
            <b v-else>{{ item.authorName.slice(0, 1).toUpperCase() }}</b>
          </span>
          <span>
            <strong :style="{ color: item.authorColor || undefined }">{{ item.authorName }}</strong>
            <small>{{ item.authorGroup || `@${item.authorLogin}` }} · {{ formatNewsDate(item.createdAt) }}</small>
          </span>
        </button>
        <p>{{ item.content }}</p>
        <button v-if="item.canDelete" class="news-comment__delete" type="button" @click="removeComment(item)">
          <i class="fa-solid fa-trash-can" aria-hidden="true" />
          <span>{{ t('theme.news.newsarticle.014') }}</span>
        </button>
      </article>
    </div>
    <p v-else class="news-feed__empty">{{ t('theme.news.newsarticle.015') }}</p>
  </section>
</template>
