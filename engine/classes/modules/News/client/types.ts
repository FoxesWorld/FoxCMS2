export interface NewsPost {
  id: number
  title: string
  summary: string
  content?: string
  coverImage: string
  isPublished: boolean
  publishedAt: string | null
  createdAt: string
  updatedAt: string
  authorLogin: string
  authorName: string
  authorPhoto: string
  authorGroup?: string
  authorColor?: string
  likesCount: number
  commentsCount: number
  viewsCount: number
  likedByViewer: boolean
  canEdit: boolean
}

export interface NewsComment {
  id: number
  content: string
  createdAt: string
  updatedAt: string
  authorUuid: string
  authorLogin: string
  authorName: string
  authorPhoto: string
  authorGroup?: string
  authorColor?: string
  canDelete: boolean
}

export interface NewsDraft {
  title: string
  summary: string
  content: string
  coverImage: string
  isPublished: boolean
}

export interface NewsListResponse {
  items: NewsPost[]
  canCreate: boolean
}

export interface NewsDetailResponse {
  post: NewsPost
  comments: NewsComment[]
  canComment: boolean
}
