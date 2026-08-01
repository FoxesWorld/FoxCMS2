<script setup lang="ts">
import { computed } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import type { SlideRouteOption, SliderSettings } from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  settings: SliderSettings
  routes: SlideRouteOption[]
  loading: boolean
}>()

const emit = defineEmits<{
  add: []
  remove: [index: number]
  move: [index: number, direction: number]
  upload: [index: number, file: File]
  save: []
}>()

const enabledCount = computed(() => props.settings.slides.filter((slide) => slide.enabled).length)

function preview(image: string): string {
  if (image.startsWith('/')) return image
  return themeAsset(appBootstrap, image.replace(/^assets\//, ''))
}

function selectImage(index: number, event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (file) emit('upload', index, file)
}
</script>

<template>
  <section class="admin-section admin-slides">
    <header class="admin-slides__header">
      <div>
        <span class="eyebrow">Theme data · JSON</span>
        <h2>Слайды главной страницы</h2>
        <p>Конфигурация сохраняется в <code>data/slides.json</code>. Порядок карточек соответствует порядку показа.</p>
      </div>
      <div class="admin-slides__summary">
        <strong>{{ settings.slides.length }}</strong>
        <span>всего · {{ enabledCount }} включено</span>
      </div>
    </header>

    <form class="admin-slides__form" @submit.prevent="emit('save')">
      <section class="admin-slides__settings">
        <label>
          <span>Надпись над заголовком</span>
          <input v-model.trim="settings.eyebrow" type="text" maxlength="100" placeholder="FoxesCraft — новая глава">
        </label>
        <label>
          <span>Автопереключение, мс</span>
          <input v-model.number="settings.autoplayMs" type="number" min="0" max="60000" step="500">
          <small>От 3000 до 60000. Значение 0 отключает автопереключение.</small>
        </label>
      </section>

      <div class="admin-slides__toolbar">
        <button class="button button--ghost" type="button" @click="emit('add')">
          <i class="fa-solid fa-plus" aria-hidden="true" />
          <span>Добавить слайд</span>
        </button>
        <button class="button button--primary" type="submit" :disabled="loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
          <span>{{ loading ? 'Сохранение…' : 'Сохранить все слайды' }}</span>
        </button>
      </div>

      <div v-if="settings.slides.length" class="admin-slides__list">
        <article v-for="(slide, index) in settings.slides" :key="slide.id" class="admin-slide" :class="{ 'is-disabled': !slide.enabled }">
          <header class="admin-slide__header">
            <div class="admin-slide__preview">
              <img v-if="slide.image" :src="preview(slide.image)" alt="">
              <i v-else class="fa-solid fa-image" aria-hidden="true" />
            </div>
            <div>
              <strong>{{ slide.title || `Слайд ${index + 1}` }}</strong>
              <small>{{ slide.id }}</small>
            </div>
            <label class="admin-slide__enabled">
              <input v-model="slide.enabled" type="checkbox">
              <span>Показывать</span>
            </label>
            <div class="admin-slide__order">
              <button type="button" :disabled="index === 0" title="Поднять" @click="emit('move', index, -1)"><i class="fa-solid fa-arrow-up" aria-hidden="true" /></button>
              <button type="button" :disabled="index === settings.slides.length - 1" title="Опустить" @click="emit('move', index, 1)"><i class="fa-solid fa-arrow-down" aria-hidden="true" /></button>
              <button class="danger" type="button" title="Удалить" @click="emit('remove', index)"><i class="fa-solid fa-trash-can" aria-hidden="true" /></button>
            </div>
          </header>

          <div class="admin-slide__fields">
            <label>
              <span>ID</span>
              <input v-model.trim="slide.id" type="text" minlength="2" maxlength="64" pattern="[a-z][a-z0-9-]{1,63}" required>
            </label>
            <label>
              <span>Заголовок</span>
              <input v-model.trim="slide.title" type="text" maxlength="160" required>
            </label>
            <label class="admin-slide__wide">
              <span>Описание</span>
              <textarea v-model.trim="slide.description" rows="3" maxlength="600" />
            </label>

            <label class="admin-slide__wide">
              <span>Изображение</span>
              <div class="admin-slide__image-field">
                <input v-model.trim="slide.image" type="text" maxlength="512" required placeholder="img/slides/slide1.png или /uploads/slides/...">
                <label class="button button--ghost admin-slide__upload">
                  <input type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/avif" :disabled="loading" @change="selectImage(index, $event)">
                  <i class="fa-solid fa-upload" aria-hidden="true" />
                  <span>Загрузить</span>
                </label>
              </div>
              <small>Ресурс темы указывается относительно <code>assets/</code>. Загруженный файл сохраняется в <code>/uploads/slides/</code>.</small>
            </label>

            <label>
              <span>Основной маршрут</span>
              <select v-model="slide.route" required>
                <option v-for="route in routes" :key="route.name" :value="route.name">{{ route.title }} — {{ route.path }}</option>
              </select>
            </label>
            <label>
              <span>Текст основной кнопки</span>
              <input v-model.trim="slide.action" type="text" maxlength="80" required>
            </label>
            <label>
              <span>Дополнительный маршрут</span>
              <select v-model="slide.secondaryRoute">
                <option value="">Без дополнительной кнопки</option>
                <option v-for="route in routes" :key="route.name" :value="route.name">{{ route.title }} — {{ route.path }}</option>
              </select>
            </label>
            <label>
              <span>Текст дополнительной кнопки</span>
              <input v-model.trim="slide.secondaryAction" type="text" maxlength="80" :required="Boolean(slide.secondaryRoute)" :disabled="!slide.secondaryRoute">
            </label>
          </div>
        </article>
      </div>

      <div v-else class="empty-state">Слайдов нет. Добавьте первый слайд.</div>

      <div class="admin-slides__footer">
        <button class="button button--primary" type="submit" :disabled="loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
          <span>{{ loading ? 'Сохранение…' : 'Сохранить все слайды' }}</span>
        </button>
      </div>
    </form>
  </section>
</template>
