import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const read = (path) => readFile(join(repositoryRoot, path), 'utf8')
const [manifestText, feed, view, api, types, module, repository, router, styles] = await Promise.all([
  read('templates/foxengine2/frontend.json'),
  read('templates/foxengine2/src/news/NewsFeed.vue'),
  read('engine/classes/modules/News/client/views/NewsListView.vue'),
  read('engine/classes/modules/News/client/newsApi.ts'),
  read('engine/classes/modules/News/client/types.ts'),
  read('engine/classes/modules/News/News.class.php'),
  read('engine/classes/modules/News/NewsRepository.class.php'),
  read('engine/client/router/index.ts'),
  read('templates/foxengine2/src/styles/news.css'),
])

let manifest
try { manifest = JSON.parse(manifestText) } catch (error) { failures.push(`frontend.json is invalid: ${error.message}`) }
const route = manifest?.routes?.find((entry) => entry?.name === 'news-list')
if (!route || route.path !== '/news' || route.view !== 'NewsListView' || route.module !== 'News') {
  failures.push('frontend manifest must expose /news through NewsListView and the News module')
}

const requireText = (label, text, tokens) => {
  for (const token of tokens) if (!text.includes(token)) failures.push(`${label} is missing ${token}`)
}
requireText('Home news feed archive navigation', feed, [
  "router.push({ name: 'news-list' })",
  "t('theme.news.newsarchive.001')",
  'v-if="archive"',
  'async function loadMore()',
  'loadNews(pageSize.value, posts.value.length)',
  'class="news-archive__footer"',
])
requireText('News archive view', view, ["import NewsFeed from '@theme/news/NewsFeed.vue'", '<NewsFeed archive />'])
requireText('News list API pagination', api, ["loadNews(limit = 6, offset = 0)", "newsAction: 'list', limit, offset"])
requireText('News response metadata', types, ['total: number', 'offset: number', 'hasMore: boolean'])
requireText('News module pagination response', module, [
  "$this->request->integer('offset', 0)",
  '$this->repository->countPosts($includeDrafts)',
  "'total' => $total",
  "'hasMore' => $loaded < $total",
])
requireText('News repository pagination', repository, [
  'public function listPosts(int $limit, int $offset, string $viewerUuid, bool $includeDrafts): array',
  "'LIMIT ' . $limit . ' OFFSET ' . $offset",
  'public function countPosts(bool $includeDrafts): int',
])
requireText('News archive fallback route', router, ["route.name === 'news-list'", "path: '/news'", "viewModules.get('NewsListView')"])
requireText('News archive styling', styles, ['.news-feed__actions', '.news-archive__footer'])

if (failures.length) {
  console.error('News archive contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log('News archive contract passed: the home feed links to /news and the archive paginates the complete publication list.')
