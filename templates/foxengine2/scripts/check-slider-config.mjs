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
for (const required of ['appBootstrap.theme.settings.slider', 'data/slides.json', "cache: 'no-store'", 'loadRuntimeSettings', 'resolveImage']) {
  if (!slider.includes(required)) failures.push(`Slider.vue missing ${required}`)
}
for (const forbidden of ['const slides: Slide[] = [', "title: 'Добро пожаловать в Лисий Мир'"]) {
  if (slider.includes(forbidden)) failures.push(`Slider.vue contains hardcoded slide data: ${forbidden}`)
}

const admin = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'), 'utf8')
for (const required of ["case 'slides'", "case 'saveSlides'", "case 'uploadSlideImage'", 'ThemeSlidesRepository', 'UploadPurpose::SLIDER_IMAGE']) {
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
for (const required of ["'slides'", "admPanel: 'slides'", "admPanel: 'saveSlides'", "body.set('admPanel', 'uploadSlideImage')"]) {
  if (!adminClient.includes(required)) failures.push(`Admin slider client missing ${required}`)
}

if (failures.length) {
  console.error('Slider configuration contract failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}
console.log(`Slider configuration passed: ${data.slides.length} JSON-backed slides and admin management contracts are present.`)
