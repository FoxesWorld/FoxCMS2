import { access } from 'node:fs/promises'
import { basename, join } from 'node:path'
import { fileURLToPath } from 'node:url'

export const themeRoot = fileURLToPath(new URL('..', import.meta.url))
export const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
export const themeName = basename(themeRoot)
if (!/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(themeName)) throw new Error(`Invalid theme directory: ${themeName}`)
export const sourceRoot = join(themeRoot, 'src')
export const runtimeRoot = join(themeRoot, 'assets', 'runtime')
export const themeManifestPath = join(themeRoot, 'theme.json')
export const themeFrontendPath = join(themeRoot, 'frontend.json')
export const themeShellPath = join(themeRoot, 'index.html')
export const engineClientRoot = join(repositoryRoot, 'engine', 'client')
export const modulesRoot = join(repositoryRoot, 'engine', 'classes', 'modules')
export const clientSourceRoots = [sourceRoot, engineClientRoot, modulesRoot]

export async function assertThemeSource() {
  await access(join(sourceRoot, 'main.ts'))
  await access(join(themeRoot, 'tsconfig.json'))
  await access(themeManifestPath)
  await access(themeFrontendPath)
  await access(join(engineClientRoot, 'runtime', 'mountEngine.ts'))
}
