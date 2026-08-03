<script setup lang="ts">
import { computed } from 'vue'
import ImageUploadField from '@/components/ImageUploadField.vue'
import type { SiteSettings } from '@modules/AdminPanel/client/useAdminPanel'
import SeoTagifyInput from './SeoTagifyInput.vue'

const props = defineProps<{
  settings: SiteSettings
  loading: boolean
  updatedAt: string
  storageReady: boolean
  imageUploading: boolean
  imageError: string
}>()
const emit = defineEmits<{
  uploadImage: [file: File]
  clearImage: []
  save: []
}>()

const titlePreview = computed(() => props.settings.titleTemplate
  .replaceAll('%page%', 'Новости проекта')
  .replaceAll('%site%', props.settings.siteTitle || 'FoxesCraft'))
const keywordCount = computed(() => props.settings.keywords
  .split(/[,;\n]+/)
  .map((item) => item.trim())
  .filter(Boolean).length)
const canonicalPreview = computed(() => props.settings.canonicalUrl || 'Канонический URL не задан')
</script>

<template>
  <section class="admin-section site-settings-admin">
    <header class="site-settings-admin__header">
      <div>
        <span class="eyebrow">Site identity & search</span>
        <h2>Настройки сайта и SEO</h2>
        <p>Параметры применяются на сервере до запуска Vue: поисковые роботы сразу получают корректные Title, Description, Open Graph, canonical и JSON-LD.</p>
      </div>
      <span class="site-settings-admin__status" :class="{ ready: storageReady }">
        {{ storageReady ? 'Хранилище подключено' : 'Требуется миграция 009' }}
      </span>
    </header>

    <div class="site-settings-grid">
      <section class="site-settings-card site-settings-card--wide">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-pen-to-square" /></span>
          <div><h3>Идентичность сайта</h3><p>Название, статус и основной текст, используемый в интерфейсе и поисковой выдаче.</p></div>
        </header>
        <div class="site-settings-fields site-settings-fields--two">
          <label><span>Название сайта</span><input v-model="settings.siteTitle" maxlength="120" placeholder="FoxesCraft"><small>{{ settings.siteTitle.length }}/120</small></label>
          <label><span>Статус проекта</span><input v-model="settings.siteStatus" maxlength="120" placeholder="IN DEVELOPMENT"><small>{{ settings.siteStatus.length }}/120</small></label>
          <label class="site-settings-field--full"><span>Описание сайта</span><textarea v-model="settings.siteDesc" rows="4" maxlength="320" placeholder="Краткое и содержательное описание проекта."></textarea><small>{{ settings.siteDesc.length }}/320</small></label>
          <label><span>Автор / организация</span><input v-model="settings.author" maxlength="120" placeholder="FoxesCraft"></label>
          <label><span>Цвет браузера</span><div class="site-settings-color"><input v-model="settings.themeColor" maxlength="7" placeholder="#152019"><input v-model="settings.themeColor" type="color" aria-label="Выбрать цвет темы"></div></label>
        </div>
      </section>

      <section class="site-settings-card">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-newspaper" /></span>
          <div><h3>Title страниц</h3><p>Шаблон применяется при переходах внутри сайта.</p></div>
        </header>
        <div class="site-settings-fields">
          <label><span>Title главной страницы</span><input v-model="settings.homeTitle" maxlength="180" placeholder="FoxesCraft — игровая студия"></label>
          <label><span>Шаблон внутренних страниц</span><input v-model="settings.titleTemplate" maxlength="180" placeholder="%page% — %site%"><small>Переменные: <code>%page%</code> и <code>%site%</code></small></label>
          <div class="site-settings-preview"><span>Предпросмотр</span><strong>{{ titlePreview }}</strong></div>
        </div>
      </section>

      <section class="site-settings-card">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-magnifying-glass" /></span>
          <div><h3>Индексация</h3><p>Canonical, поисковые теги и директивы роботов.</p></div>
        </header>
        <div class="site-settings-fields">
          <label><span>Канонический URL</span><input v-model="settings.canonicalUrl" type="url" maxlength="2048" placeholder="https://foxescraft.ru"><small>{{ canonicalPreview }}</small></label>
          <label><span>Robots</span><select v-model="settings.robots"><option value="index,follow">index, follow</option><option value="index,nofollow">index, nofollow</option><option value="noindex,follow">noindex, follow</option><option value="noindex,nofollow">noindex, nofollow</option></select></label>
          <label class="site-settings-field--full"><span>Поисковые теги</span><SeoTagifyInput v-model="settings.keywords" placeholder="Введите тег и нажмите Enter" /><small>{{ keywordCount }} тегов · количество не ограничено</small></label>
          <div class="site-settings-inline">
            <label><span>Язык HTML</span><input v-model="settings.lang" maxlength="8" placeholder="ru"></label>
            <label><span>Open Graph locale</span><input v-model="settings.locale" maxlength="8" placeholder="ru_RU"></label>
          </div>
        </div>
      </section>

      <section class="site-settings-card site-settings-card--wide">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-image" /></span>
          <div><h3>Социальные карточки</h3><p>Как ссылка выглядит в Discord, Telegram, VK, X и других сервисах.</p></div>
        </header>
        <div class="site-settings-fields site-settings-fields--two">
          <label><span>Название Open Graph</span><input v-model="settings.ogSiteName" maxlength="120" placeholder="FoxesCraft"></label>
          <label><span>Заголовок карточки</span><input v-model="settings.ogTitle" maxlength="180" placeholder="FoxesCraft"></label>
          <label class="site-settings-field--full"><span>Описание карточки</span><textarea v-model="settings.ogDescription" rows="3" maxlength="320" placeholder="Описание для предпросмотра ссылки."></textarea></label>
          <div class="site-settings-field--full site-settings-social-image">
            <label>
              <span>Open Graph image URL</span>
              <input v-model.trim="settings.ogImage" maxlength="2048" placeholder="/uploads/site/social-card.webp">
              <small>Значение атрибута <code>content</code> для <code>&lt;meta property=&quot;og:image&quot;&gt;</code>. Можно указать абсолютный URL или путь от корня сайта.</small>
            </label>
            <ImageUploadField
              title="Изображение социальной карточки"
              description="Загрузите изображение для Open Graph и Twitter Card"
              :preview="settings.ogImage"
              preview-alt="Предпросмотр социальной карточки"
              preview-mode="wide"
              preview-fit="cover"
              :editor-aspect-ratio="1200 / 630"
              accept="image/jpeg,image/png,image/webp"
              :allowed-types="['image/jpeg', 'image/png', 'image/webp']"
              :maximum-bytes="12_582_912"
              :minimum-width="600"
              :minimum-height="315"
              :maximum-width="8192"
              :maximum-height="8192"
              :disabled="loading"
              :uploading="imageUploading"
              :error="imageError"
              hint="Рекомендуемый размер 1200×630 · JPEG, PNG или WebP · до 12 МиБ"
              choose-label="Загрузить изображение"
              replace-label="Заменить изображение"
              clear-label="Очистить og:image"
              @select="emit('uploadImage', $event)"
              @clear="emit('clearImage')"
            />
          </div>
          <label><span>Тип Twitter Card</span><select v-model="settings.twitterCard"><option value="summary_large_image">Большая карточка</option><option value="summary">Компактная карточка</option></select></label>
          <label><span>Favicon</span><input v-model="settings.faviconUrl" maxlength="2048" placeholder="/favicon.ico"></label>
          <label><span>Аккаунт сайта</span><input v-model="settings.twitterSite" maxlength="31" placeholder="@foxescraft"></label>
          <label><span>Автор публикации</span><input v-model="settings.twitterCreator" maxlength="31" placeholder="@author"></label>
        </div>
      </section>

      <section class="site-settings-card site-settings-card--wide">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-circle-check" /></span>
          <div><h3>Подтверждение поисковых систем</h3><p>Вставляйте только значение атрибута content, не весь HTML-тег.</p></div>
        </header>
        <div class="site-settings-fields site-settings-fields--three">
          <label><span>Google Search Console</span><input v-model="settings.googleVerification" maxlength="180" autocomplete="off" placeholder="verification token"></label>
          <label><span>Яндекс Вебмастер</span><input v-model="settings.yandexVerification" maxlength="180" autocomplete="off" placeholder="verification token"></label>
          <label><span>Bing Webmaster</span><input v-model="settings.bingVerification" maxlength="180" autocomplete="off" placeholder="verification token"></label>
        </div>
      </section>
    </div>

    <footer class="site-settings-admin__footer">
      <span>{{ updatedAt ? `Последнее изменение: ${updatedAt}` : 'Используются значения окружения; настройки ещё не сохранялись.' }}</span>
      <button type="button" class="button button--primary" :disabled="loading" @click="emit('save')">
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" />
        {{ loading ? 'Сохранение…' : 'Сохранить настройки' }}
      </button>
    </footer>
  </section>
</template>
