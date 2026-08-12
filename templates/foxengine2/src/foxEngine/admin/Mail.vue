<script setup lang="ts">
import { t } from '@/i18n'
import { computed, ref } from 'vue'
import { appBootstrap } from '@engine/app/context'
import { themeAsset } from '@engine/domain/bootstrap'
import type {
  MailAudienceFilter,
  MailAudiencePreview,
  MailCampaignDraft,
  MailCampaignStatus,
  MailSettings,
  GroupOption,
  MailTestStatus,
} from '@modules/AdminPanel/client/useAdminPanel'

const stylesheet = themeAsset(appBootstrap, 'css/admin-mail.css')
const props = defineProps<{
  settings: MailSettings
  status: MailTestStatus | null
  loading: boolean
  updatedAt: string
  storageReady: boolean
  audienceFilter: MailAudienceFilter
  audience: MailAudiencePreview | null
  audienceGroups: GroupOption[]
  audienceStatuses: string[]
  campaign: MailCampaignDraft
  campaignStatus: MailCampaignStatus | null
}>()
const emit = defineEmits<{
  save: []
  test: [recipient: string]
  previewAudience: []
  sendCampaign: []
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
const audienceReady = computed(() => Boolean(
  props.audience
  && props.audience.count > 0
  && !props.audience.tooLarge,
))
const canSendCampaign = computed(() => (
  configured.value
  && audienceReady.value
  && props.campaign.confirmed
  && props.campaign.subject.trim() !== ''
  && props.campaign.body.trim() !== ''
  && !props.loading
))
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
            <input v-model.trim="settings.smtpHost" maxlength="253" placeholder="smtp.yandex.ru" autocomplete="off">
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

      <section class="mail-card mail-card--wide mail-campaign">
        <header>
          <span class="mail-card__icon"><i class="fa-solid fa-paper-plane" aria-hidden="true" /></span>
          <div>
            <h3>{{ t('theme.foxengine.admin.mail.042') }}</h3>
            <p>{{ t('theme.foxengine.admin.mail.043') }}</p>
          </div>
        </header>

        <div class="mail-campaign__layout">
          <section class="mail-campaign__panel">
            <div class="mail-campaign__panel-title">
              <span>{{ t('theme.foxengine.admin.mail.077') }}</span>
              <small>{{ t('theme.foxengine.admin.mail.053') }}</small>
            </div>

            <label class="mail-campaign__search">
              <span>{{ t('theme.foxengine.admin.mail.047') }}</span>
              <input v-model.trim="audienceFilter.search" type="search" maxlength="160" :placeholder="t('theme.foxengine.admin.mail.048')">
            </label>

            <div class="mail-filter-group">
              <div class="mail-filter-group__title">
                <span>{{ t('theme.foxengine.admin.mail.045') }}</span>
                <small>{{ t('theme.foxengine.admin.mail.080') }}</small>
              </div>
              <div class="mail-filter-options">
                <label v-for="group in audienceGroups" :key="group.groupTag" class="mail-filter-chip">
                  <input v-model="audienceFilter.groupTags" type="checkbox" :value="group.groupTag">
                  <span class="mail-filter-chip__dot" :style="{ backgroundColor: group.groupColor }" />
                  <span>{{ group.groupName }}</span>
                </label>
              </div>
            </div>

            <div class="mail-filter-group">
              <div class="mail-filter-group__title">
                <span>{{ t('theme.foxengine.admin.mail.046') }}</span>
                <small>{{ t('theme.foxengine.admin.mail.081') }}</small>
              </div>
              <div class="mail-filter-options">
                <label v-for="statusValue in audienceStatuses" :key="`status:${statusValue}`" class="mail-filter-chip">
                  <input v-model="audienceFilter.statuses" type="checkbox" :value="statusValue">
                  <span>{{ statusValue || t('theme.foxengine.admin.mail.051') }}</span>
                </label>
              </div>
            </div>

            <button type="button" class="button button--ghost mail-campaign__preview" :disabled="loading" @click="emit('previewAudience')">
              <i class="fa-solid fa-users-viewfinder" aria-hidden="true" />
              {{ t('theme.foxengine.admin.mail.052') }}
            </button>

            <div v-if="audience" class="mail-audience" :class="{ 'mail-audience--danger': audience.tooLarge }">
              <div class="mail-audience__metrics">
                <span><strong>{{ audience.count }}</strong><small>{{ t('theme.foxengine.admin.mail.054') }}</small></span>
                <span><strong>{{ audience.sendLimit }}</strong><small>{{ t('theme.foxengine.admin.mail.055') }}</small></span>
              </div>
              <p v-if="audience.tooLarge" class="mail-audience__warning"><i class="fa-solid fa-triangle-exclamation" /> {{ t('theme.foxengine.admin.mail.056') }}</p>
              <p v-else-if="audience.count === 0" class="mail-audience__empty">{{ t('theme.foxengine.admin.mail.058') }}</p>

              <div v-if="audience.sample.length" class="mail-audience__sample">
                <strong>{{ t('theme.foxengine.admin.mail.057') }}</strong>
                <div class="mail-audience__table-wrap">
                  <table class="mail-audience__table">
                    <thead><tr><th>{{ t('theme.foxengine.admin.mail.076') }}</th><th>{{ t('theme.foxengine.admin.mail.075') }}</th><th>{{ t('theme.foxengine.admin.mail.073') }}</th><th>{{ t('theme.foxengine.admin.mail.074') }}</th></tr></thead>
                    <tbody>
                      <tr v-for="user in audience.sample" :key="user.uuid">
                        <td><strong>{{ user.realname || user.login }}</strong><small>@{{ user.login }}</small></td>
                        <td>{{ user.email }}</td>
                        <td><code>{{ user.groupTag }}</code></td>
                        <td>{{ user.userStatus || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </section>

          <section class="mail-campaign__panel">
            <div class="mail-campaign__panel-title">
              <span>{{ t('theme.foxengine.admin.mail.078') }}</span>
              <small>{{ t('theme.foxengine.admin.mail.064') }}</small>
            </div>

            <div class="mail-fields">
              <label class="mail-field--wide">
                <span>{{ t('theme.foxengine.admin.mail.059') }}</span>
                <input v-model.trim="campaign.subject" maxlength="240" :placeholder="t('theme.foxengine.admin.mail.084')">
              </label>
              <label>
                <span>{{ t('theme.foxengine.admin.mail.060') }}</span>
                <select v-model="campaign.format">
                  <option value="html">{{ t('theme.foxengine.admin.mail.061') }}</option>
                  <option value="text">{{ t('theme.foxengine.admin.mail.062') }}</option>
                </select>
              </label>
            </div>

            <label class="mail-campaign__body">
              <span>{{ t('theme.foxengine.admin.mail.063') }}</span>
              <textarea v-model="campaign.body" maxlength="100000" rows="14" :placeholder="campaign.format === 'html' ? t('theme.foxengine.admin.mail.085') : t('theme.foxengine.admin.mail.086')" />
              <small v-if="campaign.format === 'html'">{{ t('theme.foxengine.admin.mail.079') }}</small>
            </label>

            <div class="mail-campaign__send">
              <p v-if="!configured" class="mail-audience__warning"><i class="fa-solid fa-circle-exclamation" /> {{ t('theme.foxengine.admin.mail.082') }}</p>
              <p v-if="!audience" class="mail-campaign__notice">{{ t('theme.foxengine.admin.mail.072') }}</p>
              <label class="mail-campaign__confirm" :class="{ disabled: !audienceReady }">
                <input v-model="campaign.confirmed" type="checkbox" :disabled="!audienceReady">
                <span>{{ t('theme.foxengine.admin.mail.065') }}<strong v-if="audience"> — {{ audience.count }}</strong></span>
              </label>
              <button type="button" class="button button--primary" :disabled="!canSendCampaign" @click="emit('sendCampaign')">
                <i class="fa-solid" :class="loading ? 'fa-spinner fa-spin' : 'fa-paper-plane'" aria-hidden="true" />
                {{ loading ? t('theme.foxengine.admin.mail.083') : t('theme.foxengine.admin.mail.066') }}
              </button>
            </div>

            <div v-if="campaignStatus" class="mail-diagnostic__result" :class="{ success: campaignStatus.success, error: !campaignStatus.success }">
              <i class="fa-solid" :class="campaignStatus.success ? 'fa-circle-check' : 'fa-circle-exclamation'" />
              <div>
                <strong>{{ campaignStatus.success ? t('theme.foxengine.admin.mail.067') : t('theme.foxengine.admin.mail.068') }}</strong>
                <p>{{ campaignStatus.message }}</p>
                <small>{{ t('theme.foxengine.admin.mail.069') }}: {{ campaignStatus.sent }} · {{ t('theme.foxengine.admin.mail.070') }}: {{ campaignStatus.failed }} · {{ t('theme.foxengine.admin.mail.071') }}: {{ campaignStatus.total }}</small>
              </div>
            </div>
          </section>
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
