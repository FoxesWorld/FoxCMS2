import { readFile, readdir } from 'node:fs/promises'
import { extname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const ignoredDirectories = new Set([
  '.git',
  '.idea',
  'node_modules',
  '.vite',
  'templates_c',
  'cache',
  'uploads',
  'files',
])
const ignoredFiles = new Set(['.env.example', 'package-lock.json', 'site-settings.json'])
const extensions = new Set(['.php', '.js', '.mjs', '.cjs', '.ts', '.vue', '.json', '.yml', '.yaml', '.xml', '.ini'])
const findings = []

const contextualSecret = /(?:dbPass|db_pass|smtp_pass|smtpPassword|hcaptchaSecret|secretKey|accessToken|secureKey|apiToken|apiKey)\s*(?:=>|:|=)\s*["']([^"']{8,})["']/gi
const privateKey = /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/g
const credentialUrl = /\b[a-z][a-z0-9+.-]*:\/\/[^\s/:]+:[^\s/@]+@/gi

async function walk(directory) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.isDirectory() && ignoredDirectories.has(entry.name)) continue
    const path = join(directory, entry.name)
    if (entry.isDirectory()) {
      await walk(path)
      continue
    }
    if (ignoredFiles.has(entry.name) || !extensions.has(extname(entry.name).toLowerCase())) continue

    const text = await readFile(path, 'utf8')
    const rel = relative(repositoryRoot, path).replaceAll('\\', '/')
    for (const pattern of [contextualSecret, privateKey, credentialUrl]) {
      pattern.lastIndex = 0
      for (const match of text.matchAll(pattern)) {
        const line = text.slice(0, match.index).split('\n').length
        findings.push(`${rel}:${line}`)
      }
    }
  }
}

await walk(repositoryRoot)

if (findings.length) {
  console.error('Potential committed secrets detected:')
  for (const finding of [...new Set(findings)]) console.error(`- ${finding}`)
  console.error('Move the value to an environment variable and rotate the exposed credential.')
  process.exit(1)
}

console.log('Secret scan passed: no hardcoded credential assignments or private keys detected.')
