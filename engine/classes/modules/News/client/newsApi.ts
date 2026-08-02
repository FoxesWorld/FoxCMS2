import { foxesApi } from '@/api'
import type { NewsDetailResponse, NewsDraft, NewsListResponse } from './types'

export function loadNews(limit = 6): Promise<NewsListResponse> {
  return foxesApi.post<NewsListResponse>({ newsAction: 'list', limit })
}

export function loadNewsPost(id: number, trackView = false): Promise<NewsDetailResponse> {
  return foxesApi.post<NewsDetailResponse>({ newsAction: 'detail', id, trackView: trackView ? 1 : 0 })
}

export function toggleNewsLike(id: number): Promise<{ liked: boolean; likesCount: number }> {
  return foxesApi.post({ newsAction: 'toggleLike', id })
}

export function saveNewsPost(id: number, entry: NewsDraft): Promise<{ id: number; message: string; type: string }> {
  return foxesApi.post({ newsAction: 'save', id, entry: JSON.stringify(entry) })
}

export function deleteNewsPost(id: number): Promise<{ message: string; type: string }> {
  return foxesApi.post({ newsAction: 'delete', id })
}


export function uploadNewsCover(file: File): Promise<{ coverImage: string; message: string; type: string }> {
  const body = new FormData()
  body.set('newsAction', 'uploadCover')
  body.set('cover', file, file.name)
  return foxesApi.postFormData(body)
}

export function addNewsComment(id: number, content: string): Promise<{ message: string; type: string }> {
  return foxesApi.post({ newsAction: 'addComment', id, content })
}

export function deleteNewsComment(commentId: number): Promise<{ message: string; type: string }> {
  return foxesApi.post({ newsAction: 'deleteComment', commentId })
}

export function newsDraft(post?: Partial<NewsDraft>): NewsDraft {
  return {
    title: post?.title ?? '',
    summary: post?.summary ?? '',
    content: post?.content ?? '',
    coverImage: post?.coverImage ?? '',
    isPublished: post?.isPublished ?? false,
  }
}

export function formatNewsDate(value: string | null | undefined): string {
  if (!value) return 'Черновик'
  const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'))
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat('ru-RU', { dateStyle: 'long', timeStyle: 'short' }).format(date)
}
