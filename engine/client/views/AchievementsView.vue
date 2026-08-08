<script setup lang="ts">
import { defineAsyncComponent } from 'vue'

const AchievementsRuntimeHost = defineAsyncComponent(
  () => import('@/achievements/AchievementsRuntimeHost.vue'),
)
</script>

<template>
  <AchievementsRuntimeHost />
</template>
