<script setup lang="ts">
import { t } from '@/i18n'

import { computed, onMounted, onUnmounted, ref } from 'vue'
import { appBootstrap } from '@/app/context'
import { themeAsset, type BootstrapValue } from '@/domain/bootstrap'

interface HeroSlide {
  title: string
  description: string
  image: string
  route: string
  action: string
}

const emit = defineEmits<{ navigate: [routeName: string] }>()
const activeIndex = ref(0)
let timer: number | undefined

function asRecord(value: BootstrapValue): Record<string, BootstrapValue> | null {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, BootstrapValue>
    : null
}

function asString(value: BootstrapValue | undefined, fallback = ''): string {
  return typeof value === 'string' || typeof value === 'number' ? String(value) : fallback
}

const fallbackSlides: HeroSlide[] = [{
  title: t('engine.herosection.005'),
  description: t('engine.herosection.006'),
  image: themeAsset(appBootstrap, 'img/slides/slide7.png'),
  route: 'start',
  action: t('engine.herosection.007'),
}]

const slides = computed<HeroSlide[]>(() => {
  const configured = appBootstrap.theme.settings.heroSlides
  if (!Array.isArray(configured)) return fallbackSlides

  const normalized = configured.flatMap((entry): HeroSlide[] => {
    const item = asRecord(entry)
    if (!item) return []
    const image = asString(item.image)
    const title = asString(item.title)
    if (!image || !title) return []
    return [{
      title,
      description: asString(item.description),
      image: themeAsset(appBootstrap, image.replace(/^assets\//, '')),
      route: asString(item.route, 'about'),
      action: asString(item.action, t('engine.herosection.008')),
    }]
  })
  return normalized.length ? normalized : fallbackSlides
})

const activeSlide = computed(() => slides.value[activeIndex.value] ?? fallbackSlides[0])

function selectSlide(index: number): void {
  activeIndex.value = index
  restartTimer()
}

function advance(): void {
  activeIndex.value = (activeIndex.value + 1) % slides.value.length
}

function restartTimer(): void {
  if (timer) window.clearInterval(timer)
  if (slides.value.length > 1) timer = window.setInterval(advance, 7_000)
}

onMounted(restartTimer)
onUnmounted(() => { if (timer) window.clearInterval(timer) })
</script>

<template>
  <section class="hero legacy-slider page-width" aria-labelledby="hero-title" aria-roledescription="carousel">
    <img :key="activeSlide.image" class="hero__backdrop-image legacy-slider__image" :src="activeSlide.image" alt="">
    <div class="hero__backdrop legacy-slider__overlay" />
    <div class="hero__content legacy-slider__content" aria-live="polite">
      <span class="eyebrow">{{ t('engine.herosection.001') }}</span>
      <h1 id="hero-title">{{ activeSlide.title }}</h1>
      <p>{{ activeSlide.description }}</p>
      <div class="hero__actions">
        <button class="button button--primary button--large" type="button" @click="emit('navigate', activeSlide.route)">{{ activeSlide.action }}</button>
        <button class="button button--glass button--large" type="button" @click="emit('navigate', 'about')">{{ t('engine.herosection.002') }}</button>
      </div>
    </div>
    <div class="legacy-slider__rail" :aria-label="t('engine.herosection.003')">
      <button
        v-for="(slide, index) in slides"
        :key="slide.title"
        type="button"
        :class="{ 'is-active': index === activeIndex }"
        :aria-label="t('engine.herosection.004', [index + 1, slide.title])"
        :aria-current="index === activeIndex ? 'true' : undefined"
        @click="selectSlide(index)"
      ><span /></button>
    </div>
    <div :key="activeIndex" class="legacy-slider__progress" aria-hidden="true" />
  </section>
</template>
