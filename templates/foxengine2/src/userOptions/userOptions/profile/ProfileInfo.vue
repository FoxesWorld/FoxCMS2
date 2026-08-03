<script setup lang="ts">
import { t } from '@/i18n'

import type { ProfileRecord } from '@engine/contracts/user-pages'

defineProps<{ profile: ProfileRecord }>()
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
    </dl>
  </section>
</template>
