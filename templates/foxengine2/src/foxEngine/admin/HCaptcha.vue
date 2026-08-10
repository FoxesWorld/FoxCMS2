<script setup lang="ts">
import { t } from '@/i18n'
import UiCheckbox from '@/components/UiCheckbox.vue'
import type { SiteSettings } from '@modules/AdminPanel/client/useAdminPanel'

defineProps<{
  settings: SiteSettings
  loading: boolean
  updatedAt: string
  storageReady: boolean
}>()

const emit = defineEmits<{
  save: []
}>()
</script>

<template>
  <section class="admin-section site-settings-admin hcaptcha-admin">
    <header class="site-settings-admin__header">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.sitesettings.097') }}</span>
        <h2>{{ t('theme.foxengine.admin.sitesettings.082') }}</h2>
        <p>{{ t('theme.foxengine.admin.sitesettings.083') }}</p>
      </div>
      <span class="site-settings-admin__status" :class="{ ready: storageReady }">
        {{ storageReady ? t('theme.foxengine.admin.sitesettings.004') : t('theme.foxengine.admin.sitesettings.005') }}
      </span>
    </header>

    <div class="site-settings-grid">
      <section class="site-settings-card site-settings-card--wide site-settings-security">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-shield-halved" /></span>
          <div>
            <h3>hCaptcha</h3>
            <p>{{ t('theme.foxengine.admin.sitesettings.083') }}</p>
          </div>
        </header>

        <div class="site-settings-fields">
          <UiCheckbox v-model="settings.hcaptchaEnabled" :label="t('theme.foxengine.admin.sitesettings.084')" />

          <div class="site-settings-fields site-settings-fields--two">
            <label>
              <span>{{ t('theme.foxengine.admin.sitesettings.085') }}</span>
              <input
                v-model.trim="settings.hcaptchaSiteKey"
                autocomplete="off"
                maxlength="180"
                placeholder="10000000-ffff-ffff-ffff-000000000001"
              >
            </label>

            <label>
              <span>{{ t('theme.foxengine.admin.sitesettings.086') }}</span>
              <input
                v-model="settings.hcaptchaSecret"
                type="password"
                autocomplete="new-password"
                maxlength="512"
                :placeholder="settings.hcaptchaSecretConfigured ? t('theme.foxengine.admin.sitesettings.087') : t('theme.foxengine.admin.sitesettings.088')"
              >
              <small>{{ settings.hcaptchaSecretConfigured ? t('theme.foxengine.admin.sitesettings.089') : t('theme.foxengine.admin.sitesettings.090') }}</small>
            </label>
          </div>

          <div class="site-settings-captcha-scopes">
            <strong>{{ t('theme.foxengine.admin.sitesettings.091') }}</strong>
            <div class="site-settings-fields site-settings-fields--two">
              <UiCheckbox v-model="settings.hcaptchaProtectLogin" :label="t('theme.foxengine.admin.sitesettings.092')" />
              <UiCheckbox v-model="settings.hcaptchaProtectRegistration" :label="t('theme.foxengine.admin.sitesettings.093')" />
              <UiCheckbox v-model="settings.hcaptchaProtectPasswordRecovery" :label="t('theme.foxengine.admin.sitesettings.094')" />
              <UiCheckbox v-model="settings.hcaptchaProtectPasswordReset" :label="t('theme.foxengine.admin.sitesettings.095')" />
            </div>
          </div>

          <small>{{ t('theme.foxengine.admin.sitesettings.096') }}</small>
        </div>
      </section>
    </div>

    <footer class="site-settings-admin__footer">
      <span>{{ updatedAt ? t('theme.foxengine.admin.sitesettings.063', [updatedAt]) : t('theme.foxengine.admin.sitesettings.064') }}</span>
      <button type="button" class="button button--primary" :disabled="loading" @click="emit('save')">
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-floppy-disk'" />
        {{ loading ? t('theme.foxengine.admin.sitesettings.065') : t('theme.foxengine.admin.sitesettings.066') }}
      </button>
    </footer>
  </section>
</template>
