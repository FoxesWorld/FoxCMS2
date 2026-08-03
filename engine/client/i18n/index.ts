import { computed, readonly, ref } from 'vue'
import ruRU from './locales/ru-RU.json'

export type TranslationKey = keyof typeof ruRU
export type LocaleMessages = Partial<Record<TranslationKey, string>>
export type TranslationParameters = ReadonlyArray<unknown> | Readonly<Record<string, unknown>>

const DEFAULT_LOCALE = 'ru-RU'
const localeModules = import.meta.glob<LocaleMessages>('./locales/*.json', {
  eager: true,
  import: 'default',
})
const dictionaries = new Map<string, LocaleMessages>()
const missingKeys = new Set<TranslationKey>()

for (const [path, messages] of Object.entries(localeModules)) {
  const localeCode = path.match(/\/([^/]+)\.json$/)?.[1]
  if (localeCode) dictionaries.set(localeCode, messages)
}
if (!dictionaries.has(DEFAULT_LOCALE)) dictionaries.set(DEFAULT_LOCALE, ruRU)

const activeLocale = ref(resolveAvailableLocale(resolveRequestedLocale()) ?? DEFAULT_LOCALE)

export const locale = readonly(activeLocale)
export const availableLocales = computed(() => [...dictionaries.keys()].sort())

export function registerLocale(localeCode: string, messages: LocaleMessages): void {
  const normalized = normalizeLocale(localeCode)
  dictionaries.set(normalized, messages)
}

export function setLocale(localeCode: string): boolean {
  const resolved = resolveAvailableLocale(localeCode)
  if (!resolved) return false
  activeLocale.value = resolved
  document.documentElement.lang = resolved
  return true
}

export function getLocale(): string {
  return activeLocale.value
}

export function t(key: TranslationKey, parameters?: TranslationParameters): string {
  const dictionary = dictionaries.get(activeLocale.value)
  const fallback = dictionaries.get(DEFAULT_LOCALE) ?? ruRU
  const template = dictionary?.[key] ?? fallback[key]

  if (template === undefined) {
    if (!missingKeys.has(key)) {
      missingKeys.add(key)
      console.warn(`[i18n] Missing translation: ${key}`)
    }
    return key
  }

  if (!parameters) return template
  return template.replace(/\{([A-Za-z0-9_.-]+)\}/g, (placeholder, token: string) => {
    const value = Array.isArray(parameters)
      ? parameters[Number(token)]
      : (parameters as Readonly<Record<string, unknown>>)[token]
    return value === undefined || value === null ? placeholder : String(value)
  })
}

export function formatNumber(value: number, options?: Intl.NumberFormatOptions): string {
  return new Intl.NumberFormat(activeLocale.value, options).format(value)
}

export function formatDate(
  value: Date | number | string,
  options?: Intl.DateTimeFormatOptions,
): string {
  const date = value instanceof Date ? value : new Date(value)
  return new Intl.DateTimeFormat(activeLocale.value, options).format(date)
}

export function formatRelativeTime(
  value: number,
  unit: Intl.RelativeTimeFormatUnit,
  options?: Intl.RelativeTimeFormatOptions,
): string {
  return new Intl.RelativeTimeFormat(activeLocale.value, options).format(value, unit)
}

function resolveRequestedLocale(): string {
  if (typeof document === 'undefined') return DEFAULT_LOCALE
  return document.documentElement.lang || navigator.language || DEFAULT_LOCALE
}

function resolveAvailableLocale(localeCode: string): string | null {
  const normalized = normalizeLocale(localeCode)
  const exact = [...dictionaries.keys()].find((candidate) => candidate.toLowerCase() === normalized.toLowerCase())
  if (exact) return exact
  const language = normalized.split('-')[0]?.toLowerCase()
  return [...dictionaries.keys()].find((candidate) => candidate.split('-')[0]?.toLowerCase() === language) ?? null
}

function normalizeLocale(localeCode: string): string {
  return localeCode.trim().replace('_', '-') || DEFAULT_LOCALE
}
