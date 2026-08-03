import { readdir, readFile } from 'node:fs/promises'
import { join, relative } from 'node:path'
import { repositoryRoot } from './theme-paths.mjs'
import { includesLocalized } from './i18n-test-utils.mjs'

const engineRoot = join(repositoryRoot, 'engine')
const uploadService = join(engineRoot, 'classes', 'uploads', 'UploadService.class.php')
const uploadInspectorPath = join(engineRoot, 'classes', 'uploads', 'UploadFileInspector.class.php')
const uploadFilesystemPath = join(engineRoot, 'classes', 'uploads', 'UploadFilesystem.class.php')
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
    ['move_uploaded_file(', 'direct move_uploaded_file usage', 'engine/classes/uploads/UploadFilesystem.class.php'],
    ['is_uploaded_file(', 'direct is_uploaded_file usage', 'engine/classes/uploads/UploadFileInspector.class.php'],
    ['UPLOAD_ERR_', 'local upload error handling', 'engine/classes/uploads/UploadFileInspector.class.php'],
  ]
  for (const [pattern, description, owner] of directUploadPatterns) {
    if (source.includes(pattern) && name !== owner) violations.push(`${name}: ${description}`)
  }
  if (file !== httpRequest && source.includes('$_FILES')) {
    violations.push(`${name}: direct $_FILES access`)
  }
}

const consumers = new Map([
  ['engine/SystemRequests.class.php', ['new UploadService($db, $userSession, $logger, $request)', 'UploadPurpose::MINECRAFT_SKIN', 'UploadPurpose::MINECRAFT_CAPE']],
  ['engine/classes/modules/UserSettings/actions/updateProfilePhoto.class.php', ['UploadService', 'UploadPurpose::PROFILE_PHOTO']],
  ['engine/classes/modules/News/News.class.php', ['UploadService', 'UploadPurpose::NEWS_COVER']],
  ['engine/classes/modules/AdminPanel/AdminOptions.class.php', ['UploadService', 'UploadPurpose::SLIDER_IMAGE', 'UploadPurpose::SERVER_IMAGE', 'UploadPurpose::SITE_SOCIAL_IMAGE']],
  ['engine/classes/modules/AdminPanel/AdminFileManager.class.php', ['UploadService', 'UploadPurpose::ADMIN_FILE']],
])
for (const [file, required] of consumers) {
  const source = await readFile(join(repositoryRoot, file), 'utf8')
  for (const token of required) {
    if (!includesLocalized(source, token)) violations.push(`${file}: missing ${token}`)
  }
}

const application = await readFile(join(engineRoot, 'Application.class.php'), 'utf8')
for (const file of ['UploadException.class.php', 'UploadPermission.class.php', 'UploadPurpose.class.php', 'UploadResult.class.php', 'UploadService.class.php']) {
  if (!application.includes(file)) violations.push(`engine/Application.class.php: upload core does not load ${file}`)
}

const service = await readFile(uploadService, 'utf8')
const uploadInspector = await readFile(uploadInspectorPath, 'utf8')
const uploadFilesystem = await readFile(uploadFilesystemPath, 'utf8')
const adminFileManager = await readFile(join(repositoryRoot, 'engine', 'classes', 'modules', 'AdminPanel', 'AdminFileManager.class.php'), 'utf8')
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
const pinturaImageEditor = await readFile(join(repositoryRoot, 'engine', 'client', 'media', 'pinturaImageEditor.ts'), 'utf8')
const profilePhotoDialog = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'userOptions', 'userOptions', 'profile', 'ProfilePhotoDialog.vue'), 'utf8')
const appearanceOption = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'userOptions', 'userOptions', 'profile', 'options', 'AppearanceOption.vue'), 'utf8')
const skinOption = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'userOptions', 'userOptions', 'profile', 'options', 'SkinOption.vue'), 'utf8')
const cloakOption = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'userOptions', 'userOptions', 'profile', 'options', 'CloakOption.vue'), 'utf8')
const newsEditor = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'news', 'NewsEditor.vue'), 'utf8')
const slidesEditor = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'foxEngine', 'admin', 'Slides.vue'), 'utf8')
const siteSettingsEditor = await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'src', 'foxEngine', 'admin', 'SiteSettings.vue'), 'utf8')
const uploadPurpose = await readFile(join(repositoryRoot, 'engine', 'classes', 'uploads', 'UploadPurpose.class.php'), 'utf8')
const uploadPolicyFactory = await readFile(join(repositoryRoot, 'engine', 'classes', 'uploads', 'UploadPolicyFactory.class.php'), 'utf8')
const themeRenderer = await readFile(join(repositoryRoot, 'engine', 'classes', 'themes', 'ThemeRenderer.class.php'), 'utf8')
const packageJson = JSON.parse(await readFile(join(repositoryRoot, 'templates', 'foxengine2', 'package.json'), 'utf8'))

for (const token of [
  '$this->policies = new UploadPolicyFactory',
  '$this->filesystem = new UploadFilesystem',
  '$this->inspector = new UploadFileInspector',
  'OperationTrace::begin',
  "$trace->success('Upload accepted.'",
  "'Upload rejected by policy or validation.'",
  "$trace->failed($error, 'Upload failed unexpectedly.')",
  'CsrfToken::validate',
]) {
  if (!includesLocalized(service, token)) violations.push(`UploadService orchestration is missing ${token}`)
}
for (const token of ['move_uploaded_file(', 'rejectSymlinkPath', 'verifyPublishedFile', "'.upload-'"]) {
  if (!includesLocalized(uploadFilesystem, token)) violations.push(`UploadFilesystem is missing ${token}`)
}
for (const token of ['is_uploaded_file(', 'UPLOAD_ERR_', 'getimagesize(', 'new finfo(', 'hash_file(']) {
  if (!includesLocalized(uploadInspector, token)) violations.push(`UploadFileInspector is missing ${token}`)
}
for (const token of [
  'UploadPermission::ADMIN_FILES',
  'UploadPermission::NEWS_COVER',
  'UploadPermission::SLIDER_IMAGE',
  'UploadPermission::SERVER_IMAGE',
  'UploadPermission::SITE_SOCIAL_IMAGE',
  'UploadPermission::MINECRAFT_ANY',
  'UploadPermission::PROFILE_ANY',
  'allowAnyType: true',
]) {
  if (!includesLocalized(uploadPolicyFactory, token)) violations.push(`UploadPolicyFactory is missing ${token}`)
}
for (const forbidden of ['ADMIN_BLOCKED_EXTENSIONS', 'ADMIN_BLOCKED_MIME']) {
  if (uploadPolicyFactory.includes(forbidden) || service.includes(forbidden)) {
    violations.push(`Admin arbitrary-file upload is still restricted by ${forbidden}`)
  }
}
if (adminFileManager.includes('BLOCKED_UPLOAD_EXTENSIONS')) {
  violations.push('AdminFileManager: File Manager rename still restricts file extensions')
}
for (const token of ['function selectUpload(file: File | null)', "body.set('file', file, file.name)"]) {
  if (!includesLocalized(adminClient, token)) violations.push(`Admin File Manager client is missing ${token}`)
}
for (const token of [
  'selectUpload: [file: File | null]',
  '@drop.prevent="onDrop"',
  'admin-upload-dropzone',
  'admin-upload-selection',
  'Растровые изображения редактируются перед загрузкой',
  'type="file"',
  'Найти файл или каталог',
  'uploadBlockedReason',
  'admin-upload-disabled-reason',
  'Текущий каталог недоступен для записи процессу PHP',
]) {
  if (!includesLocalized(fileManager, token)) violations.push(`Admin File Manager UI is missing ${token}`)
}
if (/\baccept\s*=/.test(fileManager)) {
  violations.push('Admin File Manager file input must not restrict selectable file types through accept')
}

for (const token of [
  "'uploadSiteSocialImage' => 'uploadSiteSocialImage'",
  'UploadPurpose::SITE_SOCIAL_IMAGE',
  "'_siteSocialImageUpload'",
  'private function uploadSiteSocialImage()',
]) {
  if (!includesLocalized(adminOptions, token)) violations.push(`Admin social-card image backend is missing ${token}`)
}
for (const token of [
  "'uploadSiteSocialImage'",
  "$payload['_siteSocialImageUpload'] = $request->file('image')",
]) {
  if (!includesLocalized(adminPanel, token)) violations.push(`AdminPanel social-card multipart bridge is missing ${token}`)
}
for (const token of [
  'async function uploadSiteSocialImage(file: File)',
  "body.set('admPanel', 'uploadSiteSocialImage')",
  'siteSettings.ogImage = response.image',
  'siteSocialImageUploading',
  'siteSocialImageError',
]) {
  if (!includesLocalized(adminClient, token)) violations.push(`Admin social-card image client is missing ${token}`)
}
for (const token of [
  "public const SITE_SOCIAL_IMAGE = 'site.social_image'",
  'self::SITE_SOCIAL_IMAGE',
]) {
  if (!includesLocalized(uploadPurpose, token)) violations.push(`UploadPurpose social-card contract is missing ${token}`)
}
for (const token of [
  'UploadPurpose::SITE_SOCIAL_IMAGE => $this->siteSocialImagePolicy($authorize)',
  'UploadPermission::SITE_SOCIAL_IMAGE',
  "directory: 'site'",
  'minimumWidth: 600',
  'minimumHeight: 315',
  "'image/webp' => 'webp'",
]) {
  if (!includesLocalized(uploadPolicyFactory, token)) violations.push(`Social-card upload policy is missing ${token}`)
}
for (const token of [
  'ImageUploadField',
  ':preview="settings.ogImage"',
  'preview-mode="wide"',
  ':editor-aspect-ratio="1200 / 630"',
  ':minimum-width="600"',
  ':minimum-height="315"',
  `@select="emit('uploadImage', $event)"`,
  `@clear="emit('clearImage')"`,
  '&lt;meta property=&quot;og:image&quot;&gt;',
]) {
  if (!includesLocalized(siteSettingsEditor, token)) violations.push(`Site social-card shared upload is missing ${token}`)
}
for (const token of ["'og:image'", "'twitter:image'", "$site['ogImage']"]) {
  if (!includesLocalized(themeRenderer, token)) violations.push(`ThemeRenderer social-card metadata is missing ${token}`)
}

for (const token of [
  "'uploadServerImage' => 'uploadServerImage'",
  "UploadPurpose::SERVER_IMAGE",
  "'_serverImageUpload'",
  "private function uploadServerImage()",
  "normalizeServerImageReference",
  "validateReference(UploadPurpose::SERVER_IMAGE",
]) {
  if (!includesLocalized(adminOptions, token)) violations.push(`Admin server image backend is missing ${token}`)
}
for (const token of [
  "'uploadServerImage'",
  "$payload['_serverImageUpload'] = $request->file('image')",
]) {
  if (!includesLocalized(adminPanel, token)) violations.push(`AdminPanel multipart bridge is missing ${token}`)
}
for (const token of [
  'async function uploadServerImage(file: File)',
  "body.set('admPanel', 'uploadServerImage')",
  "body.set('image', file, file.name)",
  'serverDraft.serverImage = response.image',
  'serverImageUploading',
]) {
  if (!includesLocalized(adminClient, token)) violations.push(`Admin server image client is missing ${token}`)
}
for (const token of [
  'ImageUploadField',
  ':preview="serverImageUrl(draft.serverImage)"',
  'preview-mode="wide"',
  ':minimum-width="320"',
  ':minimum-height="180"',
  `@select="emit('uploadImage', $event)"`,
  `@clear="emit('clearImage')"`,
  '/uploads/servers/',
]) {
  if (!includesLocalized(serverEditor, token)) violations.push(`Server image shared form is missing ${token}`)
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
  'async function editSelected()',
  'Редактирование…',
  '@click="editSelected"',
  'editorTarget?: HTMLElement | null',
  "'editing-change': [active: boolean]",
  '<Teleport v-if="editing"',
  'ref="editorHost"',
  'pintura-inline-editor__mount',
]) {
  if (!includesLocalized(imageUploadField, token)) violations.push(`Shared ImageUploadField is missing ${token}`)
}
for (const token of [
  "import('@pqina/pintura')",
  "import('@pqina/pintura/locale/ru_RU')",
  "import('@pqina/pintura/pintura.css')",
  'export function isPinturaEditableImage',
  'export async function editImageWithPintura',
  'pintura.appendDefaultEditor',
  'pintura.createDefaultImageWriter',
  'target: HTMLElement',
  'options.target.isConnected',
  'options.target.replaceChildren()',
  "editor.on('process'",
  "'image/bmp'",
]) {
  if (!includesLocalized(pinturaImageEditor, token)) violations.push(`Pintura image editor service is missing ${token}`)
}
for (const forbidden of ['openDefaultEditor', 'PinturaModal']) {
  if (pinturaImageEditor.includes(forbidden)) violations.push(`Pintura must remain embedded; forbidden modal token found: ${forbidden}`)
}

for (const token of [
  'editImageWithPintura',
  'isPinturaEditableImage',
  'async function editRasterImage(file: File)',
  'async function editSelectedUpload()',
  '@click="editSelectedUpload"',
  'ref="imageEditorHost"',
  'admin-upload-editor__mount',
  'target,',
  'cancelImageEditing',
  'SVG и Minecraft skin/cape редактором не обрабатываются',
]) {
  if (!includesLocalized(fileManager, token)) violations.push(`Admin File Manager Pintura integration is missing ${token}`)
}

if (packageJson.dependencies?.['@pqina/pintura'] !== '^8.99.0') {
  violations.push('Theme dependencies must pin the compatible @pqina/pintura ^8.99.0 contract')
}

for (const token of [
  '.image-upload-field__dropzone.is-dragging',
  '.image-upload-field--wide .image-upload-field__preview',
  '.image-upload-field--circle .image-upload-field__preview',
  '.image-upload-field.has-error',
]) {
  if (!includesLocalized(imageUploadCss, token)) violations.push(`Shared image upload styles are missing ${token}`)
}

const sharedImageConsumers = new Map([
  ['ServerEditor.vue', serverEditor],
  ['ProfilePhotoDialog.vue', profilePhotoDialog],
  ['AppearanceOption.vue', appearanceOption],
  ['NewsEditor.vue', newsEditor],
  ['Slides.vue', slidesEditor],
  ['SiteSettings.vue', siteSettingsEditor],
])
for (const [name, source] of sharedImageConsumers) {
  if (!source.includes('ImageUploadField')) violations.push(`${name}: shared ImageUploadField is not used`)
  if (/<input\b[^>]*type="file"/i.test(source)) violations.push(`${name}: direct image file input remains outside ImageUploadField`)
}
for (const token of ['@select="selectFile"', '@clear="clearSelectedFile"', 'preview-mode="none"', ':editor-target="editorTarget"', '@editing-change="editorActive = $event"', 'profile-photo-dialog__editor-target', ':editor-aspect-ratio="1"', ':editor-target-width="512"', ':editor-target-height="512"', 'editor-mime-type="image/webp"']) {
  if (!includesLocalized(profilePhotoDialog, token)) violations.push(`Profile photo dialog shared upload is missing ${token}`)
}
for (const [name, source] of [['SkinOption.vue', skinOption], ['CloakOption.vue', cloakOption]]) {
  if (!source.includes('<input type="file" accept="image/png"')) violations.push(`${name}: direct PNG selector must remain for Minecraft texture uploads`)
  if (source.includes('ImageUploadField') || source.includes('Pintura') || source.includes('pinturaImageEditor')) {
    violations.push(`${name}: Minecraft skin/cape must remain excluded from Pintura`)
  }
}

for (const token of ['preview-mode="circle"', "@select=\"emit('selectAvatar', $event)\"", '#actions']) {
  if (!includesLocalized(appearanceOption, token)) violations.push(`Profile appearance shared upload is missing ${token}`)
}
for (const token of ['preview-mode="circle"', '@select="selectCover"', "@clear=\"draft.coverImage = ''\""]) {
  if (!includesLocalized(newsEditor, token)) violations.push(`News cover shared upload is missing ${token}`)
}
for (const token of ['preview-mode="none"', ':editor-aspect-ratio="false"', '@select="selectImage(selectedIndex, $event)"', "@clear=\"selectedSlide.image = ''\""]) {
  if (!includesLocalized(slidesEditor, token)) violations.push(`Slide shared upload is missing ${token}`)
}

for (const token of ['export function serverImageUrl', "themeAsset(", "img/servers/${themeRelative}"]) {
  if (!includesLocalized(serverImageDomain, token)) violations.push(`Server image URL resolver is missing ${token}`)
}
for (const token of ['serverImageUrl', 'coverUrl', '@error="coverFailed = true"']) {
  if (!includesLocalized(serverPage, token)) violations.push(`Public server image preview is missing ${token}`)
}
for (const token of [
  "str_starts_with($reference, '/uploads/servers/')",
  "ROOT_DIR . UPLOADS_DIR . 'servers'",
  "['image/jpeg', 'image/png', 'image/webp']",
]) {
  if (!includesLocalized(systemRequests, token)) violations.push(`Launcher server image endpoint is missing ${token}`)
}

if (violations.length > 0) {
  console.error('Upload contract violations:')
  for (const violation of violations) console.error(`- ${violation}`)
  process.exit(1)
}

console.log('Upload contract passed: centralized uploads use Pintura for ordinary raster images while Minecraft skin/cape remain unmodified.')
