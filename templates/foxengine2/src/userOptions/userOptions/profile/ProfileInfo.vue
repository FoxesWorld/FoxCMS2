<script setup lang="ts">
import { t } from '@/i18n'

import type { FeedbackMessage, ProfileRecord } from '@engine/contracts/user-pages'

type PrivateProfileRecord = ProfileRecord & {
  email?: string
  emailVerified?: boolean
}

const props = defineProps<{
  profile: PrivateProfileRecord
  isOwner: boolean
  emailVerificationBusy: boolean
  emailVerificationFeedback: FeedbackMessage | null
}>()

const emit = defineEmits<{
  requestEmailVerification: []
}>()
</script>

<template>
  <section class="profile-panel profile-info-panel">
    <header class="profile-panel__heading">
      <div>
        <span class="profile-panel__eyebrow">{{ t('theme.useroptions.useroptions.profile.profileinfo.001') }}</span>
        <h2>{{ t('theme.useroptions.useroptions.profile.profileinfo.002') }}</h2>
      </div>
    </header>
    <dl class="profile-info-list">
      <div>
        <dt>{{ t('theme.useroptions.useroptions.profile.profileinfo.003') }}</dt>
        <dd>{{ profile.realname || profile.login }}</dd>
      </div>
      <div>
        <dt>{{ t('theme.useroptions.useroptions.profile.profileinfo.004') }}</dt>
        <dd>{{ profile.land || t('theme.useroptions.useroptions.profile.profileinfo.005') }}</dd>
      </div>
      <div>
        <dt>{{ t('theme.useroptions.useroptions.profile.profileinfo.006') }}</dt>
        <dd>@{{ profile.login }}</dd>
      </div>
      <div>
        <dt>{{ t('theme.useroptions.useroptions.profile.profileinfo.007') }}</dt>
        <dd>{{ profile.groupName || profile.groupTag || t('theme.useroptions.useroptions.profile.profileinfo.008') }}</dd>
      </div>
      <div v-if="isOwner" class="profile-info-list__email">
        <dt>{{ t('theme.useroptions.useroptions.profile.profileinfo.009') }}</dt>
        <dd>
          <div class="profile-email">
            <strong class="profile-email__address">{{ profile.email || t('theme.useroptions.useroptions.profile.profileinfo.010') }}</strong>
            <div class="profile-email__verification">
              <span
                class="profile-email__status"
                :class="{ 'profile-email__status--verified': profile.emailVerified === true }"
              >
                <i
                  class="fa-solid"
                  :class="profile.emailVerified === true ? 'fa-circle-check' : 'fa-circle-exclamation'"
                  aria-hidden="true"
                />
                {{ profile.emailVerified === true
                  ? t('theme.useroptions.useroptions.profile.profileinfo.011')
                  : t('theme.useroptions.useroptions.profile.profileinfo.012') }}
              </span>
              <button
                v-if="profile.emailVerified !== true"
                class="button button--ghost profile-email__action"
                type="button"
                :disabled="emailVerificationBusy || !profile.email"
                @click="emit('requestEmailVerification')"
              >
                <i class="fa-solid fa-envelope" aria-hidden="true" />
                {{ emailVerificationBusy
                  ? t('theme.useroptions.useroptions.profile.profileinfo.014')
                  : t('theme.useroptions.useroptions.profile.profileinfo.013') }}
              </button>
            </div>
            <small v-if="profile.emailVerified !== true" class="profile-email__hint">
              {{ t('theme.useroptions.useroptions.profile.profileinfo.015') }}
            </small>
            <small
              v-if="emailVerificationFeedback?.message"
              class="profile-email__feedback"
              :class="{ 'profile-email__feedback--success': emailVerificationFeedback.type === 'success' }"
            >
              {{ emailVerificationFeedback.message }}
            </small>
          </div>
        </dd>
      </div>
    </dl>
  </section>
</template>
