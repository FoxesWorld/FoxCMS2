<script setup lang="ts">
import { t } from '@/i18n'

import Monitoring from '@theme/foxEngine/monitor/Monitoring.vue'
import LastUser from '@theme/foxEngine/LastUser.vue'
</script>

<template>
  <aside class="sidebar legacy-sidebar" :aria-label="t('theme.rightblock.001')">
    <Monitoring />
    <LastUser />
  </aside>
</template>
