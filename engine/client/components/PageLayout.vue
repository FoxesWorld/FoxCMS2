<template>
  <div class="content-layout page-width">
    <section class="content-column"><slot /></section>
    <SiteSidebar />
  </div>
</template>
<script setup lang="ts">
import SiteSidebar from '@/components/SiteSidebar.vue'
</script>
