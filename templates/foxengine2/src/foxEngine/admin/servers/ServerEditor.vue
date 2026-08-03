<script setup lang="ts">
import { t } from '@/i18n'

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
  const host = props.draft.host.trim() || t('theme.foxengine.admin.servers.servereditor.067')
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
        <span class="eyebrow">{{ selected ? t('theme.foxengine.admin.servers.servereditor.001') : t('theme.foxengine.admin.servers.servereditor.002') }}</span>
        <h2>{{ draft.serverName || t('theme.foxengine.admin.servers.servereditor.003') }}</h2>
        <p>{{ serverEndpoint }}</p>
        <div class="admin-user-editor__chips">
          <span class="admin-user-editor__group-chip">
            <i class="fa-solid fa-circle" aria-hidden="true" />
            {{ draft.enabled ? t('theme.foxengine.admin.servers.servereditor.004') : t('theme.foxengine.admin.servers.servereditor.005') }}
          </span>
          <span><i class="fa-solid fa-users" aria-hidden="true" />{{ draft.serverGroups.length }} {{ t('theme.foxengine.admin.servers.servereditor.006') }}</span>
        </div>
      </div>
      <div class="admin-user-editor__uuid">
        <span>{{ selected ? t('theme.foxengine.admin.servers.servereditor.007') : t('theme.foxengine.admin.servers.servereditor.008') }}</span>
        <code>{{ selected?.id ?? t('theme.foxengine.admin.servers.servereditor.009') }}</code>
      </div>
    </header>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-server" aria-hidden="true" /></span>
        <div>
          <h3>{{ t('theme.foxengine.admin.servers.servereditor.010') }}</h3>
        </div>
      </header>
      <div class="admin-user-editor__fields">
        <label>
          <span>{{ t('theme.foxengine.admin.servers.servereditor.011') }}</span>
          <input v-model.trim="draft.serverName" type="text" maxlength="64" required>
        </label>
        <label>
          <span>{{ t('theme.foxengine.admin.servers.servereditor.012') }}</span>
          <input v-model.trim="draft.host" type="text" maxlength="255" placeholder="play.example.org">
        </label>
        <label>
          <span>{{ t('theme.foxengine.admin.servers.servereditor.013') }}</span>
          <input v-model.number="draft.port" type="number" min="1" max="65535">
        </label>
        <label>
          <span>{{ t('theme.foxengine.admin.servers.servereditor.014') }}</span>
          <input v-model.trim="draft.serverVersion" type="text" maxlength="64" placeholder="1.21.2">
        </label>
      </div>
    </section>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true" /></span>
        <div>
          <h3>{{ t('theme.foxengine.admin.servers.servereditor.015') }}</h3>
        </div>
      </header>

      <div class="admin-checks">
        <UiCheckbox
          v-model="draft.enabled"
          variant="switch"
          :label="t('theme.foxengine.admin.servers.servereditor.004')"
          :description="t('theme.foxengine.admin.servers.servereditor.016')"
        />
        <UiCheckbox
          v-model="draft.checkLib"
          variant="switch"
          :label="t('theme.foxengine.admin.servers.servereditor.017')"
          :description="t('theme.foxengine.admin.servers.servereditor.018')"
        />
      </div>

      <label class="server-runtime-field">
        <span>{{ t('theme.foxengine.admin.servers.servereditor.019') }}</span>
        <select
          v-model="draft.jreVersion"
          :disabled="loading || !jdkCatalog.available || jdkOptions.length === 0"
          :required="draft.enabled"
        >
          <option value="" disabled>{{ t('theme.foxengine.admin.servers.servereditor.020') }}</option>
          <option v-if="legacyJdkValue" :value="legacyJdkValue" disabled>{{ t('theme.foxengine.admin.servers.servereditor.021') }} {{ legacyJdkValue }} {{ t('theme.foxengine.admin.servers.servereditor.022') }}</option>
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
            <strong>{{ t('theme.foxengine.admin.servers.servereditor.023') }}</strong>
            <small>{{ jdkCatalog.error || t('theme.foxengine.admin.servers.servereditor.024', [jdkCatalog.root]) }}</small>
            <small>{{ t('theme.foxengine.admin.servers.servereditor.025') }}</small>
          </div>
          <div v-else-if="jdkOptions.length === 0">
            <strong>{{ t('theme.foxengine.admin.servers.servereditor.026') }}</strong>
            <small>{{ t('theme.foxengine.admin.servers.servereditor.027') }}</small>
          </div>
          <div v-else-if="selectedJdk">
            <strong>{{ selectedJdk.label }}</strong>
            <small> {{ t('theme.foxengine.admin.servers.servereditor.028') }} {{ selectedJdk.selectedVersions.windows }} {{ t('theme.foxengine.admin.servers.servereditor.029') }} {{ selectedJdk.selectedVersions.linux }} {{ t('theme.foxengine.admin.servers.servereditor.030') }} {{ selectedJdk.selectedVersions.macos }}
            </small>
            <small>{{ t('theme.foxengine.admin.servers.servereditor.031') }} {{ selectedJdk.value }}.</small>
          </div>
          <div v-else-if="legacyJdkValue">
            <strong>{{ t('theme.foxengine.admin.servers.servereditor.032') }} {{ legacyJdkValue }}</strong>
            <small>{{ t('theme.foxengine.admin.servers.servereditor.033') }}</small>
          </div>
          <div v-else>
            <strong>{{ t('theme.foxengine.admin.servers.servereditor.034') }}</strong>
            <small v-if="draft.enabled">{{ t('theme.foxengine.admin.servers.servereditor.035') }}</small>
            <small v-else>{{ t('theme.foxengine.admin.servers.servereditor.036') }}</small>
          </div>
        </section>
      </label>
    </section>

    <section class="admin-user-editor__section">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-image" aria-hidden="true" /></span>
        <div>
          <h3>{{ t('theme.foxengine.admin.servers.servereditor.037') }}</h3>
        </div>
      </header>

      <div class="server-image-field">
        <ImageUploadField
          :title="t('theme.foxengine.admin.servers.servereditor.038')"
          :description="t('theme.foxengine.admin.servers.servereditor.039')"
          :preview="serverImageUrl(draft.serverImage)"
          :preview-alt="draft.serverName ? t('theme.foxengine.admin.servers.servereditor.040', [draft.serverName]) : t('theme.foxengine.admin.servers.servereditor.041')"
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
          :hint="t('theme.foxengine.admin.servers.servereditor.042')"
          :choose-label="t('theme.foxengine.admin.servers.servereditor.043')"
          :replace-label="t('theme.foxengine.admin.servers.servereditor.044')"
          :clear-label="t('theme.foxengine.admin.servers.servereditor.045')"
          @select="emit('uploadImage', $event)"
          @clear="emit('clearImage')"
        />

        <label class="server-image-field__path">
          <span>{{ t('theme.foxengine.admin.servers.servereditor.046') }}</span>
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
        <span>{{ t('theme.foxengine.admin.servers.servereditor.047') }}</span>
        <textarea v-model="draft.serverDescription" rows="5" maxlength="4000" />
      </label>
    </section>

    <section class="admin-user-editor__section admin-user-editor__section--structured">
      <header class="admin-user-editor__section-header">
        <span><i class="fa-solid fa-code" aria-hidden="true" /></span>
        <div>
          <h3>{{ t('theme.foxengine.admin.servers.servereditor.048') }}</h3>
        </div>
      </header>

      <div class="admin-user-editor__structured-grid">
        <article class="admin-user-data-card">
          <header><div><strong>{{ t('theme.foxengine.admin.servers.servereditor.049') }}</strong><small>{{ draft.serverGroups.length }} {{ t('theme.foxengine.admin.servers.servereditor.050') }}</small></div></header>
          <label class="admin-user-editor__group-select">
            <span>{{ t('theme.foxengine.admin.servers.servereditor.051') }}</span>
            <select v-model="draft.serverGroups" multiple :size="Math.min(Math.max(groups.length, 3), 8)">
              <option v-for="group in groups" :key="group.groupTag" :value="group.groupTag">
                {{ group.groupName }} — {{ group.groupTag }}
              </option>
            </select>
            <small>{{ t('theme.foxengine.admin.servers.servereditor.052') }}</small>
          </label>
        </article>

        <article class="admin-user-data-card">
          <header><div><strong>{{ t('theme.foxengine.admin.servers.servereditor.053') }}</strong><small>{{ t('theme.foxengine.admin.servers.servereditor.054') }}</small></div></header>
          <SeoTagifyInput
            v-model="ignoreDirectories"
            :placeholder="t('theme.foxengine.admin.servers.servereditor.055')"
          />
          <small class="admin-tagify-field__hint"> {{ t('theme.foxengine.admin.servers.servereditor.056') }} </small>
        </article>

        <article class="admin-user-data-card admin-user-data-card--wide">
          <header><div><strong>{{ t('theme.foxengine.admin.servers.servereditor.057') }}</strong><small>{{ t('theme.foxengine.admin.servers.servereditor.058') }}</small></div></header>
          <JsonFormEditor
            :model-value="draft.modsInfo"
            :samples="samplesFor('modsInfo')"
            :label="t('theme.foxengine.admin.servers.servereditor.057')"
            root-kind="array"
            @update:model-value="draft.modsInfo = $event"
          />
        </article>
      </div>
    </section>

    <footer class="admin-user-editor__footer admin-server-editor__footer">
      <div>
        <i class="fa-solid fa-circle-info" aria-hidden="true" />
        <span>{{ selected ? t('theme.foxengine.admin.servers.servereditor.059') : t('theme.foxengine.admin.servers.servereditor.060') }}</span>
      </div>
      <button
        v-if="selected"
        class="button button--ghost"
        type="button"
        :disabled="loading || imageUploading"
        @click="emit('remove', selected)"
      >
        <i class="fa-solid fa-xmark" aria-hidden="true" />
        <span>{{ t('theme.foxengine.admin.servers.servereditor.061') }}</span>
      </button>
      <button
        class="button button--primary"
        type="submit"
        :disabled="loading || imageUploading || runtimeSaveBlocked"
        :title="runtimeSaveBlocked ? t('theme.foxengine.admin.servers.servereditor.062') : t('theme.foxengine.admin.servers.servereditor.063')"
      >
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" aria-hidden="true" />
        <span>{{ loading ? t('theme.foxengine.admin.servers.servereditor.064') : runtimeSaveBlocked ? t('theme.foxengine.admin.servers.servereditor.065') : t('theme.foxengine.admin.servers.servereditor.066') }}</span>
      </button>
    </footer>
  </form>
</template>
