import { mkdir } from 'node:fs/promises'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { rolldown } from 'rolldown'

const themeRoot = dirname(dirname(fileURLToPath(import.meta.url)))
const outputDirectory = join(themeRoot, 'assets', 'runtime', 'server')
const outputFile = join(outputDirectory, 'runtime-template-compiler.mjs')
await mkdir(outputDirectory, { recursive: true })
const bundle = await rolldown({
  input: join(themeRoot, 'scripts', 'compile-runtime-template.mjs'),
  platform: 'node',
  external: (id) => id.startsWith('node:'),
  treeshake: true,
  logLevel: 'warn',
})
await bundle.write({
  file: outputFile,
  format: 'esm',
  codeSplitting: false,
  sourcemap: false,
  minify: true,
  banner: '/* FoxCMS standalone CSP-safe runtime TPL compiler. Generated; do not edit. */',
})
console.log('scripts/compile-runtime-template.mjs -> assets/runtime/server/runtime-template-compiler.mjs')
