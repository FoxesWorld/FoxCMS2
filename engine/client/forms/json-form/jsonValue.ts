import { t } from '@/i18n'
import type { JsonKind, JsonObject, JsonRootKind, JsonValue } from './types'

export function isJsonObject(value: unknown): value is JsonObject {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

export function normalizeJsonValue(value: unknown, fallback: JsonValue = ''): JsonValue {
  if (value === null) return null
  if (typeof value === 'string' || typeof value === 'boolean') return value
  if (typeof value === 'number') return Number.isFinite(value) ? value : fallback
  if (Array.isArray(value)) return value.map((item) => normalizeJsonValue(item))
  if (isJsonObject(value)) {
    return Object.fromEntries(
      Object.entries(value)
        .filter(([, item]) => item !== undefined && typeof item !== 'function' && typeof item !== 'symbol')
        .map(([key, item]) => [key, normalizeJsonValue(item)]),
    )
  }
  return fallback
}

export function decodeJsonValue(value: unknown, fallback: JsonValue = ''): JsonValue {
  if (typeof value !== 'string') return normalizeJsonValue(value, fallback)
  const trimmed = value.trim()
  if (trimmed === '') return ''
  try { return normalizeJsonValue(JSON.parse(trimmed), fallback) }
  catch { return value }
}

export function cloneJsonValue(value: JsonValue): JsonValue {
  if (Array.isArray(value)) return value.map((item) => cloneJsonValue(item))
  if (isJsonObject(value)) return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, cloneJsonValue(item)]))
  return value
}

export function jsonKindOf(value: JsonValue): JsonKind {
  if (value === null) return 'null'
  if (Array.isArray(value)) return 'array'
  if (isJsonObject(value)) return 'object'
  return typeof value as 'string' | 'number' | 'boolean'
}

function meaningfulSamples(samples: readonly unknown[]): JsonValue[] {
  return samples
    .filter((sample) => sample !== undefined)
    .map((sample) => decodeJsonValue(sample))
    .filter((sample) => sample !== '')
}

export function inferJsonKind(value: JsonValue, samples: readonly unknown[] = [], preferred: JsonRootKind = 'auto'): JsonKind {
  if (preferred !== 'auto') return preferred
  const normalizedSamples = meaningfulSamples(samples)
  if ((value === '' || value === null) && normalizedSamples.length > 0) {
    const counts = new Map<JsonKind, number>()
    for (const sample of normalizedSamples) {
      const kind = jsonKindOf(sample)
      counts.set(kind, (counts.get(kind) ?? 0) + 1)
    }
    return [...counts.entries()].sort((left, right) => right[1] - left[1])[0]?.[0] ?? jsonKindOf(value)
  }
  return jsonKindOf(value)
}

export function defaultJsonValue(kind: JsonKind): JsonValue {
  if (kind === 'object') return {}
  if (kind === 'array') return []
  if (kind === 'number') return 0
  if (kind === 'boolean') return false
  if (kind === 'null') return null
  return ''
}

export function createJsonTemplate(samples: readonly unknown[] = [], preferred: JsonRootKind = 'auto'): JsonValue {
  const normalized = meaningfulSamples(samples)
  const seed = normalized[0] ?? ''
  const kind = inferJsonKind(seed, normalized, preferred)

  if (kind === 'object') {
    const objects = normalized.filter(isJsonObject)
    const keys = new Set<string>()
    for (const object of objects) Object.keys(object).forEach((key) => keys.add(key))
    return Object.fromEntries(
      [...keys].map((key) => [key, createJsonTemplate(objects.flatMap((object) => key in object ? [object[key]] : []))]),
    )
  }
  if (kind === 'array') return []
  return defaultJsonValue(kind)
}

export function createJsonObjectTemplate(samples: readonly unknown[] = [], fields: readonly string[] = []): JsonObject {
  const objects = meaningfulSamples(samples).filter(isJsonObject)
  const keys = new Set(fields)
  for (const object of objects) Object.keys(object).forEach((key) => keys.add(key))
  return Object.fromEntries(
    [...keys].map((key) => {
      const values = objects.flatMap((object) => key in object ? [object[key]] : [])
      const value = /colou?r/i.test(key) ? '#ffffff' : createJsonTemplate(values)
      return [key, value]
    }),
  )
}

export function mergeJsonWithTemplate(value: JsonValue, template: JsonValue): JsonValue {
  if (isJsonObject(template)) {
    const source = isJsonObject(value) ? value : {}
    const result: JsonObject = {}
    const keys = new Set([...Object.keys(template), ...Object.keys(source)])
    for (const key of keys) {
      if (key in source && key in template) result[key] = mergeJsonWithTemplate(source[key], template[key])
      else if (key in source) result[key] = cloneJsonValue(source[key])
      else result[key] = cloneJsonValue(template[key])
    }
    return result
  }
  if (Array.isArray(template) && Array.isArray(value)) return cloneJsonValue(value)
  return cloneJsonValue(value)
}

export function collectJsonSamples<T extends Record<string, unknown>>(records: readonly T[], field: keyof T | string): JsonValue[] {
  return records
    .filter((record) => Object.prototype.hasOwnProperty.call(record, field))
    .map((record) => decodeJsonValue(record[String(field)]))
}

export function humanizeJsonKey(key: string): string {
  const normalized = key
    .replace(/[_-]+/g, ' ')
    .replace(/([a-zа-яё0-9])([A-ZА-ЯЁ])/g, '$1 $2')
    .replace(/\s+/g, ' ')
    .trim()
  return normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : t('engine.forms.json-form.jsonvalue.001')
}
