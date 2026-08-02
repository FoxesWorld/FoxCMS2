import { readdir, readFile } from 'node:fs/promises'
import { join, relative } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'

const engineRoot = join(repositoryRoot, 'engine')
const uploadService = join(engineRoot, 'classes', 'uploads', 'UploadService.class.php')
const httpRequest = join(engineRoot, 'classes', 'http', 'HttpRequest.class.php')

async function phpFiles(directory) {
  const result = []
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.name === 'cache') continue
    const path = join(directory, entry.name)
    if (entry.isDirectory()) result.push(...await phpFiles(path))
    else if (entry.isFile() && (entry.name.endsWith('.php') || !entry.name.includes('.'))) result.push(path)
  }
  return result
}

const violations = []
for (const file of await phpFiles(engineRoot)) {
  const source = await readFile(file, 'utf8')
  const name = relative(repositoryRoot, file).replaceAll('\\', '/')
  const directUploadPatterns = [
    ['move_uploaded_file(', 'direct move_uploaded_file usage'],
    ['is_uploaded_file(', 'direct is_uploaded_file usage'],
    ['UPLOAD_ERR_', 'local upload error handling'],
  ]
  if (file !== uploadService) {
    for (const [pattern, description] of directUploadPatterns) {
      if (source.includes(pattern)) violations.push(`${name}: ${description}`)
    }
  }
  if (file !== httpRequest && source.includes('$_FILES')) {
    violations.push(`${name}: direct $_FILES access`)
  }
}

const consumers = new Map([
  ['engine/SystemRequests.class.php', ['new UploadService($db, $userSession, $logger, $request)', 'UploadPurpose::MINECRAFT_SKIN', 'UploadPurpose::MINECRAFT_CAPE']],
  ['engine/classes/modules/UserSettings/actions/updateProfilePhoto.class.php', ['UploadService', 'UploadPurpose::PROFILE_PHOTO']],
  ['engine/classes/modules/News/News.class.php', ['UploadService', 'UploadPurpose::NEWS_COVER']],
  ['engine/classes/modules/AdminPanel/AdminOptions.class.php', ['UploadService', 'UploadPurpose::ADMIN_FILE', 'UploadPurpose::SLIDER_IMAGE', 'UploadPurpose::SERVER_IMAGE']],
])
for (const [file, required] of consumers) {
  const source = await readFile(join(repositoryRoot, file), 'utf8')
  for (const token of required) {
    if (!source.includes(token)) violations.push(`${file}: missing ${token}`)
  }
}

const application = await readFile(join(engineRoot, 'Application.class.php'), 'utf8')
for (const file of ['UploadException.class.php', 'UploadPermission.class.php', 'UploadPurpose.class.php', 'UploadResult.class.php', 'UploadService.class.php']) {
  if (!application.includes(file)) violations.push(`engine/Application.class.php: upload core does not load ${file}`)
}

const service = await readFile(uploadService, 'utf8')
const adminOptions = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminOptions.class.php'), 'utf8')
const adminClient = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'client', 'useAdminPanel.ts'), 'utf8')
const fileManager = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'foxEngine', 'admin', 'FileManager.vue'), 'utf8')
const adminPanel = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminPanel.class.php'), 'utf8')
const serverEditor = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'foxEngine', 'admin', 'servers', 'ServerEditor.vue'), 'utf8')
const serverPage = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'foxEngine', 'serverPage', 'ServerPage.vue'), 'utf8')
const serverImageDomain = await readFile(join(repositoryRoot, 'engine', 'client', 'domain', 'serverImage.ts'), 'utf8')
const systemRequests = await readFile(join(repositoryRoot, 'engine', 'SystemRequests.class.php'), 'utf8')
const imageUploadField = await readFile(join(repositoryRoot, 'engine', 'client', 'components', 'ImageUploadField.vue'), 'utf8')
const imageUploadCss = await readFile(join(repositoryRoot, 'engine', 'client', 'components', 'image-upload-field.css'), 'utf8')
const profilePhotoDialog = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'userOptions', 'userOptions', 'profile', 'ProfilePhotoDialog.vue'), 'utf8')
const appearanceOption = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'userOptions', 'userOptions', 'profile', 'options', 'AppearanceOption.vue'), 'utf8')
const newsEditor = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'news', 'NewsEditor.vue'), 'utf8')
const slidesEditor = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'foxEngine', 'admin', 'Slides.vue'), 'utf8')

for (const token of [
  "logInfo('Upload accepted.'",
  "logWarn('Upload rejected.'",
  "logError('Upload failed unexpectedly.'",
  'CsrfToken::validate',
  'rejectSymlinkPath',
  'verifyPublishedFile',
  'UploadPermission::ADMIN_FILES',
  'UploadPermission::NEWS_COVER',
  'UploadPermission::SLIDER_IMAGE',
  'UploadPermission::SERVER_IMAGE',
  'UploadPermission::MINECRAFT_ANY',
  'UploadPermission::PROFILE_ANY',
  "'allowAnyType' => true",
]) {
  if (!service.includes(token)) violations.push(`UploadService: missing contract ${token}`)
}

for (const forbidden of ['ADMIN_BLOCKED_EXTENSIONS', 'ADMIN_BLOCKED_MIME', "'blockedExtensions' =>", "'blockedMime' =>"]) {
  if (service.includes(forbidden)) violations.push(`UploadService: admin arbitrary-file upload is still restricted by ${forbidden}`)
}
if (adminOptions.includes('BLOCKED_UPLOAD_EXTENSIONS')) {
  violations.push('AdminOptions: File Manager rename still restricts file extensions')
}
for (const token of ['function selectUpload(file: File | null)', "body.set('file', file, file.name)"]) {
  if (!adminClient.includes(token)) violations.push(`Admin File Manager client is missing ${token}`)
}
for (const token of [
  'selectUpload: [file: File | null]',
  '@drop.prevent="onDrop"',
  'admin-upload-dropzone',
  'admin-upload-selection',
  'Любой тип и расширение',
  'type="file"',
  'Найти файл или каталог',
  'uploadBlockedReason',
  'admin-upload-disabled-reason',
  'Текущий каталог недоступен для записи процессу PHP',
]) {
  if (!fileManager.includes(token)) violations.push(`Admin File Manager UI is missing ${token}`)
}
if (/\baccept\s*=/.test(fileManager)) {
  violations.push('Admin File Manager file input must not restrict selectable file types through accept')
}

for (const token of [
  "case 'uploadServerImage'",
  "UploadPurpose::SERVER_IMAGE",
  "'_serverImageUpload'",
  "private function uploadServerImage()",
  "normalizeServerImageReference",
  "validateReference(UploadPurpose::SERVER_IMAGE",
]) {
  if (!adminOptions.includes(token)) violations.push(`Admin server image backend is missing ${token}`)
}
for (const token of [
  "'uploadServerImage'",
  "$payload['_serverImageUpload'] = $request->file('image')",
]) {
  if (!adminPanel.includes(token)) violations.push(`AdminPanel multipart bridge is missing ${token}`)
}
for (const token of [
  'async function uploadServerImage(file: File)',
  "body.set('admPanel', 'uploadServerImage')",
  "body.set('image', file, file.name)",
  'serverDraft.serverImage = response.image',
  'serverImageUploading',
]) {
  if (!adminClient.includes(token)) violations.push(`Admin server image client is missing ${token}`)
}
for (const token of [
  'ImageUploadField',
  ':preview="serverImageUrl(draft.serverImage)"',
  'preview-mode="wide"',
  ':minimum-width="320"',
  ':minimum-height="180"',
  "@select=\"emit('uploadImage', $event)\"",
  "@clear=\"emit('clearImage')\"",
  '/uploads/servers/',
]) {
  if (!serverEditor.includes(token)) violations.push(`Server image shared form is missing ${token}`)
}

for (const token of [
  '@dragenter.prevent="onDragEnter"',
  '@dragover.prevent',
  '@dragleave.prevent="onDragLeave"',
  '@drop.prevent="onDrop"',
  'URL.createObjectURL(file)',
  'URL.revokeObjectURL',
  'async function validateFile(file: File)',
  'function imageDimensions(file: File)',
  "emit('select', file)",
  "emit('invalid', message)",
  'image-upload-field__preview',
  'image-upload-field__dropzone',
  'image-upload-field__selection',
]) {
  if (!imageUploadField.includes(token)) violations.push(`Shared ImageUploadField is missing ${token}`)
}
for (const token of [
  '.image-upload-field__dropzone.is-dragging',
  '.image-upload-field--wide .image-upload-field__preview',
  '.image-upload-field--circle .image-upload-field__preview',
  '.image-upload-field.has-error',
]) {
  if (!imageUploadCss.includes(token)) violations.push(`Shared image upload styles are missing ${token}`)
}

const sharedImageConsumers = new Map([
  ['ServerEditor.vue', serverEditor],
  ['ProfilePhotoDialog.vue', profilePhotoDialog],
  ['AppearanceOption.vue', appearanceOption],
  ['NewsEditor.vue', newsEditor],
  ['Slides.vue', slidesEditor],
])
for (const [name, source] of sharedImageConsumers) {
  if (!source.includes('ImageUploadField')) violations.push(`${name}: shared ImageUploadField is not used`)
  if (/<input\b[^>]*type="file"/i.test(source)) violations.push(`${name}: direct image file input remains outside ImageUploadField`)
}
for (const token of ['@select="selectFile"', '@clear="clearSelectedFile"', 'preview-mode="none"']) {
  if (!profilePhotoDialog.includes(token)) violations.push(`Profile photo dialog shared upload is missing ${token}`)
}
for (const token of ['preview-mode="circle"', "@select=\"emit('selectAvatar', $event)\"", '#actions']) {
  if (!appearanceOption.includes(token)) violations.push(`Profile appearance shared upload is missing ${token}`)
}
for (const token of ['preview-mode="circle"', '@select="selectCover"', "@clear=\"draft.coverImage = ''\""]) {
  if (!newsEditor.includes(token)) violations.push(`News cover shared upload is missing ${token}`)
}
for (const token of ['preview-mode="none"', '@select="selectImage(selectedIndex, $event)"', "@clear=\"selectedSlide.image = ''\""]) {
  if (!slidesEditor.includes(token)) violations.push(`Slide shared upload is missing ${token}`)
}

for (const token of ['export function serverImageUrl', "themeAsset(", "img/servers/${themeRelative}"]) {
  if (!serverImageDomain.includes(token)) violations.push(`Server image URL resolver is missing ${token}`)
}
for (const token of ['serverImageUrl', 'coverUrl', '@error="coverFailed = true"']) {
  if (!serverPage.includes(token)) violations.push(`Public server image preview is missing ${token}`)
}
for (const token of [
  "str_starts_with($reference, '/uploads/servers/')",
  "ROOT_DIR . UPLOADS_DIR . 'servers'",
  "['image/jpeg', 'image/png', 'image/webp']",
]) {
  if (!systemRequests.includes(token)) violations.push(`Launcher server image endpoint is missing ${token}`)
}

if (violations.length > 0) {
  console.error('Upload contract violations:')
  for (const violation of violations) console.error(`- ${violation}`)
  process.exit(1)
}

console.log('Upload contract passed: all server uploads use the centralized policy service with audit logging.')
