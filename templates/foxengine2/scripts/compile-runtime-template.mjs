import process from 'node:process'
import { compileRuntimeTemplateBody } from './runtime-template-compiler.mjs'

function argument(name) {
  const index = process.argv.indexOf(name)
  return index >= 0 ? String(process.argv[index + 1] ?? '') : ''
}

const id = argument('--id')
const bridgeUrl = argument('--bridge-url')
let body = ''
for await (const chunk of process.stdin) body += chunk
try {
  process.stdout.write(compileRuntimeTemplateBody(body, id, bridgeUrl))
} catch (error) {
  process.stderr.write(error instanceof Error ? error.message : String(error))
  process.exitCode = 1
}
