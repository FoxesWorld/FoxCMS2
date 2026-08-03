<script setup lang="ts">
import { t } from '@/i18n'

import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import type { BootstrapValue } from '@engine/domain/bootstrap'

interface Slide {
  id: string
  enabled: boolean
  title: string
  description: string
  image: string
  route: string
  action: string
  secondaryRoute: string
  secondaryAction: string
}

interface SliderSettings {
  schema: number
  eyebrow: string
  autoplayMs: number
  slides: Slide[]
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : null
}

function asString(value: unknown, fallback = ''): string {
  return typeof value === 'string' || typeof value === 'number' ? String(value) : fallback
}

function asBoolean(value: unknown, fallback = true): boolean {
  return typeof value === 'boolean' ? value : fallback
}

function resolveImage(path: string): string {
  if (path.startsWith('/')) return path
  return themeAsset(appBootstrap, path.replace(/^assets\//, ''))
}

function normalizeSettings(value: unknown): SliderSettings {
  const configured = asRecord(value)
  const source = Array.isArray(configured?.slides) ? configured.slides : []
  const slides = source.flatMap((raw): Slide[] => {
    const entry = asRecord(raw)
    if (!entry) return []

    const id = asString(entry.id).trim()
    const title = asString(entry.title).trim()
    const image = asString(entry.image).trim()
    const route = asString(entry.route).trim()
    const enabled = asBoolean(entry.enabled)
    if (!enabled || !id || !title || !image || !route) return []

    return [{
      id,
      enabled,
      title,
      description: asString(entry.description).trim(),
      image: resolveImage(image),
      route,
      action: asString(entry.action, t('theme.slider.007')).trim() || t('theme.slider.007'),
      secondaryRoute: asString(entry.secondaryRoute).trim(),
      secondaryAction: asString(entry.secondaryAction).trim(),
    }]
  })

  const requestedInterval = Number(configured?.autoplayMs ?? 7000)
  return {
    schema: Number(configured?.schema ?? 1),
    eyebrow: asString(configured?.eyebrow, 'FoxesCraft').trim(),
    autoplayMs: Number.isFinite(requestedInterval) ? Math.max(0, requestedInterval) : 7000,
    slides,
  }
}

function runtimeDataUrl(): string {
  const themeName = appBootstrap.theme.name || document.documentElement.dataset.theme || 'foxengine2'
  return `/templates/${encodeURIComponent(themeName)}/data/slides.json`
}

const router = useRouter()
const settings = ref<SliderSettings>(normalizeSettings(appBootstrap.theme.settings.slider as BootstrapValue | undefined))
const activeIndex = ref(0)
const paused = ref(false)
const loading = ref(true)
const sliderRoot = ref<HTMLElement | null>(null)
const dragOffset = ref(0)
const dragging = ref(false)
const transitionDirection = ref<1 | -1>(1)
const suppressClick = ref(false)
let timer: number | undefined
let pointerId: number | null = null
let pointerStartX = 0
let pointerStartY = 0
let pointerStartedAt = 0
let horizontalGesture = false

const slides = computed(() => settings.value.slides)
const activeSlide = computed(() => slides.value[activeIndex.value])
const slideNumber = computed(() => String(activeIndex.value + 1).padStart(2, '0'))
const slideTotal = computed(() => String(slides.value.length).padStart(2, '0'))
const activeTitleId = computed(() => `hero-title-${activeSlide.value?.id ?? 'slide'}`)
const slideTransitionName = computed(() => transitionDirection.value > 0
  ? 'legacy-slide-next'
  : 'legacy-slide-previous')
const sliderStyle = computed(() => ({
  '--slider-drag-x': `${dragOffset.value}px`,
}))

function stopTimer(): void {
  if (timer !== undefined) window.clearInterval(timer)
  timer = undefined
}

function startTimer(): void {
  stopTimer()
  if (!paused.value && slides.value.length > 1 && settings.value.autoplayMs >= 3000) {
    timer = window.setInterval(advance, settings.value.autoplayMs)
  }
}

function selectSlide(index: number): void {
  if (index < 0 || index >= slides.value.length || index === activeIndex.value) return
  transitionDirection.value = index > activeIndex.value ? 1 : -1
  activeIndex.value = index
  startTimer()
}

function advance(): void {
  if (slides.value.length < 2) return
  transitionDirection.value = 1
  activeIndex.value = (activeIndex.value + 1) % slides.value.length
}

function move(direction: number): void {
  if (slides.value.length < 2) return
  transitionDirection.value = direction >= 0 ? 1 : -1
  activeIndex.value = (activeIndex.value + transitionDirection.value + slides.value.length) % slides.value.length
  startTimer()
}

function pause(): void {
  paused.value = true
  stopTimer()
}

function resume(): void {
  paused.value = false
  startTimer()
}

function handleVisibilityChange(): void {
  document.hidden ? pause() : resume()
}

function navigate(route: string): void {
  if (route) void router.push({ name: route })
}


function resetPointerState(): void {
  pointerId = null
  horizontalGesture = false
  dragging.value = false
  dragOffset.value = 0
}

function finishPointer(event: PointerEvent, cancelled = false): void {
  if (event.pointerId !== pointerId) return

  const elapsed = Math.max(1, performance.now() - pointerStartedAt)
  const distance = dragOffset.value
  const width = sliderRoot.value?.clientWidth ?? window.innerWidth
  const threshold = Math.min(112, Math.max(46, width * 0.16))
  const velocity = Math.abs(distance) / elapsed
  const shouldMove = !cancelled
    && horizontalGesture
    && (Math.abs(distance) >= threshold || (Math.abs(distance) >= 24 && velocity >= 0.45))

  const root = sliderRoot.value
  const hasCapture = root?.hasPointerCapture(event.pointerId) === true
  if (horizontalGesture && Math.abs(distance) >= 8) {
    suppressClick.value = true
    window.setTimeout(() => { suppressClick.value = false }, 250)
  }
  resetPointerState()
  if (hasCapture) root?.releasePointerCapture(event.pointerId)

  if (shouldMove) move(distance < 0 ? 1 : -1)
  else startTimer()
}

function onPointerDown(event: PointerEvent): void {
  if (slides.value.length < 2 || pointerId !== null || !event.isPrimary) return
  if (event.pointerType === 'mouse' && event.button !== 0) return

  pointerId = event.pointerId
  pointerStartX = event.clientX
  pointerStartY = event.clientY
  pointerStartedAt = performance.now()
  horizontalGesture = false
  dragOffset.value = 0
  sliderRoot.value?.setPointerCapture(event.pointerId)
  stopTimer()
}

function onPointerMove(event: PointerEvent): void {
  if (event.pointerId !== pointerId) return

  const deltaX = event.clientX - pointerStartX
  const deltaY = event.clientY - pointerStartY
  const absoluteX = Math.abs(deltaX)
  const absoluteY = Math.abs(deltaY)

  if (!horizontalGesture) {
    if (absoluteX < 7 && absoluteY < 7) return
    if (absoluteY > absoluteX) {
      finishPointer(event, true)
      return
    }
    horizontalGesture = true
    dragging.value = true
  }

  event.preventDefault()
  const width = sliderRoot.value?.clientWidth ?? window.innerWidth
  const resistance = Math.max(0.34, 1 - absoluteX / Math.max(width, 1) * 0.42)
  dragOffset.value = deltaX * resistance
}

function onPointerUp(event: PointerEvent): void {
  finishPointer(event)
}

function onPointerCancel(event: PointerEvent): void {
  finishPointer(event, true)
}

function onClickCapture(event: MouseEvent): void {
  if (!suppressClick.value) return
  event.preventDefault()
  event.stopPropagation()
  suppressClick.value = false
}

function onLostPointerCapture(event: PointerEvent): void {
  if (event.pointerId === pointerId) finishPointer(event, true)
}

async function loadRuntimeSettings(): Promise<void> {
  try {
    const response = await fetch(runtimeDataUrl(), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) throw new Error(t('theme.slider.008', [response.status]))
    const runtimeSettings = normalizeSettings(await response.json())
    if (runtimeSettings.slides.length === 0) throw new Error(t('theme.slider.009'))
    settings.value = runtimeSettings
    activeIndex.value = 0
  } catch (error) {
    if (settings.value.slides.length === 0) {
      console.error('[FoxesCraft] Slider runtime data is unavailable', error)
    } else {
      console.warn('[FoxesCraft] Slider uses bootstrap fallback', error)
    }
  } finally {
    loading.value = false
    startTimer()
  }
}

onMounted(() => {
  void loadRuntimeSettings()
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  stopTimer()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})
</script>

<template>
  <section
    v-if="activeSlide"
    ref="sliderRoot"
    class="hero legacy-slider"
    :class="{ 'is-dragging': dragging }"
    :style="sliderStyle"
    :aria-labelledby="activeTitleId"
    aria-roledescription="carousel"
    :aria-busy="loading"
    @mouseenter="pause"
    @mouseleave="resume"
    @focusin="pause"
    @focusout="resume"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointercancel="onPointerCancel"
    @lostpointercapture="onLostPointerCapture"
    @click.capture="onClickCapture"
    @dragstart.prevent
    @selectstart.prevent
  >
    <Transition :name="slideTransitionName">
      <div :key="activeSlide.id" class="legacy-slider__track">
        <img
          class="hero__backdrop-image legacy-slider__image"
          :src="activeSlide.image"
          alt=""
          draggable="false"
          fetchpriority="high"
          decoding="async"
        >
        <div class="hero__backdrop legacy-slider__overlay" />

        <div class="hero__sequence" aria-hidden="true">
          <strong>{{ slideNumber }}</strong>
          <span>/ {{ slideTotal }}</span>
        </div>

        <div class="hero__content legacy-slider__content" aria-live="polite" aria-atomic="true">
          <span v-if="settings.eyebrow" class="eyebrow">{{ settings.eyebrow }}</span>
          <h1 :id="activeTitleId">{{ activeSlide.title }}</h1>
          <p v-if="activeSlide.description">{{ activeSlide.description }}</p>
          <div class="hero__actions">
            <button class="button button--primary button--large" type="button" @click="navigate(activeSlide.route)">
              {{ activeSlide.action }}
            </button>
            <button
              v-if="activeSlide.secondaryRoute && activeSlide.secondaryAction"
              class="button button--glass button--large"
              type="button"
              @click="navigate(activeSlide.secondaryRoute)"
            >
              {{ activeSlide.secondaryAction }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <div v-if="slides.length > 1" class="legacy-slider__controls" :aria-label="t('theme.slider.001')">
      <button type="button" :aria-label="t('theme.slider.002')" @click="move(-1)"><i class="fa-solid fa-chevron-left" aria-hidden="true" /></button>
      <button type="button" :aria-label="t('theme.slider.003')" @click="move(1)"><i class="fa-solid fa-chevron-right" aria-hidden="true" /></button>
    </div>

    <div v-if="slides.length > 1" class="legacy-slider__rail" :aria-label="t('theme.slider.004')">
      <button
        v-for="(slide, index) in slides"
        :key="slide.id"
        type="button"
        :class="{ 'is-active': index === activeIndex }"
        :aria-label="t('theme.slider.005', [index + 1, slide.title])"
        :aria-current="index === activeIndex ? 'true' : undefined"
        @click="selectSlide(index)"
      ><span /></button>
    </div>

    <div
      v-if="!paused && slides.length > 1 && settings.autoplayMs >= 3000"
      :key="activeIndex"
      class="legacy-slider__progress"
      aria-hidden="true"
      :style="{ animationDuration: `${settings.autoplayMs}ms` }"
    />
  </section>
</template>
