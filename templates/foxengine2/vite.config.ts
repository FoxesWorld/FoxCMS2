import { fileURLToPath, URL } from 'node:url'
import { basename, resolve } from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const themeRoot = fileURLToPath(new URL('.', import.meta.url))
const repositoryRoot = resolve(themeRoot, '..', '..')
const themeName = basename(themeRoot)
if (!/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(themeName)) {
  throw new Error(`Invalid theme directory: ${themeName}`)
}
const themeSource = resolve(themeRoot, 'src')
const engineClient = resolve(repositoryRoot, 'engine', 'client')
const engineModules = resolve(repositoryRoot, 'engine', 'classes', 'modules')
const themeModules = resolve(themeRoot, 'node_modules')

export default defineConfig({
  root: repositoryRoot,
  base: `/templates/${encodeURIComponent(themeName)}/assets/runtime/`,
  publicDir: false,
  cacheDir: resolve(themeRoot, '.vite'),
  plugins: [vue()],
  resolve: {
    alias: {
      '@': engineClient,
      '@engine': engineClient,
      '@theme': themeSource,
      '@modules': engineModules,
      'vue': resolve(themeModules, 'vue', 'dist', 'vue.runtime.esm-bundler.js'),
      'vue-router': resolve(themeModules, 'vue-router', 'dist', 'vue-router.mjs'),
      '@pqina/pintura': resolve(themeModules, '@pqina', 'pintura'),
    },
    dedupe: ['vue', 'vue-router'],
  },
  build: {
    outDir: resolve(themeRoot, 'assets', 'runtime'),
    emptyOutDir: true,
    sourcemap: true,
    target: 'es2022',
    cssCodeSplit: false,
    rollupOptions: {
      input: resolve(themeSource, 'main.ts'),
      output: {
        entryFileNames: 'theme.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) =>
          assetInfo.names?.some((name) => name.endsWith('.css'))
            ? 'theme.css'
            : 'assets/[name]-[hash][extname]',
      },
    },
  },
})
