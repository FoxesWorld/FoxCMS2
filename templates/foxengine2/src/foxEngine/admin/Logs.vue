<script setup lang="ts">
import UiCheckbox from '@/components/UiCheckbox.vue'
import type { LogEntry } from '@modules/AdminPanel/client/useAdminPanel'

type LogFile = 'lastlog' | 'error' | 'access'
interface LogMeta { label: string; value: string; kind?: string }

const props = defineProps<{ file: LogFile; entries: LogEntry[]; autoRefresh: boolean }>()
const emit = defineEmits<{
  'update:file': [value: LogFile]
  'update:autoRefresh': [value: boolean]
  reload: []
  clear: []
}>()

const reservedContextFields = new Set([
  'action', 'actionField', 'handler', 'requestChannel', 'moduleName', 'moduleClass',
  'modulePriority', 'loadedCount', 'authenticated', 'sessionState',
])

function updateFile(event: Event): void { emit('update:file', (event.target as HTMLSelectElement).value as LogFile) }
function updateAuto(value: boolean): void { emit('update:autoRefresh', value) }

function contextValue(entry: LogEntry, key: string): unknown {
  return entry.context && Object.prototype.hasOwnProperty.call(entry.context, key)
    ? entry.context[key]
    : undefined
}

function scalar(value: unknown): string {
  if (typeof value === 'string') return value.trim()
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)
  return ''
}

function metadata(entry: LogEntry): LogMeta[] {
  const result: LogMeta[] = []
  if (entry.httpMethod || entry.httpPath) {
    result.push({ label: 'HTTP', value: `${entry.httpMethod || '?'} ${entry.httpPath || '/'}`, kind: 'request' })
  }
  if (entry.operation) result.push({ label: 'РћРїРµСЂР°С†РёСЏ', value: entry.operation, kind: 'operation' })
  if (entry.component) result.push({ label: 'РљРѕРјРїРѕРЅРµРЅС‚', value: entry.component })

  const channel = entry.requestChannel || scalar(contextValue(entry, 'requestChannel'))
  if (channel) result.push({ label: 'РљР°РЅР°Р»', value: channel })
  const action = entry.action || scalar(contextValue(entry, 'action'))
  if (action) result.push({ label: 'Action', value: action, kind: 'action' })
  const handler = entry.handler || scalar(contextValue(entry, 'handler'))
  if (handler) result.push({ label: 'Handler', value: handler })

  const moduleName = scalar(contextValue(entry, 'moduleName'))
  const modulePriority = scalar(contextValue(entry, 'modulePriority'))
  if (moduleName) result.push({ label: 'РњРѕРґСѓР»СЊ', value: modulePriority ? `${moduleName} В· ${modulePriority}` : moduleName })

  if (entry.actorLogin || entry.actorGroup) {
    const login = entry.actorLogin && entry.actorLogin !== 'anonymous' ? entry.actorLogin : 'guest'
    result.push({ label: 'РџРѕР»СЊР·РѕРІР°С‚РµР»СЊ', value: `${login}${entry.actorGroup ? ` [${entry.actorGroup}]` : ''}`, kind: 'actor' })
  }
  if (entry.httpStatus != null) result.push({ label: 'РЎС‚Р°С‚СѓСЃ', value: String(entry.httpStatus), kind: entry.httpStatus >= 400 ? 'status-error' : 'status-ok' })
  if (entry.durationMs != null) result.push({ label: 'Р’СЂРµРјСЏ', value: `${entry.durationMs.toFixed(3)} ms`, kind: entry.durationMs >= 2000 ? 'slow' : '' })
  if (entry.outcome) result.push({ label: 'Р РµР·СѓР»СЊС‚Р°С‚', value: entry.outcome })

  const loaded = contextValue(entry, 'loadedModules')
  if (Array.isArray(loaded) && loaded.length) {
    result.push({ label: 'Р—Р°РіСЂСѓР¶РµРЅРѕ', value: loaded.map(String).join(', '), kind: 'modules' })
  }
  return result
}

function diagnosticContext(entry: LogEntry): Array<[string, unknown]> {
  if (!entry.context) return []
  return Object.entries(entry.context)
    .filter(([key, value]) => !reservedContextFields.has(key) && value !== '' && value !== null && value !== undefined)
    .sort(([left], [right]) => left.localeCompare(right))
}

function formatValue(value: unknown): string {
  if (typeof value === 'string') return value
  if (typeof value === 'number' || typeof value === 'boolean' || value === null) return String(value)
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

function hasDetails(entry: LogEntry): boolean {
  return Boolean(
    entry.requestId || entry.correlationId || entry.actorUuid || entry.deviation || entry.exception
    || diagnosticContext(entry).length,
  )
}

function entryKey(entry: LogEntry, index: number): string {
  return `${entry.timestamp}-${entry.requestId || entry.event}-${index}`
}
</script>

<template>
  <section class="admin-section admin-log-section">
    <div class="admin-toolbar">
      <select :value="file" aria-label="Р¤Р°Р№Р» Р¶СѓСЂРЅР°Р»Р°" @change="updateFile">
        <option value="lastlog">lastlog.log</option>
        <option value="error">error.log</option>
        <option value="access">access.log</option>
      </select>
      <UiCheckbox
        :model-value="autoRefresh"
        class="admin-auto-refresh"
        variant="switch"
        compact
        label="РђРІС‚РѕРѕР±РЅРѕРІР»РµРЅРёРµ"
        description="РљР°Р¶РґС‹Рµ 10 СЃРµРєСѓРЅРґ"
        @update:model-value="updateAuto"
      />
      <button class="button button--ghost" type="button" @click="emit('reload')">РћР±РЅРѕРІРёС‚СЊ</button>
      <button class="button button--ghost" type="button" @click="emit('clear')">РћС‡РёСЃС‚РёС‚СЊ</button>
    </div>

    <div class="admin-log">
      <p v-if="!props.entries.length" class="admin-log__empty">Р–СѓСЂРЅР°Р» РїСѓСЃС‚.</p>
      <article
        v-for="(entry, index) in props.entries"
        :key="entryKey(entry, index)"
        class="admin-log-line"
        :class="`admin-log-line--${entry.tone}`"
      >
        <header class="admin-log-line__header">
          <time :datetime="entry.timestamp">{{ entry.time }}</time>
          <b class="admin-log-line__level">{{ entry.level }}</b>
          <code>{{ entry.event || 'application.log' }}</code>
          <span v-if="entry.malformed" class="admin-log-line__malformed">legacy/unparsed</span>
        </header>

        <p class="admin-log-line__message">{{ entry.message }}</p>

        <div v-if="metadata(entry).length" class="admin-log-line__meta">
          <span
            v-for="item in metadata(entry)"
            :key="`${item.label}-${item.value}`"
            :class="item.kind ? `admin-log-meta--${item.kind}` : ''"
            :title="item.value"
          >
            <small>{{ item.label }}</small>
            <strong>{{ item.value }}</strong>
          </span>
        </div>

        <details v-if="hasDetails(entry)" class="admin-log-line__details">
          <summary>Р”РёР°РіРЅРѕСЃС‚РёС‡РµСЃРєРёР№ РєРѕРЅС‚РµРєСЃС‚</summary>

          <div v-if="entry.requestId || entry.correlationId" class="admin-log-identifiers">
            <span v-if="entry.requestId"><small>Request ID</small><code>{{ entry.requestId }}</code></span>
            <span v-if="entry.correlationId"><small>Correlation ID</small><code>{{ entry.correlationId }}</code></span>
          </div>

          <section v-if="entry.deviation" class="admin-log-deviation">
            <strong>{{ entry.deviation.code || 'deviation' }}</strong>
            <span>Severity: {{ entry.deviation.severity || 'unknown' }}</span>
            <dl>
              <div><dt>РћР¶РёРґР°Р»РѕСЃСЊ</dt><dd><pre>{{ formatValue(entry.deviation.expected) }}</pre></dd></div>
              <div><dt>РџРѕР»СѓС‡РµРЅРѕ</dt><dd><pre>{{ formatValue(entry.deviation.actual) }}</pre></dd></div>
            </dl>
          </section>

          <section v-if="entry.exception" class="admin-log-exception">
            <strong>{{ entry.exception.class || 'Exception' }}</strong>
            <p>{{ entry.exception.message }}</p>
            <code v-if="entry.exception.file">{{ entry.exception.file }}:{{ entry.exception.line || '?' }}</code>
            <pre v-if="entry.exception.trace">{{ entry.exception.trace }}</pre>
          </section>

          <dl v-if="diagnosticContext(entry).length" class="admin-log-context">
            <div v-for="([key, value]) in diagnosticContext(entry)" :key="key">
              <dt>{{ key }}</dt>
              <dd><pre>{{ formatValue(value) }}</pre></dd>
            </div>
          </dl>

          <div v-if="entry.actorUuid" class="admin-log-actor-id">
            <small>Actor UUID</small>
            <code>{{ entry.actorUuid }}</code>
          </div>
        </details>
      </article>
    </div>
  </section>
</template>
