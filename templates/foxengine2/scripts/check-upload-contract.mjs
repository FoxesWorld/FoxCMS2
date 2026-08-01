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
  ['engine/classes/modules/AdminPanel/AdminOptions.class.php', ['UploadService', 'UploadPurpose::ADMIN_FILE', 'UploadPurpose::SLIDER_IMAGE']],
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
  'UploadPermission::MINECRAFT_ANY',
  'UploadPermission::PROFILE_ANY',
]) {
  if (!service.includes(token)) violations.push(`UploadService: missing contract ${token}`)
}

if (violations.length > 0) {
  console.error('Upload contract violations:')
  for (const violation of violations) console.error(`- ${violation}`)
  process.exit(1)
}

console.log('Upload contract passed: all server uploads use the centralized policy service with audit logging.')
