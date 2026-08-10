import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { repositoryRoot, themeRoot } from './theme-paths.mjs'

const failures = []
const manifest = JSON.parse(await readFile(join(themeRoot, 'theme.json'), 'utf8'))
if (manifest?.data?.slider !== undefined) failures.push('slides.json must be loaded at runtime, not embedded through theme.json data.slider')

const data = JSON.parse(await readFile(join(themeRoot, 'data', 'slides.json'), 'utf8'))
if (data?.schema !== 1) failures.push('slides.json schema must equal 1')
if (!Array.isArray(data?.slides)) failures.push('slides.json must contain a slides array')
else {
  const ids = new Set()
  for (const [index, slide] of data.slides.entries()) {
    if (!slide || typeof slide !== 'object' || Array.isArray(slide)) failures.push(`slide ${index + 1} must be an object`)
    else {
      if (!/^[a-z][a-z0-9-]{1,63}$/.test(String(slide.id ?? ''))) failures.push(`slide ${index + 1} has invalid id`)
      if (ids.has(slide.id)) failures.push(`duplicate slide id ${slide.id}`)
      ids.add(slide.id)
      for (const field of ['title', 'image', 'route', 'action']) {
        if (typeof slide[field] !== 'string' || !slide[field].trim()) failures.push(`slide ${index + 1} is missing ${field}`)
      }
    }
  }
}

const slider = await readFile(join(themeRoot, 'src', 'Slider.vue'), 'utf8')
const sliderSettings = await readFile(join(themeRoot, 'src', 'slider', 'sliderSettings.ts'), 'utf8')
const sliderRepository = await readFile(join(themeRoot, 'src', 'slider', 'sliderRuntimeRepository.ts'), 'utf8')
const sliderController = await readFile(join(themeRoot, 'src', 'slider', 'useHeroCarousel.ts'), 'utf8')
const runtimeJson = await readFile(join(repositoryRoot, 'engine', 'client', 'runtime', 'runtimeJson.ts'), 'utf8')
for (const required of ['appBootstrap.theme.settings.slider', 'loadRuntimeSettings', 'resolveImage', 'loadSliderRuntimeSettings', 'draggable="false"', '@dragstart.prevent', '@selectstart.prevent']) {
  if (!slider.includes(required)) failures.push(`Slider.vue missing ${required}`)
}
for (const required of ['sliderRuntimeDataUrl', 'data/slides.json']) {
  if (!sliderSettings.includes(required)) failures.push(`Slider settings domain missing ${required}`)
}
for (const required of ['loadRuntimeJson', 'normalizeSliderSettings']) {
  if (!sliderRepository.includes(required)) failures.push(`Slider runtime repository missing ${required}`)
}
for (const required of ['onPointerDown', 'onPointerMove', 'finishPointer', 'legacy-slide-next']) {
  if (!sliderController.includes(required)) failures.push(`Hero carousel controller missing ${required}`)
}
if (!runtimeJson.includes("cache: 'no-store'")) failures.push("Runtime JSON transport missing cache: 'no-store'")
for (const forbidden of ['const slides: Slide[] = [', "title: 'Добро пожаловать в Лисий Мир'"]) {
  if (slider.includes(forbidden)) failures.push(`Slider.vue contains hardcoded slide data: ${forbidden}`)
}

const sliderStyles = await readFile(join(themeRoot, 'assets', 'css', 'legacy-continuation.css'), 'utf8')
for (const required of ['touch-action: pan-y', 'user-select: none', '-webkit-user-drag: none', '.legacy-slide-next-enter-from', '.legacy-slide-previous-enter-from']) {
  if (!sliderStyles.includes(required)) failures.push(`Slider styles are missing ${required}`)
}

const admin = (await Promise.all([
  join(repositoryRoot, 'engine', 'src', 'FoxCMS', 'Engine', 'Admin', 'AdminActionRouterFactory.php'),
  join(repositoryRoot, 'engine', 'src', 'FoxCMS', 'Engine', 'Admin', 'AdminThemeController.php'),
].map((path) => readFile(path, 'utf8')))).join('\n')
for (const required of ["->register('slides'", "->register('saveSlides'", "->register('uploadSlideImage'", 'ThemeSlidesRepository', 'UploadPurpose::SLIDER_IMAGE']) {
  if (!admin.includes(required)) failures.push(`AdminOptions missing ${required}`)
}
const repository = await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', 'ThemeSlidesRepository.class.php'), 'utf8')
for (const required of [
  'function read()',
  'function save(',
  'function routes()',
  'JSON_PRETTY_PRINT',
  '@file_put_contents($temporary, $encoded, LOCK_EX)',
  '@rename($temporary, $this->dataPath)',
  'Временный файл: ',
  'Целевой файл: ',
  'Каталог: ',
  'Права каталога: ',
  'Системная ошибка: ',
  'private function lastFilesystemError()',
  'private function permissions(',
]) {
  if (!repository.includes(required)) failures.push(`ThemeSlidesRepository missing ${required}`)
}
const adminClient = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'), 'utf8')
for (const required of ["'slides'", "admPanel: 'slides'", "admPanel: 'saveSlides'", "body.set('admPanel', 'uploadSlideImage')", 'function reorderSlide(fromIndex: number, toIndex: number)']) {
  if (!adminClient.includes(required)) failures.push(`Admin slider client missing ${required}`)
}

const adminSlides = await readFile(join(themeRoot, 'src', 'foxEngine', 'admin', 'Slides.vue'), 'utf8')
for (const required of [
  'admin-slide-item__drag-handle',
  '@pointerdown="startSlideDrag($event, slide)"',
  '@keydown="reorderSlideByKeyboard($event, index)"',
  "window.addEventListener('pointermove', dragSlide",
  "window.addEventListener('pointerup', finishSlideDrag",
  "window.addEventListener('pointercancel', cancelSlideDrag",
  'transform: `translate3d(0, ${dragTranslateY()}px, 0)`',
  "'is-drop-target':",
  'reorderSlide(fromIndex, toIndex)',
  "emit('reorder', fromIndex, toIndex)",
]) {
  if (!adminSlides.includes(required)) failures.push(`Admin Slides.vue missing drag-reorder contract ${required}`)
}
for (const forbidden of ['admin-slide-item__order', 'fa-arrow-up', 'fa-arrow-down', "emit('move'"]) {
  if (adminSlides.includes(forbidden)) failures.push(`Admin Slides.vue still contains legacy order control ${forbidden}`)
}

const adminSlidesStyles = await readFile(join(themeRoot, 'src', 'styles', 'admin-slides.css'), 'utf8')
for (const required of ['.admin-slides__list.is-reordering', '.admin-slide-item.is-dragging', '.admin-slide-item.is-drop-target::before', '.admin-slide-item__drag-handle', 'touch-action: none']) {
  if (!adminSlidesStyles.includes(required)) failures.push(`Admin slide drag styles are missing ${required}`)
}

const adminPanelTpl = await readFile(join(themeRoot, 'userOptions', 'AdminPanel.tpl'), 'utf8')
if (!adminPanelTpl.includes('@reorder="reorderSlide"')) failures.push('AdminPanel.tpl does not wire slide reorder events')
if (adminPanelTpl.includes('@move="moveSlide"')) failures.push('AdminPanel.tpl still wires legacy moveSlide events')

if (failures.length) {
  console.error('Slider configuration contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`Slider configuration passed: ${data.slides.length} JSON-backed slides and admin management contracts are present.`)
