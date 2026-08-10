<script setup lang="ts">
import { t } from '@/i18n'
import { computed, ref } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import type { MailSettings, MailTestStatus } from '@modules/AdminPanel/client/useAdminPanel'

const stylesheet = themeAsset(appBootstrap, 'css/admin-mail.css')
const props = defineProps<{
  settings: MailSettings
  status: MailTestStatus | null
  loading: boolean
  updatedAt: string
  storageReady: boolean
}>()
const emit = defineEmits<{
  save: []
  test: [recipient: string]
}>()

const testRecipient = ref('')
const passwordVisible = ref(false)
const configured = computed(() => (
  props.settings.mailMethod === 'smtp'
  && props.settings.smtpHost.trim() !== ''
  && props.settings.smtpUsername.trim() !== ''
  && props.settings.passwordConfigured
))
const passwordPlaceholder = computed(() => props.settings.passwordConfigured
  ? t('theme.foxengine.admin.mail.018')
  : t('theme.foxengine.admin.mail.019'))
</script>

<template>
  <Teleport to="head"><link rel="stylesheet" :href="stylesheet"></Teleport>

  <section class="mail-admin">
    <header class="mail-admin__hero">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.mail.001') }}</span>
        <h2>{{ t('theme.foxengine.admin.mail.002') }}</h2>
        <p>{{ t('theme.foxengine.admin.mail.003') }}</p>
      </div>
      <div class="mail-admin__state" :class="{ ready: configured }">
        <i class="fa-solid" :class="configured ? 'fa-circle-check' : 'fa-circle-exclamation'" aria-hidden="true" />
        <span>
          <strong>{{ configured ? t('theme.foxengine.admin.mail.004') : t('theme.foxengine.admin.mail.005') }}</strong>
          <small>{{ storageReady ? t('theme.foxengine.admin.mail.006') : t('theme.foxengine.admin.mail.007') }}</small>
        </span>
      </div>
    </header>

    <div class="mail-admin__grid">
      <section class="mail-card">
        <header>
          <span class="mail-card__icon"><i class="fa-solid fa-envelope" aria-hidden="true" /></span>
          <div>
            <h3>{{ t('theme.foxengine.admin.mail.008') }}</h3>
            <p>{{ t('theme.foxengine.admin.mail.009') }}</p>
          </div>
        </header>

        <div class="mail-fields">
          <label>
            <span>{{ t('theme.foxengine.admin.mail.010') }}</span>
            <select v-model="settings.mailMethod">
              <option value="smtp">{{ t('theme.foxengine.admin.mail.039') }}</option>
              <option value="mail">{{ t('theme.foxengine.admin.mail.040') }}</option>
            </select>
          </label>
          <label>
            <span>{{ t('theme.foxengine.admin.mail.011') }}</span>
            <input v-model.trim="settings.mailFromName" maxlength="120" autocomplete="organization">
          </label>
          <label class="mail-field--wide">
            <span>{{ t('theme.foxengine.admin.mail.012') }}</span>
            <input v-model.trim="settings.mailFromAddress" type="email" maxlength="254" placeholder="noreply@foxescraft.ru" autocomplete="email">
          </label>
        </div>
      </section>

      <section class="mail-card">
        <header>
          <span class="mail-card__icon"><i class="fa-solid fa-server" aria-hidden="true" /></span>
          <div>
            <h3>{{ t('theme.foxengine.admin.mail.013') }}</h3>
            <p>{{ t('theme.foxengine.admin.mail.014') }}</p>
          </div>
        </header>

        <div class="mail-fields mail-fields--server">
          <label class="mail-field--wide">
            <span>{{ t('theme.foxengine.admin.mail.015') }}</span>
            <input v-model.trim="settings.smtpHost" maxlength="253" placeholder="smtp.mail.ru" autocomplete="off">
          </label>
          <label>
            <span>{{ t('theme.foxengine.admin.mail.016') }}</span>
            <input v-model.number="settings.smtpPort" type="number" min="1" max="65535" inputmode="numeric">
          </label>
          <label>
            <span>{{ t('theme.foxengine.admin.mail.017') }}</span>
            <select v-model="settings.smtpSecurity">
              <option value="ssl">SSL/TLS</option>
              <option value="tls">{{ t('theme.foxengine.admin.mail.041') }}</option>
              <option value="">{{ t('theme.foxengine.admin.mail.020') }}</option>
            </select>
          </label>
        </div>
      </section>

      <section class="mail-card mail-card--wide">
        <header>
          <span class="mail-card__icon"><i class="fa-solid fa-key" aria-hidden="true" /></span>
          <div>
            <h3>{{ t('theme.foxengine.admin.mail.021') }}</h3>
            <p>{{ t('theme.foxengine.admin.mail.022') }}</p>
          </div>
        </header>

        <div class="mail-fields mail-fields--credentials">
          <label>
            <span>{{ t('theme.foxengine.admin.mail.023') }}</span>
            <input v-model.trim="settings.smtpUsername" type="email" maxlength="254" placeholder="noreply@foxescraft.ru" autocomplete="username">
          </label>
          <label>
            <span>{{ t('theme.foxengine.admin.mail.024') }}</span>
            <span class="mail-password">
              <input
                v-model="settings.smtpPassword"
                :type="passwordVisible ? 'text' : 'password'"
                maxlength="512"
                :placeholder="passwordPlaceholder"
                autocomplete="new-password"
              >
              <button
                type="button"
                class="mail-password__toggle"
                :aria-label="passwordVisible ? t('theme.foxengine.admin.mail.025') : t('theme.foxengine.admin.mail.026')"
                @click="passwordVisible = !passwordVisible"
              >
                <i class="fa-solid" :class="passwordVisible ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true" />
              </button>
            </span>
            <small>{{ t('theme.foxengine.admin.mail.027') }}</small>
          </label>
        </div>
      </section>

      <section class="mail-card mail-card--wide mail-diagnostic">
        <header>
          <span class="mail-card__icon"><i class="fa-solid fa-stethoscope" aria-hidden="true" /></span>
          <div>
            <h3>{{ t('theme.foxengine.admin.mail.028') }}</h3>
            <p>{{ t('theme.foxengine.admin.mail.029') }}</p>
          </div>
        </header>

        <div class="mail-diagnostic__controls">
          <label>
            <span>{{ t('theme.foxengine.admin.mail.030') }}</span>
            <input v-model.trim="testRecipient" type="email" maxlength="254" placeholder="you@foxescraft.ru" autocomplete="email">
          </label>
          <div class="mail-diagnostic__buttons">
            <button type="button" class="button button--ghost" :disabled="loading" @click="emit('test', '')">
              <i class="fa-solid fa-plug-circle-check" aria-hidden="true" />
              {{ t('theme.foxengine.admin.mail.031') }}
            </button>
            <button
              type="button"
              class="button button--ghost"
              :disabled="loading || testRecipient.trim() === ''"
              @click="emit('test', testRecipient)"
            >
              <i class="fa-solid fa-paper-plane" aria-hidden="true" />
              {{ t('theme.foxengine.admin.mail.032') }}
            </button>
          </div>
        </div>

        <div v-if="status" class="mail-diagnostic__result" :class="{ success: status.success, error: !status.success }">
          <i class="fa-solid" :class="status.success ? 'fa-circle-check' : 'fa-circle-xmark'" aria-hidden="true" />
          <div>
            <strong>{{ status.success ? t('theme.foxengine.admin.mail.033') : t('theme.foxengine.admin.mail.034') }}</strong>
            <p>{{ status.message }}</p>
            <p v-if="status.hint" class="mail-diagnostic__hint">{{ status.hint }}</p>
            <code v-if="status.detail || status.smtpCode || status.smtpReply" class="mail-diagnostic__technical">{{ [status.detail, status.smtpCode, status.smtpReply].filter(Boolean).join(' / ') }}</code>
            <small>{{ [status.library, status.checkedAt].filter(Boolean).join(' / ') }}</small>
          </div>
        </div>
      </section>
    </div>

    <footer class="mail-admin__footer">
      <span>{{ updatedAt ? `${t('theme.foxengine.admin.mail.035')} ${updatedAt}` : t('theme.foxengine.admin.mail.036') }}</span>
      <button type="button" class="button button--primary" :disabled="loading" @click="emit('save')">
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" aria-hidden="true" />
        {{ loading ? t('theme.foxengine.admin.mail.037') : t('theme.foxengine.admin.mail.038') }}
      </button>
    </footer>
  </section>
</template>
