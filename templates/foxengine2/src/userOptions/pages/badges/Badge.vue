<script setup lang="ts">
import { t } from '@/i18n'

import type { BadgeDefinition } from '@engine/content/contentData'

defineProps<{ loading: boolean; error: boolean; badge: BadgeDefinition | null }>()
</script>

<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <div
    v-else-if="badge"
    class="badge-runtime-page"
    :data-badge-route="badge.id"
    v-html="badge.html"
  />
  <div v-else-if="error" class="system-message system-message--error">
    <strong>{{ t('theme.useroptions.pages.badges.badge.001') }}</strong>
    <p>{{ t('theme.useroptions.pages.badges.badge.002') }}</p>
  </div>
</template>
