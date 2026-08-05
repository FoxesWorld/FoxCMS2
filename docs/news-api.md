# Public News API

Read-only JSON API for published FoxCMS news posts.

## Endpoint

```text
GET /api/news.php
HEAD /api/news.php
```

Only published posts with a non-null publication date are returned. Drafts are never exposed.

## List news

```http
GET /api/news.php?limit=10&offset=0&includeImages=1
```

Query parameters:

| Parameter | Default | Range | Description |
| --- | ---: | ---: | --- |
| `limit` | `10` | `1..50` | Maximum number of posts in the response. |
| `offset` | `0` | `0..1000000` | Number of posts to skip. |
| `includeImages` | `false` | boolean | Include a sanitized local cover preview as `coverImageDataUrl`. |

Example response:

```json
{
  "items": [
    {
      "id": 42,
      "title": "Server update",
      "summary": "A short publication summary.",
      "coverImage": "/uploads/news/news-example.webp",
      "coverImageDataUrl": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ...",
      "publishedAt": "2026-08-04 12:00:00.0000",
      "createdAt": "2026-08-04 11:45:00.0000",
      "updatedAt": "2026-08-04 12:00:00.0000",
      "authorLogin": "admin",
      "authorName": "Administrator",
      "authorPhoto": null,
      "authorGroup": "Administrators",
      "authorColor": "#ff7a00",
      "likesCount": 7,
      "commentsCount": 3,
      "viewsCount": 125
    }
  ],
  "total": 18,
  "limit": 10,
  "offset": 0,
  "hasMore": true
}
```

The list response intentionally omits the full `content` field.

`coverImageDataUrl` is returned only with `includeImages=1`. The API accepts only image data already stored in the news record or local files under `/uploads`, validates the actual MIME type and dimensions, applies a 2 MiB source limit, and never fetches arbitrary remote URLs. When PHP GD is available, covers are resized to a maximum of 960×540 and encoded as JPEG.

## Get one news post

```http
GET /api/news.php?id=42
```

The detail response includes sanitized HTML in `post.content` and includes public comments by default.

```json
{
  "post": {
    "id": 42,
    "title": "Server update",
    "summary": "A short publication summary.",
    "content": "<p>Full publication text.</p>",
    "coverImage": "/uploads/news/news-example.webp",
    "publishedAt": "2026-08-04 12:00:00.0000",
    "createdAt": "2026-08-04 11:45:00.0000",
    "updatedAt": "2026-08-04 12:00:00.0000",
    "authorLogin": "admin",
    "authorName": "Administrator",
    "authorPhoto": null,
    "authorGroup": "Administrators",
    "authorColor": "#ff7a00",
    "likesCount": 7,
    "commentsCount": 3,
    "viewsCount": 125
  },
  "comments": [],
  "commentsIncluded": true
}
```

Set `includeComments=0` to omit the comments query:

```http
GET /api/news.php?id=42&includeComments=0
```

## HTTP behavior

- Supported methods: `GET`, `HEAD`.
- Successful responses use `Cache-Control: public, max-age=60, stale-while-revalidate=300`.
- Every response includes an `ETag`; matching `If-None-Match` requests receive `304 Not Modified`.
- Invalid parameters return `400 invalid_request`.
- Missing or unpublished posts return `404 news_not_found`.
- Unsupported methods return `405 method_not_allowed` with `Allow: GET, HEAD`.
- Database or schema failures return `503 news_unavailable` and a request ID for server logs.

## JavaScript example

```ts
interface NewsListResponse {
  items: Array<{
    id: number
    title: string
    summary: string
    coverImage: string | null
    coverImageDataUrl?: string | null
    publishedAt: string | null
    createdAt: string
    updatedAt: string
    authorLogin: string
    authorName: string
    authorPhoto: string | null
    authorGroup: string | null
    authorColor: string | null
    likesCount: number
    commentsCount: number
    viewsCount: number
  }>
  total: number
  limit: number
  offset: number
  hasMore: boolean
}

export async function getNews(limit = 10, offset = 0): Promise<NewsListResponse> {
  const query = new URLSearchParams({
    limit: String(limit),
    offset: String(offset),
    includeImages: '1',
  })
  const response = await fetch(`/api/news.php?${query.toString()}`, {
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) {
    throw new Error(`News API returned HTTP ${response.status}`)
  }
  return response.json() as Promise<NewsListResponse>
}
```
