<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@/i18n'
import { appBootstrap } from '@engine/app/context'
import { themeAsset, type BootstrapValue } from '@engine/domain/bootstrap'
import { normalizeSliderSettings, type SliderSettings } from './slider/sliderSettings'
import { loadSliderRuntimeSettings } from './slider/sliderRuntimeRepository'
import { useHeroCarousel } from './slider/useHeroCarousel'

function resolveImage(path: string): string {
  if (path.startsWith('/')) return path
  return themeAsset(appBootstrap, path.replace(/^assets\//, ''))
}

function normalizeSettings(value: unknown): SliderSettings {
  return normalizeSliderSettings(value, {
    defaultAction: t('theme.slider.007'),
    resolveImage,
  })
}

const router = useRouter()
const settings = ref<SliderSettings>(
  normalizeSettings(appBootstrap.theme.settings.slider as BootstrapValue | undefined),
)
const loading = ref(true)
const slides = computed(() => settings.value.slides)
const autoplayMs = computed(() => settings.value.autoplayMs)
const ready = computed(() => !loading.value)

const {
  activeIndex,
  activeSlide,
  activeTitleId,
  dragging,
  move,
  onClickCapture,
  onLostPointerCapture,
  onPointerCancel,
  onPointerDown,
  onPointerMove,
  onPointerUp,
  pause,
  paused,
  reset,
  resume,
  selectSlide,
  slideNumber,
  slideTotal,
  slideTransitionName,
  sliderRoot,
  sliderStyle,
} = useHeroCarousel(slides, autoplayMs, ready)

function navigate(route: string): void {
  if (route) void router.push({ name: route })
}

async function loadRuntimeSettings(): Promise<void> {
  try {
    const themeName = appBootstrap.theme.name || document.documentElement.dataset.theme || 'foxengine2'
    settings.value = await loadSliderRuntimeSettings(themeName, resolveImage)
  } catch (error) {
    if (settings.value.slides.length === 0) {
      console.error('[FoxesCraft] Slider runtime data is unavailable', error)
    } else {
      console.warn('[FoxesCraft] Slider uses bootstrap fallback', error)
    }
  } finally {
    loading.value = false
    reset()
  }
}

onMounted(() => void loadRuntimeSettings())
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
