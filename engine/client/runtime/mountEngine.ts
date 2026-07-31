import { createApp, type Component } from 'vue'
import { router } from '@/router'
import { appBootstrap } from '@/app/context'

export function mountEngine(rootComponent: Component): void {
  const mountId = appBootstrap.theme.mount || 'foxescraft-app'
  const mountPoint = document.getElementById(mountId)
  if (!mountPoint) throw new Error(`Theme mount point was not found: ${mountId}`)

  const application = createApp(rootComponent)
  application.config.errorHandler = (error, instance, info) => {
    console.error('[FoxesCraft] Client runtime error', { error, instance, info })
  }
  application.use(router)
  application.mount(mountPoint)
  document.documentElement.dataset.themeReady = 'true'
}
