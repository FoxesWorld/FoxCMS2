<script setup lang="ts">
import { t } from '@/i18n'

import { computed } from 'vue'
import ImageUploadField from '@/components/ImageUploadField.vue'
import UiCheckbox from '@/components/UiCheckbox.vue'
import type { SiteSettings } from '@modules/AdminPanel/client/useAdminPanel'
import SeoTagifyInput from './SeoTagifyInput.vue'

const props = defineProps<{
  settings: SiteSettings
  loading: boolean
  updatedAt: string
  storageReady: boolean
  imageUploading: boolean
  imageError: string
}>()
const emit = defineEmits<{
  uploadImage: [file: File]
  clearImage: []
  save: []
}>()

const titlePreview = computed(() => props.settings.titleTemplate
  .replaceAll('%page%', t('theme.foxengine.admin.sitesettings.067'))
  .replaceAll('%site%', props.settings.siteTitle || 'FoxesCraft'))
const keywordCount = computed(() => props.settings.keywords
  .split(/[,;\n]+/)
  .map((item) => item.trim())
  .filter(Boolean).length)
const canonicalPreview = computed(() => props.settings.canonicalUrl || t('theme.foxengine.admin.sitesettings.068'))
</script>

<template>
  <section class="admin-section site-settings-admin">
    <header class="site-settings-admin__header">
      <div>
        <span class="eyebrow">{{ t('theme.foxengine.admin.sitesettings.001') }}</span>
        <h2>{{ t('theme.foxengine.admin.sitesettings.002') }}</h2>
        <p>{{ t('theme.foxengine.admin.sitesettings.003') }}</p>
      </div>
      <span class="site-settings-admin__status" :class="{ ready: storageReady }">
        {{ storageReady ? t('theme.foxengine.admin.sitesettings.004') : t('theme.foxengine.admin.sitesettings.005') }}
      </span>
    </header>

    <div class="site-settings-grid">
      <section class="site-settings-card site-settings-card--wide site-settings-security">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-shield-halved" /></span>
          <div><h3>{{ t('theme.foxengine.admin.sitesettings.082') }}</h3><p>{{ t('theme.foxengine.admin.sitesettings.083') }}</p></div>
        </header>
        <div class="site-settings-fields">
          <UiCheckbox v-model="settings.hcaptchaEnabled" :label="t('theme.foxengine.admin.sitesettings.084')" />
          <div class="site-settings-fields site-settings-fields--two">
            <label>
              <span>{{ t('theme.foxengine.admin.sitesettings.085') }}</span>
              <input v-model.trim="settings.hcaptchaSiteKey" autocomplete="off" maxlength="180" placeholder="10000000-ffff-ffff-ffff-000000000001">
            </label>
            <label>
              <span>{{ t('theme.foxengine.admin.sitesettings.086') }}</span>
              <input v-model="settings.hcaptchaSecret" type="password" autocomplete="new-password" maxlength="512" :placeholder="settings.hcaptchaSecretConfigured ? t('theme.foxengine.admin.sitesettings.087') : t('theme.foxengine.admin.sitesettings.088')">
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

      <section class="site-settings-card site-settings-card--wide">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-pen-to-square" /></span>
          <div><h3>{{ t('theme.foxengine.admin.sitesettings.006') }}</h3><p>{{ t('theme.foxengine.admin.sitesettings.007') }}</p></div>
        </header>
        <div class="site-settings-fields site-settings-fields--two">
          <label><span>{{ t('theme.foxengine.admin.sitesettings.008') }}</span><input v-model="settings.siteTitle" maxlength="120" :placeholder="t('theme.foxengine.admin.sitesettings.009')"><small>{{ settings.siteTitle.length }}/120</small></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.010') }}</span><input v-model="settings.siteStatus" maxlength="120" :placeholder="t('theme.foxengine.admin.sitesettings.011')"><small>{{ settings.siteStatus.length }}/120</small></label>
          <label class="site-settings-field--full"><span>{{ t('theme.foxengine.admin.sitesettings.012') }}</span><textarea v-model="settings.siteDesc" rows="4" maxlength="320" :placeholder="t('theme.foxengine.admin.sitesettings.013')"></textarea><small>{{ settings.siteDesc.length }}/320</small></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.014') }}</span><input v-model="settings.author" maxlength="120" :placeholder="t('theme.foxengine.admin.sitesettings.009')"></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.015') }}</span><div class="site-settings-color"><input v-model="settings.themeColor" maxlength="7" placeholder="#152019"><input v-model="settings.themeColor" type="color" :aria-label="t('theme.foxengine.admin.sitesettings.016')"></div></label>
        </div>
      </section>

      <section class="site-settings-card site-settings-card--wide site-settings-social-links">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-share-nodes" /></span>
          <div><h3>{{ t('theme.foxengine.admin.sitesettings.075') }}</h3><p>{{ t('theme.foxengine.admin.sitesettings.076') }}</p></div>
        </header>
        <div class="site-settings-fields site-settings-fields--two">
          <label>
            <span><i class="fa-brands fa-telegram" aria-hidden="true" /> {{ t('theme.foxengine.admin.sitesettings.077') }}</span>
            <input v-model.trim="settings.telegramLink" type="url" maxlength="2048" placeholder="https://t.me/foxescraft">
          </label>
          <label>
            <span><i class="fa-brands fa-github" aria-hidden="true" /> {{ t('theme.foxengine.admin.sitesettings.078') }}</span>
            <input v-model.trim="settings.githubLink" type="url" maxlength="2048" placeholder="https://github.com/FoxesCraft">
          </label>
          <label>
            <span><i class="fa-brands fa-youtube" aria-hidden="true" /> {{ t('theme.foxengine.admin.sitesettings.079') }}</span>
            <input v-model.trim="settings.youtubeLink" type="url" maxlength="2048" placeholder="https://youtube.com/@FoxesCraft">
          </label>
          <label>
            <span><i class="fa-brands fa-discord" aria-hidden="true" /> {{ t('theme.foxengine.admin.sitesettings.080') }}</span>
            <input v-model.trim="settings.discordLink" type="url" maxlength="2048" placeholder="https://discord.gg/foxescraft">
          </label>
          <small class="site-settings-field--full">{{ t('theme.foxengine.admin.sitesettings.081') }}</small>
        </div>
      </section>

      <section class="site-settings-card">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-newspaper" /></span>
          <div><h3>{{ t('theme.foxengine.admin.sitesettings.017') }}</h3><p>{{ t('theme.foxengine.admin.sitesettings.018') }}</p></div>
        </header>
        <div class="site-settings-fields">
          <label><span>{{ t('theme.foxengine.admin.sitesettings.019') }}</span><input v-model="settings.homeTitle" maxlength="180" :placeholder="t('theme.foxengine.admin.sitesettings.020')"></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.021') }}</span><input v-model="settings.titleTemplate" maxlength="180" :placeholder="t('theme.foxengine.admin.sitesettings.069')"><small>{{ t('theme.foxengine.admin.sitesettings.022') }} <code>%page%</code> {{ t('theme.foxengine.admin.sitesettings.023') }} <code>%site%</code></small></label>
          <div class="site-settings-preview"><span>{{ t('theme.foxengine.admin.sitesettings.024') }}</span><strong>{{ titlePreview }}</strong></div>
        </div>
      </section>

      <section class="site-settings-card">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-magnifying-glass" /></span>
          <div><h3>{{ t('theme.foxengine.admin.sitesettings.025') }}</h3><p>{{ t('theme.foxengine.admin.sitesettings.026') }}</p></div>
        </header>
        <div class="site-settings-fields">
          <label><span>{{ t('theme.foxengine.admin.sitesettings.027') }}</span><input v-model="settings.canonicalUrl" type="url" maxlength="2048" placeholder="https://foxescraft.ru"><small>{{ canonicalPreview }}</small></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.028') }}</span><select v-model="settings.robots"><option value="index,follow">{{ t('theme.foxengine.admin.sitesettings.070') }}</option><option value="index,nofollow">{{ t('theme.foxengine.admin.sitesettings.071') }}</option><option value="noindex,follow">{{ t('theme.foxengine.admin.sitesettings.072') }}</option><option value="noindex,nofollow">{{ t('theme.foxengine.admin.sitesettings.073') }}</option></select></label>
          <label class="site-settings-field--full"><span>{{ t('theme.foxengine.admin.sitesettings.029') }}</span><SeoTagifyInput v-model="settings.keywords" :placeholder="t('theme.foxengine.admin.sitesettings.030')" /><small>{{ keywordCount }} {{ t('theme.foxengine.admin.sitesettings.031') }}</small></label>
          <div class="site-settings-inline">
            <label><span>{{ t('theme.foxengine.admin.sitesettings.032') }}</span><input v-model="settings.lang" maxlength="8" placeholder="ru"></label>
            <label><span>{{ t('theme.foxengine.admin.sitesettings.033') }}</span><input v-model="settings.locale" maxlength="8" placeholder="ru_RU"></label>
          </div>
        </div>
      </section>

      <section class="site-settings-card site-settings-card--wide">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-image" /></span>
          <div><h3>{{ t('theme.foxengine.admin.sitesettings.034') }}</h3><p>{{ t('theme.foxengine.admin.sitesettings.035') }}</p></div>
        </header>
        <div class="site-settings-fields site-settings-fields--two">
          <label><span>{{ t('theme.foxengine.admin.sitesettings.036') }}</span><input v-model="settings.ogSiteName" maxlength="120" :placeholder="t('theme.foxengine.admin.sitesettings.009')"></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.037') }}</span><input v-model="settings.ogTitle" maxlength="180" :placeholder="t('theme.foxengine.admin.sitesettings.009')"></label>
          <label class="site-settings-field--full"><span>{{ t('theme.foxengine.admin.sitesettings.038') }}</span><textarea v-model="settings.ogDescription" rows="3" maxlength="320" :placeholder="t('theme.foxengine.admin.sitesettings.039')"></textarea></label>
          <div class="site-settings-field--full site-settings-social-image">
            <label>
              <span>{{ t('theme.foxengine.admin.sitesettings.040') }}</span>
              <input v-model.trim="settings.ogImage" maxlength="2048" placeholder="/uploads/site/social-card.webp">
              <small>{{ t('theme.foxengine.admin.sitesettings.041') }} <code>content</code> {{ t('theme.foxengine.admin.sitesettings.042') }} <code>{{ t('theme.foxengine.admin.sitesettings.043') }}</code>{{ t('theme.foxengine.admin.sitesettings.044') }}</small>
            </label>
            <ImageUploadField
              :title="t('theme.foxengine.admin.sitesettings.045')"
              :description="t('theme.foxengine.admin.sitesettings.046')"
              :preview="settings.ogImage"
              :preview-alt="t('theme.foxengine.admin.sitesettings.047')"
              preview-mode="wide"
              preview-fit="cover"
              :editor-aspect-ratio="1200 / 630"
              accept="image/jpeg,image/png,image/webp"
              :allowed-types="['image/jpeg', 'image/png', 'image/webp']"
              :maximum-bytes="12_582_912"
              :minimum-width="600"
              :minimum-height="315"
              :maximum-width="8192"
              :maximum-height="8192"
              :disabled="loading"
              :uploading="imageUploading"
              :error="imageError"
              :hint="t('theme.foxengine.admin.sitesettings.048')"
              :choose-label="t('theme.foxengine.admin.sitesettings.049')"
              :replace-label="t('theme.foxengine.admin.sitesettings.050')"
              :clear-label="t('theme.foxengine.admin.sitesettings.051')"
              @select="emit('uploadImage', $event)"
              @clear="emit('clearImage')"
            />
          </div>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.052') }}</span><select v-model="settings.twitterCard"><option value="summary_large_image">{{ t('theme.foxengine.admin.sitesettings.053') }}</option><option value="summary">{{ t('theme.foxengine.admin.sitesettings.054') }}</option></select></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.055') }}</span><input v-model="settings.faviconUrl" maxlength="2048" placeholder="/favicon.ico"></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.056') }}</span><input v-model="settings.twitterSite" maxlength="31" placeholder="@foxescraft"></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.057') }}</span><input v-model="settings.twitterCreator" maxlength="31" placeholder="@author"></label>
        </div>
      </section>

      <section class="site-settings-card site-settings-card--wide">
        <header>
          <span class="site-settings-card__icon"><i class="fa-solid fa-circle-check" /></span>
          <div><h3>{{ t('theme.foxengine.admin.sitesettings.058') }}</h3><p>{{ t('theme.foxengine.admin.sitesettings.059') }}</p></div>
        </header>
        <div class="site-settings-fields site-settings-fields--three">
          <label><span>{{ t('theme.foxengine.admin.sitesettings.060') }}</span><input v-model="settings.googleVerification" maxlength="180" autocomplete="off" :placeholder="t('theme.foxengine.admin.sitesettings.074')"></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.061') }}</span><input v-model="settings.yandexVerification" maxlength="180" autocomplete="off" :placeholder="t('theme.foxengine.admin.sitesettings.074')"></label>
          <label><span>{{ t('theme.foxengine.admin.sitesettings.062') }}</span><input v-model="settings.bingVerification" maxlength="180" autocomplete="off" :placeholder="t('theme.foxengine.admin.sitesettings.074')"></label>
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
