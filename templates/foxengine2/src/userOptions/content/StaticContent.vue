<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import type { StaticPageDefinition } from '@engine/content/contentData'
import PrivacyPolicy from './PrivacyPolicy.vue'
import Cookies from './Cookies.vue'
import VerifiedLibs from './VerifiedLibs.vue'
import UnVerifiedLibs from './UnVerifiedLibs.vue'
import NewAge from './NewAge.vue'
import UpcomingUpdates from '../pages/UpcomingUpdates.vue'
const props = defineProps<{ pageId: string; loading: boolean; error: boolean; page: StaticPageDefinition | null }>()
const pages: Record<string, Component> = { privacy: PrivacyPolicy, cookies: Cookies, 'verified-libraries': VerifiedLibs, 'unverified-libraries': UnVerifiedLibs, technology: NewAge, roadmap: UpcomingUpdates }
const component = computed(() => pages[props.pageId] ?? NewAge)
</script>
<template>
  <div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <component :is="component" v-else-if="page" :page="page" />
  <div v-else-if="error" class="system-message system-message--error"><strong>Страница не найдена</strong><p>Запрошенный материал отсутствует в реестре контента.</p></div>
</template>
