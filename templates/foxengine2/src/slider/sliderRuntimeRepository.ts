import { t } from '@/i18n'
import { loadRuntimeJson, RuntimeJsonHttpError } from '@engine/runtime/runtimeJson'
import {
  normalizeSliderSettings,
  sliderRuntimeDataUrl,
  type SliderSettings,
} from './sliderSettings'

export async function loadSliderRuntimeSettings(
  themeName: string,
  resolveImage: (path: string) => string,
): Promise<SliderSettings> {
  let payload: unknown
  try {
    payload = await loadRuntimeJson<unknown>(sliderRuntimeDataUrl(themeName))
  } catch (error) {
    if (error instanceof RuntimeJsonHttpError) {
      throw new Error(t('theme.slider.008', [error.status]))
    }
    throw error
  }

  const settings = normalizeSliderSettings(payload, {
    defaultAction: t('theme.slider.007'),
    resolveImage,
  })
  if (settings.slides.length === 0) throw new Error(t('theme.slider.009'))
  return settings
}
