export interface Slide {
  id: string
  enabled: boolean
  title: string
  description: string
  image: string
  route: string
  action: string
  secondaryRoute: string
  secondaryAction: string
}

export interface SliderSettings {
  schema: number
  eyebrow: string
  autoplayMs: number
  slides: Slide[]
}

interface SliderNormalizationOptions {
  defaultAction: string
  resolveImage: (path: string) => string
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : null
}

function asString(value: unknown, fallback = ''): string {
  return typeof value === 'string' || typeof value === 'number' ? String(value) : fallback
}

function asBoolean(value: unknown, fallback = true): boolean {
  return typeof value === 'boolean' ? value : fallback
}

export function normalizeSliderSettings(
  value: unknown,
  options: SliderNormalizationOptions,
): SliderSettings {
  const configured = asRecord(value)
  const source = Array.isArray(configured?.slides) ? configured.slides : []
  const slides = source.flatMap((raw): Slide[] => {
    const entry = asRecord(raw)
    if (!entry) return []

    const id = asString(entry.id).trim()
    const title = asString(entry.title).trim()
    const image = asString(entry.image).trim()
    const route = asString(entry.route).trim()
    const enabled = asBoolean(entry.enabled)
    if (!enabled || !id || !title || !image || !route) return []

    return [{
      id,
      enabled,
      title,
      description: asString(entry.description).trim(),
      image: options.resolveImage(image),
      route,
      action: asString(entry.action, options.defaultAction).trim() || options.defaultAction,
      secondaryRoute: asString(entry.secondaryRoute).trim(),
      secondaryAction: asString(entry.secondaryAction).trim(),
    }]
  })

  const requestedInterval = Number(configured?.autoplayMs ?? 7000)
  return {
    schema: Number(configured?.schema ?? 1),
    eyebrow: asString(configured?.eyebrow, 'FoxesCraft').trim(),
    autoplayMs: Number.isFinite(requestedInterval) ? Math.max(0, requestedInterval) : 7000,
    slides,
  }
}

export function sliderRuntimeDataUrl(themeName: string): string {
  return `/templates/${encodeURIComponent(themeName || 'foxengine2')}/data/slides.json`
}
