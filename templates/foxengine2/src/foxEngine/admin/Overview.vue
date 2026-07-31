<script setup lang="ts">
import type { Hardware, Overview } from '@modules/AdminPanel/client/useAdminPanel'
defineProps<{ overview:Overview|null; hardware:Hardware|null; hardwareMax:number }>()
</script>
<template>
  <section class="admin-section">
    <div v-if="overview" class="admin-metrics">
      <div><span>Пользователи</span><strong>{{ overview.users }}</strong></div>
      <div><span>Активны 24 часа</span><strong>{{ overview.recentUsers }}</strong></div>
      <div><span>Серверы</span><strong>{{ overview.enabledServers }}/{{ overview.servers }}</strong></div>
      <div><span>Hardware reports</span><strong>{{ overview.hardwareReports }}</strong></div>
    </div>
    <div v-if="hardware" class="hardware-grid">
      <section v-for="(dataset, category) in hardware" :key="category">
        <h2>{{ category.toUpperCase() }}</h2>
        <div v-for="(count, vendor) in dataset" :key="vendor" class="hardware-row">
          <span>{{ vendor }}</span><i><b :style="{ width: `${Number(count) / hardwareMax * 100}%` }" /></i><strong>{{ count }}</strong>
        </div>
      </section>
    </div>
  </section>
</template>
