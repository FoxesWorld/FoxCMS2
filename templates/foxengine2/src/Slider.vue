<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'

interface Slide {
  title: string
  description: string
  image: string
  route: string
  action: string
}

const router = useRouter()
const activeIndex = ref(0)
const paused = ref(false)
let timer: number | undefined

const slides: Slide[] = [
  {
    title: 'Добро пожаловать в Лисий Мир',
    description: 'Знакомая атмосфера FoxesCraft продолжается в новой, лёгкой и адаптивной версии 3.0.',
    image: themeAsset(appBootstrap, 'img/slides/slide1.png'),
    route: 'start',
    action: 'Начать играть',
  },
  {
    title: 'Лисий Мир 3.0',
    description: 'Новая архитектура, новые технологии и тот же дух открытий.',
    image: themeAsset(appBootstrap, 'img/slides/slide3.png'),
    route: 'about',
    action: 'О проекте',
  },
  {
    title: 'Исследуй игровые миры',
    description: 'Следи за серверами, игроками и развитием проекта из единого интерфейса.',
    image: themeAsset(appBootstrap, 'img/slides/slide5.png'),
    route: 'players',
    action: 'Игроки',
  },
  {
    title: 'FoxesCraft продолжается',
    description: 'Мы не возвращаемся в прошлое — мы продолжаем начатую историю.',
    image: themeAsset(appBootstrap, 'img/slides/slide7.png'),
    route: 'about',
    action: 'Узнать больше',
  },
]

const activeSlide = computed(() => slides[activeIndex.value] ?? slides[0])
const slideNumber = computed(() => String(activeIndex.value + 1).padStart(2, '0'))
const slideTotal = String(slides.length).padStart(2, '0')

function stopTimer(): void {
  if (timer !== undefined) window.clearInterval(timer)
  timer = undefined
}

function startTimer(): void {
  stopTimer()
  if (!paused.value) timer = window.setInterval(advance, 7_000)
}

function selectSlide(index: number): void {
  activeIndex.value = index
  startTimer()
}

function advance(): void {
  activeIndex.value = (activeIndex.value + 1) % slides.length
}

function move(direction: number): void {
  activeIndex.value = (activeIndex.value + direction + slides.length) % slides.length
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
  void router.push({ name: route })
}

onMounted(() => {
  startTimer()
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  stopTimer()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})
</script>

<template>
  <section
    class="hero legacy-slider"
    aria-labelledby="hero-title"
    aria-roledescription="carousel"
    @mouseenter="pause"
    @mouseleave="resume"
    @focusin="pause"
    @focusout="resume"
  >
    <img
      :key="activeSlide.image"
      class="hero__backdrop-image legacy-slider__image"
      :src="activeSlide.image"
      alt=""
      fetchpriority="high"
      decoding="async"
    >
    <div class="hero__backdrop legacy-slider__overlay" />

    <div class="hero__sequence" aria-hidden="true">
      <strong>{{ slideNumber }}</strong>
      <span>/ {{ slideTotal }}</span>
    </div>

    <div class="hero__content legacy-slider__content" aria-live="polite" aria-atomic="true">
      <span class="eyebrow">FoxesCraft — новая глава</span>
      <h1 id="hero-title">{{ activeSlide.title }}</h1>
      <p>{{ activeSlide.description }}</p>
      <div class="hero__actions">
        <button class="button button--primary button--large" type="button" @click="navigate(activeSlide.route)">
          {{ activeSlide.action }}
        </button>
        <button class="button button--glass button--large" type="button" @click="navigate('about')">
          История проекта
        </button>
      </div>
    </div>

    <div class="legacy-slider__controls" aria-label="Управление слайдером">
      <button type="button" aria-label="Предыдущий слайд" @click="move(-1)">←</button>
      <button type="button" aria-label="Следующий слайд" @click="move(1)">→</button>
    </div>

    <div class="legacy-slider__rail" aria-label="Слайды">
      <button
        v-for="(slide, index) in slides"
        :key="slide.title"
        type="button"
        :class="{ 'is-active': index === activeIndex }"
        :aria-label="`Показать слайд ${index + 1}: ${slide.title}`"
        :aria-current="index === activeIndex ? 'true' : undefined"
        @click="selectSlide(index)"
      ><span /></button>
    </div>

    <div v-if="!paused" :key="activeIndex" class="legacy-slider__progress" aria-hidden="true" />
  </section>
</template>
