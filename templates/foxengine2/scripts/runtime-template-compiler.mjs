import { createHash } from 'node:crypto'
import { compile } from '@vue/compiler-dom'

const idPattern = /^[a-z][a-z0-9-]{1,63}$/u
const bridgePattern = /^\/templates\/[A-Za-z0-9_-]+\/assets\/runtime\/vue-runtime\.js$/u

export function parseRuntimeTemplate(source, expectedId = '') {
  const root = source.match(/^\s*<(fox-page-template|fox-user-options-template)\b([^>]*)>([\s\S]*)<\/\1>\s*$/u)
  if (!root) throw new Error('Runtime TPL must contain one supported root element.')
  const attributes = Object.fromEntries(
    [...root[2].matchAll(/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*"([^"]*)"/gu)]
      .map((match) => [match[1].toLowerCase(), match[2]]),
  )
  const id = String(attributes.id ?? '')
  if (!idPattern.test(id) || (expectedId && id !== expectedId) || attributes.schema !== '1') {
    throw new Error(`Invalid runtime TPL id/schema: ${id || '(empty)'}`)
  }
  const match = root[3].match(/<fox-template-body\b[^>]*>([\s\S]*?)<\/fox-template-body>/u)
  const body = match?.[1]?.trim() ?? ''
  if (!body) throw new Error(`Runtime TPL ${id} has no fox-template-body.`)
  return {
    id,
    revision: Math.max(1, Math.trunc(Number(attributes.revision) || 1)),
    body,
  }
}

export function compileRuntimeTemplateBody(body, id, bridgeUrl) {
  if (!idPattern.test(id)) throw new Error(`Invalid runtime template id: ${id}`)
  if (!bridgePattern.test(bridgeUrl)) throw new Error(`Invalid Vue runtime bridge URL: ${bridgeUrl}`)
  const problems = []
  const result = compile(body, {
    mode: 'module',
    prefixIdentifiers: true,
    hoistStatic: true,
    cacheHandlers: false,
    runtimeModuleName: bridgeUrl,
    onError: (problem) => problems.push(problem.message),
    onWarn: (problem) => problems.push(problem.message),
  })
  if (problems.length) throw new Error(problems.join('; '))
  const sourceHash = createHash('sha256').update(body, 'utf8').digest('hex')
  return [
    `/* fox-runtime-template id=${id} sha256=${sourceHash} */`,
    result.code,
    `export const templateId = ${JSON.stringify(id)}`,
    `export const sourceHash = ${JSON.stringify(sourceHash)}`,
    '',
  ].join('\n')
}

export function compileRuntimeTemplateSource(source, expectedId, bridgeUrl) {
  const parsed = parseRuntimeTemplate(source, expectedId)
  return { ...parsed, module: compileRuntimeTemplateBody(parsed.body, parsed.id, bridgeUrl) }
}
