<script setup lang="ts">
import { computed } from 'vue'
import UiCheckbox from '@/components/UiCheckbox.vue'
import ImageUploadField from '@/components/ImageUploadField.vue'
import SeoTagifyInput from '../SeoTagifyInput.vue'
import { JsonFormEditor, collectJsonSamples } from '@/forms/json-form'
import { serverImageUrl } from '@/domain/serverImage'
import type { JsonValue } from '@/forms/json-form'
import type { GroupOption, JdkCatalogStatus, JdkRuntimeOption, ServerDraft, ServerRow } from '@modules/AdminPanel/client/useAdminPanel'

const props = defineProps<{
  selected: ServerRow | null
  draft: ServerDraft
  groups: GroupOption[]
  samples: ServerRow[]
  jdkOptions: JdkRuntimeOption[]
  jdkCatalog: JdkCatalogStatus
  loading: boolean
  imageUploading: boolean
  imageError: string
}>()

const emit = defineEmits<{
  uploadImage: [file: File]
  clearImage: []
  remove: [server: ServerRow]
  save: []
}>()

type StructuredServerField = 'modsInfo'

function samplesFor(field: StructuredServerField): JsonValue[] {
  return collectJsonSamples(props.samples, field)
}

function normalizeIgnoreDirectories(value: unknown): string[] {
  let source: unknown[] = []
  if (Array.isArray(value)) {
    source = value
  } else if (typeof value === 'string') {
    const trimmed = value.trim()
    if (trimmed !== '') {
      try {
        const decoded = JSON.parse(trimmed)
        source = Array.isArray(decoded) ? decoded : trimmed.split(/[\r\n,;]+/u)
      } catch {
        source = trimmed.split(/[\r\n,;]+/u)
      }
    }
  }

  const unique = new Map<string, string>()
  for (const raw of source) {
    if (typeof raw !== 'string' && typeof raw !== 'number') continue
    const directory = String(raw).trim().replace(/\\/gu, '/').replace(/\/{2,}/gu, '/')
    if (!directory) continue
    unique.set(directory.toLocaleLowerCase('ru'), directory)
  }
  return [...unique.values()]
}

const ignoreDirectories = computed({
  get: () => normalizeIgnoreDirectories(props.draft.ignoreDirs).join(', '),
  set: (value: string) => {
    props.draft.ignoreDirs = normalizeIgnoreDirectories(value)
  },
})

const selectedJdk = computed(() => props.jdkOptions.find((option) => option.value === props.draft.jreVersion) ?? null)
const runtimeValue = computed(() => props.draft.jreVersion.trim())
const runtimeConfigured = computed(() => /^(?:1\.)?[0-9]+(?:\.[0-9]+)*$/.test(runtimeValue.value))
const runtimeSaveBlocked = computed(() => props.draft.enabled && !runtimeConfigured.value)
const legacyJdkValue = computed(() => runtimeValue.value !== '' && !selectedJdk.value ? runtimeValue.value : '')
const serverInitial = computed(() => Array.from(props.draft.serverName.trim())[0]?.toLocaleUpperCase('ru') ?? 'S')
const serverEndpoint = computed(() => {
  const host = props.draft.host.trim() || 'host не указан'
  return props.draft.port ? `${host}:${props.draft.port}` : host
})
const serverAccent = computed(() => props.draft.enabled ? 'var(--color-success)' : 'var(--color-warning)')

function serverStyle(): Record<string, string> {
  return { '--admin-user-group': serverAccent.value }
}
</script>

<template>
  <form class="admin-editor admin-user-editor admin-server-editor" @submit.prevent="emit('save')">
    <header class="admin-user-editor__hero admin-server-editor__hero" :style="serverStyle()">
      <span class="admin-user-avatar admin-user-avatar--large">{{ serverInitial }}</span>
      <div class="admin-user-editor__hero-copy">
        <span class="eyebrow">{{ selected ? 'Редактирование сервера' : 'Новая конфигурация' }}</span>
        <h2>{{ draft.serverName || 'Новый сервер' }}</h2>
        <p>{{ serverEndpoint }}</p>
        <div class="admin-user-editor__chips">
          <span class="admin-user-editor__group-chip">
            <i class="fa-solid fa-circle" aria-hidden="true" />
            {{ draft.enabled ? 'Сервер включён' : 'Сервер отключён' }}
          </span>
          <span><i class="fa-solid fa-users" aria-hidden="true" />{{ draft.serverGroups.length }} групп</span>
        </div>
      </div>
      <div class="admin-user-editor__uuid">
        <span>{{ selected ? 'Server ID' : 'Режим' }}</span>
        <code>{{ selected?.id ?? 'Создание' }}</code>
      </div>
    </header>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-server" aria-hidden="true" /></span>
        <div>
          <h3>Подключение</h3>
        </div>
      </header>
      <div class="admin-user-editor__fields">
        <label>
          <span>Имя сервера</span>
          <input v-model.trim="draft.serverName" type="text" maxlength="64" required>
        </label>
        <label>
          <span>Host</span>
          <input v-model.trim="draft.host" type="text" maxlength="255" placeholder="play.example.org">
        </label>
        <label>
          <span>Port</span>
          <input v-model.number="draft.port" type="number" min="1" max="65535">
        </label>
        <label>
          <span>Версия сервера</span>
          <input v-model.trim="draft.serverVersion" type="text" maxlength="64" placeholder="1.21.2">
        </label>
      </div>
    </section>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true" /></span>
        <div>
          <h3>Состояние и запуск</h3>
        </div>
      </header>

      <div class="admin-checks">
        <UiCheckbox
          v-model="draft.enabled"
          variant="switch"
          label="Сервер включён"
          description="Разрешить отображение и подключение"
        />
        <UiCheckbox
          v-model="draft.checkLib"
          variant="switch"
          label="Проверять библиотеки"
          description="Проверять клиентские зависимости перед запуском"
        />
      </div>

      <label class="server-runtime-field">
        <span>Java runtime</span>
        <select
          v-model="draft.jreVersion"
          :disabled="loading || !jdkCatalog.available || jdkOptions.length === 0"
          :required="draft.enabled"
        >
          <option value="" disabled>Выберите JDK</option>
          <option v-if="legacyJdkValue" :value="legacyJdkValue" disabled>JDK {{ legacyJdkValue }} — вне каталога</option>
          <option v-for="runtime in jdkOptions" :key="runtime.value" :value="runtime.value">{{ runtime.label }}</option>
        </select>

        <section
          class="server-runtime-status"
          :class="{
            'is-error': !jdkCatalog.available,
            'is-empty': jdkCatalog.available && jdkOptions.length === 0,
            'is-ready': selectedJdk,
            'is-warning': legacyJdkValue || !jdkCatalog.available || jdkOptions.length === 0,
          }"
        >
          <i
            class="fa-solid"
            :class="!jdkCatalog.available || jdkOptions.length === 0 ? 'fa-circle-exclamation' : 'fa-circle-check'"
            aria-hidden="true"
          />
          <div v-if="!jdkCatalog.available">
            <strong>Каталог runtime недоступен</strong>
            <small>{{ jdkCatalog.error || `Не удалось прочитать ${jdkCatalog.root}` }}</small>
            <small>Сохранение конфигурации доступно.</small>
          </div>
          <div v-else-if="jdkOptions.length === 0">
            <strong>Общие семейства JDK не найдены</strong>
            <small>Сервер можно сохранить; запуск потребует архивов для Windows, Linux и macOS.</small>
          </div>
          <div v-else-if="selectedJdk">
            <strong>{{ selectedJdk.label }}</strong>
            <small>
              Windows {{ selectedJdk.selectedVersions.windows }} · Linux {{ selectedJdk.selectedVersions.linux }} · macOS {{ selectedJdk.selectedVersions.macos }}
            </small>
            <small>Сохраняется major-версия {{ selectedJdk.value }}.</small>
          </div>
          <div v-else-if="legacyJdkValue">
            <strong>Сохранён JDK {{ legacyJdkValue }}</strong>
            <small>Значение можно сохранить, но каталог пока не подтверждает его для всех систем.</small>
          </div>
          <div v-else>
            <strong>Java runtime не выбран</strong>
            <small v-if="draft.enabled">Для включённого сервера укажите major-версию JDK.</small>
            <small v-else>Отключённый сервер можно сохранить как черновик.</small>
          </div>
        </section>
      </label>
    </section>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-image" aria-hidden="true" /></span>
        <div>
          <h3>Представление сервера</h3>
        </div>
      </header>

      <div class="server-image-field">
        <ImageUploadField
          title="Изображение сервера"
          description="Обложка 16:9 для страницы сервера"
          :preview="serverImageUrl(draft.serverImage)"
          :preview-alt="draft.serverName ? `Preview сервера ${draft.serverName}` : 'Preview изображения сервера'"
          preview-mode="wide"
          accept="image/jpeg,image/png,image/webp"
          :allowed-types="['image/jpeg', 'image/png', 'image/webp']"
          :maximum-bytes="12_582_912"
          :minimum-width="320"
          :minimum-height="180"
          :maximum-width="8192"
          :maximum-height="8192"
          :disabled="loading"
          :uploading="imageUploading"
          :error="imageError"
          hint="JPEG, PNG или WebP · минимум 320×180 · до 12 МиБ"
          choose-label="Выбрать изображение"
          replace-label="Заменить изображение"
          clear-label="Удалить изображение"
          @select="emit('uploadImage', $event)"
          @clear="emit('clearImage')"
        />

        <label class="server-image-field__path">
          <span>Путь или URL</span>
          <input
            v-model.trim="draft.serverImage"
            type="text"
            maxlength="1024"
            placeholder="/uploads/servers/server-...webp"
            :disabled="imageUploading"
          >
        </label>
      </div>

      <label>
        <span>Описание сервера</span>
        <textarea v-model="draft.serverDescription" rows="5" maxlength="4000" />
      </label>
    </section>

    <section class="admin-user-editor__section admin-user-editor__section--structured">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-code" aria-hidden="true" /></span>
        <div>
          <h3>Доступ и клиентские данные</h3>
        </div>
      </header>

      <div class="admin-user-editor__structured-grid">
        <article class="admin-user-data-card">
          <header><div><strong>Группы доступа</strong><small>{{ draft.serverGroups.length }} выбрано</small></div></header>
          <label class="admin-user-editor__group-select">
            <span>Разрешённые группы</span>
            <select v-model="draft.serverGroups" multiple :size="Math.min(Math.max(groups.length, 3), 8)">
              <option v-for="group in groups" :key="group.groupTag" :value="group.groupTag">
                {{ group.groupName }} — {{ group.groupTag }}
              </option>
            </select>
            <small>Можно выбрать несколько групп.</small>
          </label>
        </article>

        <article class="admin-user-data-card">
          <header><div><strong>Игнорируемые каталоги</strong><small>Исключения синхронизации</small></div></header>
          <SeoTagifyInput
            v-model="ignoreDirectories"
            placeholder="Добавьте каталог и нажмите Enter"
          />
          <small class="admin-tagify-field__hint">
            Добавляйте каталоги по одному. Enter, запятая и точка с запятой завершают тег.
          </small>
        </article>

        <article class="admin-user-data-card admin-user-data-card--wide">
          <header><div><strong>Информация о модах</strong><small>Данные, показываемые клиенту и лаунчеру</small></div></header>
          <JsonFormEditor
            :model-value="draft.modsInfo"
            :samples="samplesFor('modsInfo')"
            label="Информация о модах"
            root-kind="array"
            @update:model-value="draft.modsInfo = $event"
          />
        </article>
      </div>
    </section>

    <footer class="admin-user-editor__footer admin-server-editor__footer">
      <div>
        <i class="fa-solid fa-circle-info" aria-hidden="true" />
        <span>{{ selected ? 'Изменения применяются только после сохранения.' : 'Новая конфигурация будет создана после сохранения.' }}</span>
      </div>
      <button
        v-if="selected"
        class="button button--ghost"
        type="button"
        :disabled="loading || imageUploading"
        @click="emit('remove', selected)"
      >
        <i class="fa-solid fa-xmark" aria-hidden="true" />
        <span>Удалить</span>
      </button>
      <button
        class="button button--primary"
        type="submit"
        :disabled="loading || imageUploading || runtimeSaveBlocked"
        :title="runtimeSaveBlocked ? 'Для включённого сервера укажите Java runtime.' : 'Сохранить конфигурацию сервера'"
      >
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" aria-hidden="true" />
        <span>{{ loading ? 'Сохранение…' : runtimeSaveBlocked ? 'Укажите Java runtime' : 'Сохранить сервер' }}</span>
      </button>
    </footer>
  </form>
</template>
