import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  type ComputedRef,
  type Ref,
} from 'vue'
import type { Slide } from './sliderSettings'

export function useHeroCarousel(
  slides: ComputedRef<Slide[]>,
  autoplayMs: ComputedRef<number>,
  ready: Ref<boolean>,
) {
  const activeIndex = ref(0)
  const paused = ref(false)
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

  const activeSlide = computed(() => slides.value[activeIndex.value])
  const slideNumber = computed(() => String(activeIndex.value + 1).padStart(2, '0'))
  const slideTotal = computed(() => String(slides.value.length).padStart(2, '0'))
  const activeTitleId = computed(() => `hero-title-${activeSlide.value?.id ?? 'slide'}`)
  const slideTransitionName = computed(() => transitionDirection.value > 0
    ? 'legacy-slide-next'
    : 'legacy-slide-previous')
  const sliderStyle = computed(() => ({ '--slider-drag-x': `${dragOffset.value}px` }))

  function stopTimer(): void {
    if (timer !== undefined) window.clearInterval(timer)
    timer = undefined
  }

  function startTimer(): void {
    stopTimer()
    if (
      ready.value
      && !paused.value
      && slides.value.length > 1
      && autoplayMs.value >= 3000
    ) {
      timer = window.setInterval(advance, autoplayMs.value)
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

  function reset(): void {
    activeIndex.value = 0
    transitionDirection.value = 1
    startTimer()
  }

  function handleVisibilityChange(): void {
    document.hidden ? pause() : resume()
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

  onMounted(() => document.addEventListener('visibilitychange', handleVisibilityChange))
  onBeforeUnmount(() => {
    stopTimer()
    document.removeEventListener('visibilitychange', handleVisibilityChange)
  })

  return {
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
  }
}
