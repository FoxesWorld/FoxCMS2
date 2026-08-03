export interface PinturaImageEditorOptions {
  target: HTMLElement
  aspectRatio?: number | false
  quality?: number
  minimumWidth?: number
  minimumHeight?: number
  maximumWidth?: number
  maximumHeight?: number
  targetWidth?: number
  targetHeight?: number
  targetFit?: 'contain' | 'cover' | 'force'
  upscale?: boolean
  mimeType?: string
  outputName?: string
  signal?: AbortSignal
}

const EDITABLE_MIME_TYPES = new Set([
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'image/avif',
  'image/bmp',
])

export function isPinturaEditableImage(file: File): boolean {
  return EDITABLE_MIME_TYPES.has(file.type.toLowerCase())
}

export async function editImageWithPintura(
  file: File,
  options: PinturaImageEditorOptions,
): Promise<File | null> {
  if (options.signal?.aborted) return null
  if (!options.target.isConnected) throw new Error('Контейнер редактора изображения недоступен.')

  const [pintura, localeModule] = await Promise.all([
    import('@pqina/pintura'),
    import('@pqina/pintura/locale/ru_RU'),
    import('@pqina/pintura/pintura.css'),
  ])

  if (options.signal?.aborted) return null
  if (!options.target.isConnected) throw new Error('Контейнер редактора изображения был закрыт.')

  const writerOptions: Record<string, unknown> = {
    quality: clamp(options.quality ?? 0.9, 0, 1),
    renameFile: () => outputName(file, options),
  }
  if (options.mimeType) writerOptions.mimeType = options.mimeType

  const targetWidth = positive(options.targetWidth)
  const targetHeight = positive(options.targetHeight)
  const maximumWidth = positive(options.maximumWidth)
  const maximumHeight = positive(options.maximumHeight)
  if (targetWidth || targetHeight || maximumWidth || maximumHeight) {
    writerOptions.targetSize = {
      width: targetWidth || maximumWidth || undefined,
      height: targetHeight || maximumHeight || undefined,
      fit: options.targetFit ?? (targetWidth || targetHeight ? 'cover' : 'contain'),
      upscale: options.upscale ?? Boolean(targetWidth || targetHeight),
    }
  }

  const editorOptions: Record<string, unknown> = {
    src: file,
    locale: localeModule.default,
    imageWriter: pintura.createDefaultImageWriter(writerOptions),
  }

  const minimumWidth = positive(options.minimumWidth)
  const minimumHeight = positive(options.minimumHeight)
  if (minimumWidth || minimumHeight) {
    editorOptions.imageCropMinSize = {
      width: minimumWidth || 1,
      height: minimumHeight || 1,
    }
  }
  if (options.aspectRatio !== false && positive(options.aspectRatio)) {
    editorOptions.imageCropAspectRatio = options.aspectRatio
  }

  options.target.replaceChildren()

  return await new Promise<File | null>((resolve, reject) => {
    let settled = false
    const editor = pintura.appendDefaultEditor(
      options.target,
      editorOptions as Parameters<typeof pintura.appendDefaultEditor>[1],
    )

    const cleanup = (): void => {
      options.signal?.removeEventListener('abort', abort)
      try { editor.destroy() } catch { /* editor is already destroyed */ }
      options.target.replaceChildren()
    }
    const settle = (value: File | null): void => {
      if (settled) return
      settled = true
      cleanup()
      resolve(value)
    }
    const fail = (reason: unknown): void => {
      if (settled) return
      settled = true
      cleanup()
      reject(new Error(errorMessage(reason, 'Pintura не смогла обработать изображение.')))
    }
    const abort = (): void => settle(null)

    options.signal?.addEventListener('abort', abort, { once: true })

    editor.on('process', (result?: { dest?: Blob }) => {
      const output = result?.dest
      if (!(output instanceof Blob)) {
        fail(new Error('Редактор не вернул итоговый файл.'))
        return
      }
      settle(new File([output], outputName(file, options), {
        type: output.type || options.mimeType || file.type || 'image/png',
        lastModified: Date.now(),
      }))
    })
    editor.on('processerror', fail)
    editor.on('loaderror', fail)
    editor.on('close', () => settle(null))
    editor.on('destroy', () => {
      if (!settled) settle(null)
    })
  })
}

function outputName(source: File, options: PinturaImageEditorOptions): string {
  if (options.outputName?.trim()) return options.outputName.trim()
  const mime = options.mimeType || source.type || 'image/png'
  const extension = extensionForMime(mime, source.name)
  const stem = source.name.replace(/\.[^.]+$/u, '') || 'image'
  return `${stem}-edited.${extension}`
}

function extensionForMime(mime: string, fallbackName: string): string {
  const normalized = mime.toLowerCase()
  if (normalized === 'image/jpeg') return 'jpg'
  if (normalized.startsWith('image/')) return normalized.slice(6).replace('jpeg', 'jpg') || 'png'
  const fallback = fallbackName.includes('.') ? fallbackName.split('.').pop()?.toLowerCase() : ''
  return fallback || 'png'
}

function positive(value: number | undefined): number {
  return typeof value === 'number' && Number.isFinite(value) && value > 0 ? value : 0
}

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(maximum, Math.max(minimum, value))
}

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message.trim() !== '') return error.message
  if (typeof error === 'string' && error.trim() !== '') return error
  return fallback
}
