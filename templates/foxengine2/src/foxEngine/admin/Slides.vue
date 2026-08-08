<script setup lang="ts">
import { t } from '@/i18n'

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
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
  reorder: [fromIndex: number, toIndex: number]
  upload: [index: number, file: File]
  save: []
}>()

const selectedSlide = ref<SlideDraft | null>(null)
const slideList = ref<HTMLElement | null>(null)
const draggedSlide = ref<SlideDraft | null>(null)
const dragPointerId = ref<number | null>(null)
const dragFromIndex = ref(-1)
const dragTargetIndex = ref(-1)
const dragStartY = ref(0)
const dragCurrentY = ref(0)
const dragMinTranslateY = ref(0)
const dragMaxTranslateY = ref(0)
const dragHandle = ref<HTMLElement | null>(null)
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
  if (!name) return t('theme.foxengine.admin.slides.060')
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

function reorderSlide(fromIndex: number, toIndex: number): void {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return
  if (fromIndex >= props.settings.slides.length || toIndex >= props.settings.slides.length) return
  emit('reorder', fromIndex, toIndex)
}

function slideElements(list: HTMLElement): HTMLElement[] {
  return Array.from(list.children).filter((child): child is HTMLElement =>
    child instanceof HTMLElement && child.matches('.admin-slide-item[data-slide-index]'))
}

function dragTranslateY(): number {
  if (!draggedSlide.value) return 0
  const desired = dragCurrentY.value - dragStartY.value
  return Math.min(dragMaxTranslateY.value, Math.max(dragMinTranslateY.value, desired))
}

function draggedSlideStyle(slide: SlideDraft): Record<string, string> | undefined {
  if (draggedSlide.value !== slide) return undefined
  return {
    transform: `translate3d(0, ${dragTranslateY()}px, 0)`,
  }
}

function startSlideDrag(event: PointerEvent, slide: SlideDraft): void {
  if (event.pointerType === 'mouse' && event.button !== 0) return
  const handle = event.currentTarget
  const list = slideList.value
  if (!(handle instanceof HTMLElement) || !list) return

  const index = props.settings.slides.indexOf(slide)
  if (index < 0) return

  event.preventDefault()
  event.stopPropagation()
  draggedSlide.value = slide
  dragPointerId.value = event.pointerId
  dragFromIndex.value = index
  dragTargetIndex.value = index
  dragStartY.value = event.clientY
  dragCurrentY.value = event.clientY
  const listBounds = list.getBoundingClientRect()
  const card = handle.closest<HTMLElement>('.admin-slide-item')
  const cardBounds = card?.getBoundingClientRect()
  dragMinTranslateY.value = cardBounds ? listBounds.top - cardBounds.top : 0
  dragMaxTranslateY.value = cardBounds ? listBounds.bottom - cardBounds.bottom : 0
  dragHandle.value = handle
  handle.setPointerCapture?.(event.pointerId)

  window.addEventListener('pointermove', dragSlide, { passive: false })
  window.addEventListener('pointerup', finishSlideDrag, { passive: false })
  window.addEventListener('pointercancel', cancelSlideDrag, { passive: false })
}

function updateDragTarget(clientY: number): void {
  const list = slideList.value
  const slide = draggedSlide.value
  if (!list || !slide) return

  const currentIndex = props.settings.slides.indexOf(slide)
  const entries = slideElements(list).filter((element) =>
    Number.parseInt(element.dataset.slideIndex ?? '', 10) !== currentIndex)
  if (!entries.length) {
    dragTargetIndex.value = currentIndex
    return
  }

  let insertionIndex = 0
  for (const element of entries) {
    const rect = element.getBoundingClientRect()
    if (clientY >= rect.top + rect.height / 2) insertionIndex += 1
  }
  dragTargetIndex.value = Math.max(0, Math.min(insertionIndex, props.settings.slides.length - 1))
}

function dragSlide(event: PointerEvent): void {
  const list = slideList.value
  if (!draggedSlide.value || !list || dragPointerId.value !== event.pointerId) return
  event.preventDefault()

  const bounds = list.getBoundingClientRect()
  dragCurrentY.value = event.clientY
  const boundedY = Math.min(bounds.bottom, Math.max(bounds.top, event.clientY))

  const edge = Math.min(48, Math.max(28, bounds.height * 0.12))
  if (event.clientY < bounds.top + edge) list.scrollTop -= 14
  else if (event.clientY > bounds.bottom - edge) list.scrollTop += 14

  updateDragTarget(boundedY)
}

function resetSlideDrag(): void {
  const pointerId = dragPointerId.value
  const handle = dragHandle.value
  if (pointerId !== null && handle?.hasPointerCapture?.(pointerId)) {
    handle.releasePointerCapture(pointerId)
  }
  window.removeEventListener('pointermove', dragSlide)
  window.removeEventListener('pointerup', finishSlideDrag)
  window.removeEventListener('pointercancel', cancelSlideDrag)
  dragPointerId.value = null
  draggedSlide.value = null
  dragFromIndex.value = -1
  dragTargetIndex.value = -1
  dragStartY.value = 0
  dragCurrentY.value = 0
  dragMinTranslateY.value = 0
  dragMaxTranslateY.value = 0
  dragHandle.value = null
}

function finishSlideDrag(event: PointerEvent): void {
  if (dragPointerId.value !== event.pointerId) return
  event.preventDefault()
  const fromIndex = dragFromIndex.value
  const toIndex = dragTargetIndex.value
  resetSlideDrag()
  reorderSlide(fromIndex, toIndex)
}

function cancelSlideDrag(event: PointerEvent): void {
  if (dragPointerId.value !== event.pointerId) return
  resetSlideDrag()
}

onBeforeUnmount(resetSlideDrag)

function reorderSlideByKeyboard(event: KeyboardEvent, index: number): void {
  if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return
  event.preventDefault()
  reorderSlide(index, index + (event.key === 'ArrowUp' ? -1 : 1))
}

function selectImage(index: number, file: File): void {
  emit('upload', index, file)
}
</script>

<template>
  <section class="admin-section admin-slides">
    <header class="admin-slides__header">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.slides.001') }}</span>
        <h2>{{ t('theme.foxengine.admin.slides.002') }}</h2>
        <p>{{ t('theme.foxengine.admin.slides.003') }}</p>
      </div>
      <div class="admin-slides__summary" :aria-label="t('theme.foxengine.admin.slides.004')">
        <div><strong>{{ settings.slides.length }}</strong><span>{{ t('theme.foxengine.admin.slides.005') }}</span></div>
        <div><strong>{{ enabledCount }}</strong><span>{{ t('theme.foxengine.admin.slides.006') }}</span></div>
      </div>
    </header>

    <form class="admin-slides__form" @submit.prevent="emit('save')">
      <section class="admin-slides__settings">
        <header>
          <span class="admin-slides__settings-icon"><i class="fa-solid fa-sliders" aria-hidden="true" /></span>
          <div>
            <strong>{{ t('theme.foxengine.admin.slides.007') }}</strong>
            <small>{{ t('theme.foxengine.admin.slides.008') }}</small>
          </div>
        </header>
        <label>
          <span>{{ t('theme.foxengine.admin.slides.009') }}</span>
          <input v-model.trim="settings.eyebrow" type="text" maxlength="100" :placeholder="t('theme.foxengine.admin.slides.010')">
        </label>
        <label>
          <span>{{ t('theme.foxengine.admin.slides.011') }}</span>
          <input v-model.number="settings.autoplayMs" type="number" min="0" max="60000" step="500">
          <small>{{ t('theme.foxengine.admin.slides.012') }}</small>
        </label>
      </section>

      <div class="admin-slides__toolbar">
        <button class="button button--ghost" type="button" @click="addSlide">
          <i class="fa-solid fa-plus" aria-hidden="true" />
          <span>{{ t('theme.foxengine.admin.slides.013') }}</span>
        </button>
        <button class="button button--primary" type="submit" :disabled="loading">
          <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
          <span>{{ loading ? t('theme.foxengine.admin.slides.014') : t('theme.foxengine.admin.slides.015') }}</span>
        </button>
      </div>

      <div v-if="settings.slides.length" class="admin-slides__workspace">
        <aside class="admin-slides__master">
          <header class="admin-slides__master-header">
            <div>
              <strong>{{ t('theme.foxengine.admin.slides.016') }}</strong>
              <small>{{ t('theme.foxengine.admin.slides.017') }}</small>
            </div>
            <span>{{ settings.slides.length }}</span>
          </header>

          <div ref="slideList" class="admin-slides__list" :class="{ 'is-reordering': draggedSlide }" :aria-label="t('theme.foxengine.admin.slides.021')">
            <article
              v-for="(slide, index) in settings.slides"
              :key="slide.id"
              class="admin-slide-item"
              :class="{
                'is-selected': selectedSlide === slide,
                'is-disabled': !slide.enabled,
                'is-dragging': draggedSlide === slide,
                'is-drop-target': draggedSlide && draggedSlide !== slide && dragTargetIndex === index,
              }"
              :style="draggedSlideStyle(slide)"
              :data-slide-index="index"
            >
              <button class="admin-slide-item__select" type="button" @click="selectSlide(slide)">
                <span class="admin-slide-item__number">{{ String(index + 1).padStart(2, '0') }}</span>
                <span class="admin-slide-item__preview">
                  <img v-if="slide.image" :src="preview(slide.image)" alt="">
                  <i v-else class="fa-solid fa-image" aria-hidden="true" />
                </span>
                <span class="admin-slide-item__copy">
                  <strong>{{ slide.title || t('theme.foxengine.admin.slides.018', [index + 1]) }}</strong>
                  <small>{{ slide.id }}</small>
                  <span class="admin-slide-item__state">
                    <i class="fa-solid" :class="slide.enabled ? 'fa-circle-check' : 'fa-circle-xmark'" aria-hidden="true" />
                    {{ slide.enabled ? t('theme.foxengine.admin.slides.019') : t('theme.foxengine.admin.slides.020') }}
                  </span>
                </span>
              </button>

              <button
                class="admin-slide-item__drag-handle"
                type="button"
                :aria-label="draggedSlide === slide ? t('theme.foxengine.admin.slides.023') : t('theme.foxengine.admin.slides.022')"
                :title="draggedSlide === slide ? t('theme.foxengine.admin.slides.023') : t('theme.foxengine.admin.slides.022')"
                @pointerdown="startSlideDrag($event, slide)"
                @keydown="reorderSlideByKeyboard($event, index)"
              >
                <i class="fa-solid fa-grip-vertical" aria-hidden="true" />
              </button>
            </article>
          </div>

          <button class="admin-slides__add-card" type="button" @click="addSlide">
            <i class="fa-solid fa-plus" aria-hidden="true" />
            <span><strong>{{ t('theme.foxengine.admin.slides.024') }}</strong><small>{{ t('theme.foxengine.admin.slides.025') }}</small></span>
          </button>
        </aside>

        <section v-if="selectedSlide && selectedIndex >= 0" class="admin-slides__detail">
          <header class="admin-slide-editor__header">
            <div>
              <span class="eyebrow">{{ t('theme.foxengine.admin.slides.026') }} {{ selectedIndex + 1 }} {{ t('theme.foxengine.admin.slides.027') }} {{ settings.slides.length }}</span>
              <h3>{{ selectedSlide.title || t('theme.foxengine.admin.slides.028') }}</h3>
              <p><code>{{ selectedSlide.id }}</code></p>
            </div>
            <button class="admin-slide-editor__delete" type="button" @click="removeSlide(selectedIndex)">
              <i class="fa-solid fa-trash-can" aria-hidden="true" />
              <span>{{ t('theme.foxengine.admin.slides.029') }}</span>
            </button>
          </header>

          <div class="admin-slide-editor__preview" :class="{ 'has-image': selectedImage, 'is-disabled': !selectedSlide.enabled }">
            <img v-if="selectedImage" :src="selectedImage" alt="">
            <div v-else class="admin-slide-editor__preview-empty">
              <i class="fa-solid fa-image" aria-hidden="true" />
              <span>{{ t('theme.foxengine.admin.slides.030') }}</span>
            </div>
            <div class="admin-slide-editor__preview-overlay" />
            <div class="admin-slide-editor__preview-content">
              <span class="eyebrow">{{ settings.eyebrow || 'FoxesCraft' }}</span>
              <h4>{{ selectedSlide.title || t('theme.foxengine.admin.slides.031') }}</h4>
              <p>{{ selectedSlide.description || t('theme.foxengine.admin.slides.032') }}</p>
              <div>
                <span class="button button--primary">{{ selectedSlide.action || t('theme.foxengine.admin.slides.033') }}</span>
                <span v-if="selectedSlide.secondaryRoute" class="button button--ghost">{{ selectedSlide.secondaryAction || t('theme.foxengine.admin.slides.034') }}</span>
              </div>
            </div>
            <span v-if="!selectedSlide.enabled" class="admin-slide-editor__preview-badge">{{ t('theme.foxengine.admin.slides.035') }}</span>
          </div>

          <UiCheckbox
            v-model="selectedSlide.enabled"
            class="admin-slide-toggle"
            variant="switch"
            :label="t('theme.foxengine.admin.slides.036')"
            :description="selectedSlide.enabled
              ? t('theme.foxengine.admin.slides.037')
              : t('theme.foxengine.admin.slides.038')"
          />

          <div class="admin-slide-editor__fields">
            <label>
              <span>{{ t('theme.foxengine.admin.slides.039') }}</span>
              <input v-model.trim="selectedSlide.id" type="text" minlength="2" maxlength="64" pattern="[a-z][a-z0-9-]{1,63}" required>
              <small>{{ t('theme.foxengine.admin.slides.040') }}</small>
            </label>
            <label>
              <span>{{ t('theme.foxengine.admin.slides.041') }}</span>
              <input v-model.trim="selectedSlide.title" type="text" maxlength="160" required>
            </label>
            <label class="admin-slide-editor__wide">
              <span>{{ t('theme.foxengine.admin.slides.042') }}</span>
              <textarea v-model.trim="selectedSlide.description" rows="4" maxlength="600" />
            </label>

            <div class="admin-slide-editor__wide admin-slide-editor__image-field">
              <label>
                <span>{{ t('theme.foxengine.admin.slides.043') }}</span>
                <input v-model.trim="selectedSlide.image" type="text" maxlength="512" required :placeholder="t('theme.foxengine.admin.slides.044')">
                <small>{{ t('theme.foxengine.admin.slides.045') }} <code>assets/</code>{{ t('theme.foxengine.admin.slides.046') }} <code>/uploads/slides/</code>.</small>
              </label>
              <ImageUploadField
                :title="t('theme.foxengine.admin.slides.047')"
                :description="t('theme.foxengine.admin.slides.048')"
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
                :hint="t('theme.foxengine.admin.slides.049')"
                :choose-label="t('theme.foxengine.admin.slides.050')"
                :replace-label="t('theme.foxengine.admin.slides.051')"
                :clear-label="t('theme.foxengine.admin.slides.052')"
                @select="selectImage(selectedIndex, $event)"
                @clear="selectedSlide.image = ''"
              />
            </div>

            <section class="admin-slide-editor__actions">
              <header>
                <i class="fa-solid fa-arrow-pointer" aria-hidden="true" />
                <div><strong>{{ t('theme.foxengine.admin.slides.033') }}</strong><small>{{ selectedPrimaryRoute }}</small></div>
              </header>
              <label>
                <span>{{ t('theme.foxengine.admin.slides.053') }}</span>
                <select v-model="selectedSlide.route" required>
                  <option v-for="route in routes" :key="route.name" :value="route.name">{{ route.title }} — {{ route.path }}</option>
                </select>
              </label>
              <label>
                <span>{{ t('theme.foxengine.admin.slides.054') }}</span>
                <input v-model.trim="selectedSlide.action" type="text" maxlength="80" required>
              </label>
            </section>

            <section class="admin-slide-editor__actions admin-slide-editor__actions--secondary" :class="{ 'is-empty': !selectedSlide.secondaryRoute }">
              <header>
                <i class="fa-solid fa-code-branch" aria-hidden="true" />
                <div><strong>{{ t('theme.foxengine.admin.slides.034') }}</strong><small>{{ selectedSecondaryRoute }}</small></div>
              </header>
              <label>
                <span>{{ t('theme.foxengine.admin.slides.053') }}</span>
                <select v-model="selectedSlide.secondaryRoute">
                  <option value="">{{ t('theme.foxengine.admin.slides.055') }}</option>
                  <option v-for="route in routes" :key="route.name" :value="route.name">{{ route.title }} — {{ route.path }}</option>
                </select>
              </label>
              <label>
                <span>{{ t('theme.foxengine.admin.slides.054') }}</span>
                <input v-model.trim="selectedSlide.secondaryAction" type="text" maxlength="80" :required="Boolean(selectedSlide.secondaryRoute)" :disabled="!selectedSlide.secondaryRoute">
              </label>
            </section>
          </div>

          <footer class="admin-slide-editor__footer">
            <span><i class="fa-solid fa-circle-info" aria-hidden="true" /> {{ t('theme.foxengine.admin.slides.056') }}</span>
            <button class="button button--primary" type="submit" :disabled="loading">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true" />
              <span>{{ loading ? t('theme.foxengine.admin.slides.014') : t('theme.foxengine.admin.slides.057') }}</span>
            </button>
          </footer>
        </section>
      </div>

      <div v-else class="admin-slides__empty">
        <i class="fa-solid fa-images" aria-hidden="true" />
        <strong>{{ t('theme.foxengine.admin.slides.058') }}</strong>
        <p>{{ t('theme.foxengine.admin.slides.059') }}</p>
        <button class="button button--primary" type="button" @click="addSlide"><i class="fa-solid fa-plus" aria-hidden="true" /><span>{{ t('theme.foxengine.admin.slides.013') }}</span></button>
      </div>
    </form>
  </section>
</template>
