<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import UiCheckbox from '@/components/UiCheckbox.vue'
import ImageUploadField from '@/components/ImageUploadField.vue'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import type { SlideDraft, SlideRouteOption, SliderSettings } from '@modules/AdminPanel/client/useAdminPanel'

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

const selectedSlide = ref<SlideDraft | null>(null)
const enabledCount = computed(() => props.settings.slides.filter((slide) => slide.enabled).length)
const selectedIndex = computed(() => selectedSlide.value ? props.settings.slides.indexOf(selectedSlide.value) : -1)
const selectedImage = computed(() => selectedSlide.value?.image ? preview(selectedSlide.value.image) : '')
const selectedPrimaryRoute = computed(() => routeLabel(selectedSlide.value?.route ?? ''))
const selectedSecondaryRoute = computed(() => routeLabel(selectedSlide.value?.secondaryRoute ?? ''))

watch(
  () => props.settings.slides.slice(),
  (slides) => {
    if (!slides.length) {
      selectedSlide.value = null
      return
    }
    if (!selectedSlide.value || !slides.includes(selectedSlide.value)) selectedSlide.value = slides[0] ?? null
  },
  { immediate: true },
)

function preview(image: string): string {
  if (image.startsWith('/')) return image
  return themeAsset(appBootstrap, image.replace(/^assets\//, ''))
}

function routeLabel(name: string): string {
  if (!name) return 'Не выбран'
  const route = props.routes.find((entry) => entry.name === name)
  return route ? `${route.title} · ${route.path}` : name
}

function selectSlide(slide: SlideDraft): void {
  selectedSlide.value = slide
}

async function addSlide(): Promise<void> {
  emit('add')
  await nextTick()
  selectedSlide.value = props.settings.slides.at(-1) ?? null
}

async function removeSlide(index: number): Promise<void> {
  const current = selectedSlide.value
  emit('remove', index)
  await nextTick()
  if (current && !props.settings.slides.includes(current)) {
    selectedSlide.value = props.settings.slides[Math.min(index, props.settings.slides.length - 1)] ?? null
  }
}

function moveSlide(index: number, direction: number): void {
  emit('move', index, direction)
}

function selectImage(index: number, file: File): void {
  emit('upload', index, file)
}
</script>

<template>
  <section class="admin-section admin-slides">
    <header class="admin-slides__header">
      <div>
        <span class="eyebrow">Theme data · JSON</span>
        <h2>Слайды главной страницы</h2>
        <p>Слева расположен порядок показа. Справа — полная конфигурация и предварительный вид выбранного слайда.</p>
      </div>
      <div class="admin-slides__summary" aria-label="Статистика слайдов">
        <div><strong>{{ settings.slides.length }}</strong><span>всего</span></div>
        <div><strong>{{ enabledCount }}</strong><span>показывается</span></div>
      </div>
    </header>

    <form class="admin-slides__form" @submit.prevent="emit('save')">
      <section class="admin-slides__settings">
        <header>
          <span class="admin-slides__settings-icon"><i class="fa-solid fa-sliders" aria-hidden="true" /></span>
          <div>
            <strong>Общие настройки</strong>
            <small>Применяются ко всей последовательности слайдов</small>
          </div>
        </header>
        <label>
          <span>Надпись над заголовком</span>
          <input v-model.trim="settings.eyebrow" type="text" maxlength="100" placeholder="FoxesCraft — новая глава">
        </label>
        <label>
          <span>Автопереключение, мс</span>
          <input v-model.number="settings.autoplayMs" type="number" min="0" max="60000" step="500">
          <small>0 отключает автоматическую смену; рабочий диапазон — 3000–60000 мс.</small>
        </label>
      </section>

      <div class="admin-slides__toolbar">
        <button class="button button--ghost" type="button" @click="addSlide">
          <i class="fa-solid fa-plus" aria-hidden="true" />
          <span>Добавить слайд</span>
        </button>
        <button class="button button--primary" type="submit" :disabled="loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
          <span>{{ loading ? 'Сохранение…' : 'Сохранить изменения' }}</span>
        </button>
      </div>

      <div v-if="settings.slides.length" class="admin-slides__workspace">
        <aside class="admin-slides__master">
          <header class="admin-slides__master-header">
            <div>
              <strong>Порядок показа</strong>
              <small>Выберите слайд для редактирования</small>
            </div>
            <span>{{ settings.slides.length }}</span>
          </header>

          <div class="admin-slides__list">
            <article
              v-for="(slide, index) in settings.slides"
              :key="slide.id"
              class="admin-slide-item"
              :class="{ 'is-selected': selectedSlide === slide, 'is-disabled': !slide.enabled }"
            >
              <button class="admin-slide-item__select" type="button" @click="selectSlide(slide)">
                <span class="admin-slide-item__number">{{ String(index + 1).padStart(2, '0') }}</span>
                <span class="admin-slide-item__preview">
                  <img v-if="slide.image" :src="preview(slide.image)" alt="">
                  <i v-else class="fa-solid fa-image" aria-hidden="true" />
                </span>
                <span class="admin-slide-item__copy">
                  <strong>{{ slide.title || `Слайд ${index + 1}` }}</strong>
                  <small>{{ slide.id }}</small>
                  <span class="admin-slide-item__state">
                    <i class="fa-solid" :class="slide.enabled ? 'fa-circle-check' : 'fa-circle-xmark'" aria-hidden="true" />
                    {{ slide.enabled ? 'Показывается' : 'Скрыт' }}
                  </span>
                </span>
              </button>

              <div class="admin-slide-item__order" aria-label="Изменить порядок">
                <button type="button" :disabled="index === 0" title="Поднять" @click="moveSlide(index, -1)">
                  <i class="fa-solid fa-arrow-up" aria-hidden="true" />
                </button>
                <button type="button" :disabled="index === settings.slides.length - 1" title="Опустить" @click="moveSlide(index, 1)">
                  <i class="fa-solid fa-arrow-down" aria-hidden="true" />
                </button>
              </div>
            </article>
          </div>

          <button class="admin-slides__add-card" type="button" @click="addSlide">
            <i class="fa-solid fa-plus" aria-hidden="true" />
            <span><strong>Новый слайд</strong><small>Добавить в конец последовательности</small></span>
          </button>
        </aside>

        <section v-if="selectedSlide && selectedIndex >= 0" class="admin-slides__detail">
          <header class="admin-slide-editor__header">
            <div>
              <span class="eyebrow">Слайд {{ selectedIndex + 1 }} из {{ settings.slides.length }}</span>
              <h3>{{ selectedSlide.title || 'Без названия' }}</h3>
              <p><code>{{ selectedSlide.id }}</code></p>
            </div>
            <button class="admin-slide-editor__delete" type="button" @click="removeSlide(selectedIndex)">
              <i class="fa-solid fa-trash-can" aria-hidden="true" />
              <span>Удалить</span>
            </button>
          </header>

          <div class="admin-slide-editor__preview" :class="{ 'has-image': selectedImage, 'is-disabled': !selectedSlide.enabled }">
            <img v-if="selectedImage" :src="selectedImage" alt="">
            <div v-else class="admin-slide-editor__preview-empty">
              <i class="fa-solid fa-image" aria-hidden="true" />
              <span>Изображение не выбрано</span>
            </div>
            <div class="admin-slide-editor__preview-overlay" />
            <div class="admin-slide-editor__preview-content">
              <span class="eyebrow">{{ settings.eyebrow || 'FoxesCraft' }}</span>
              <h4>{{ selectedSlide.title || 'Заголовок слайда' }}</h4>
              <p>{{ selectedSlide.description || 'Описание появится здесь после заполнения поля.' }}</p>
              <div>
                <span class="button button--primary">{{ selectedSlide.action || 'Основное действие' }}</span>
                <span v-if="selectedSlide.secondaryRoute" class="button button--ghost">{{ selectedSlide.secondaryAction || 'Дополнительное действие' }}</span>
              </div>
            </div>
            <span v-if="!selectedSlide.enabled" class="admin-slide-editor__preview-badge">Слайд скрыт</span>
          </div>

          <UiCheckbox
            v-model="selectedSlide.enabled"
            class="admin-slide-toggle"
            variant="switch"
            label="Показывать слайд"
            :description="selectedSlide.enabled
              ? 'Слайд участвует в показе на главной странице'
              : 'Слайд сохранён, но исключён из показа'"
          />

          <div class="admin-slide-editor__fields">
            <label>
              <span>ID слайда</span>
              <input v-model.trim="selectedSlide.id" type="text" minlength="2" maxlength="64" pattern="[a-z][a-z0-9-]{1,63}" required>
              <small>Стабильный технический идентификатор: латиница, цифры и дефисы.</small>
            </label>
            <label>
              <span>Заголовок</span>
              <input v-model.trim="selectedSlide.title" type="text" maxlength="160" required>
            </label>
            <label class="admin-slide-editor__wide">
              <span>Описание</span>
              <textarea v-model.trim="selectedSlide.description" rows="4" maxlength="600" />
            </label>

            <div class="admin-slide-editor__wide admin-slide-editor__image-field">
              <label>
                <span>Путь изображения</span>
                <input v-model.trim="selectedSlide.image" type="text" maxlength="512" required placeholder="img/slides/slide1.png или /uploads/slides/...">
                <small>Ресурс темы задаётся относительно <code>assets/</code>; загрузки сохраняются в <code>/uploads/slides/</code>.</small>
              </label>
              <ImageUploadField
                title="Изображение слайда"
                description="Перетащите новую обложку или выберите файл"
                :preview="selectedImage"
                preview-mode="none"
                :editor-aspect-ratio="false"
                accept="image/png,image/jpeg,image/webp,image/gif,image/avif"
                :allowed-types="['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/avif']"
                :maximum-bytes="12_582_912"
                :minimum-width="320"
                :minimum-height="320"
                :maximum-width="8192"
                :maximum-height="8192"
                :disabled="loading"
                :uploading="loading"
                hint="JPEG, PNG, WebP, GIF или AVIF · до 12 МиБ"
                choose-label="Выбрать изображение"
                replace-label="Заменить изображение"
                clear-label="Очистить изображение"
                @select="selectImage(selectedIndex, $event)"
                @clear="selectedSlide.image = ''"
              />
            </div>

            <section class="admin-slide-editor__actions">
              <header>
                <i class="fa-solid fa-arrow-pointer" aria-hidden="true" />
                <div><strong>Основное действие</strong><small>{{ selectedPrimaryRoute }}</small></div>
              </header>
              <label>
                <span>Маршрут</span>
                <select v-model="selectedSlide.route" required>
                  <option v-for="route in routes" :key="route.name" :value="route.name">{{ route.title }} — {{ route.path }}</option>
                </select>
              </label>
              <label>
                <span>Текст кнопки</span>
                <input v-model.trim="selectedSlide.action" type="text" maxlength="80" required>
              </label>
            </section>

            <section class="admin-slide-editor__actions admin-slide-editor__actions--secondary" :class="{ 'is-empty': !selectedSlide.secondaryRoute }">
              <header>
                <i class="fa-solid fa-code-branch" aria-hidden="true" />
                <div><strong>Дополнительное действие</strong><small>{{ selectedSecondaryRoute }}</small></div>
              </header>
              <label>
                <span>Маршрут</span>
                <select v-model="selectedSlide.secondaryRoute">
                  <option value="">Без дополнительной кнопки</option>
                  <option v-for="route in routes" :key="route.name" :value="route.name">{{ route.title }} — {{ route.path }}</option>
                </select>
              </label>
              <label>
                <span>Текст кнопки</span>
                <input v-model.trim="selectedSlide.secondaryAction" type="text" maxlength="80" :required="Boolean(selectedSlide.secondaryRoute)" :disabled="!selectedSlide.secondaryRoute">
              </label>
            </section>
          </div>

          <footer class="admin-slide-editor__footer">
            <span><i class="fa-solid fa-circle-info" aria-hidden="true" /> Изменения применятся после сохранения всей конфигурации.</span>
            <button class="button button--primary" type="submit" :disabled="loading">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
              <span>{{ loading ? 'Сохранение…' : 'Сохранить слайды' }}</span>
            </button>
          </footer>
        </section>
      </div>

      <div v-else class="admin-slides__empty">
        <i class="fa-solid fa-images" aria-hidden="true" />
        <strong>Слайдов пока нет</strong>
        <p>Создайте первый слайд, чтобы настроить главный экран.</p>
        <button class="button button--primary" type="button" @click="addSlide"><i class="fa-solid fa-plus" aria-hidden="true" /><span>Добавить слайд</span></button>
      </div>
    </form>
  </section>
</template>
