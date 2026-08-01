<script setup lang="ts">
import LastUserCard from '@/components/LastUserCard.vue'
import ServerMonitor from '@/components/ServerMonitor.vue'
import { appBootstrap } from '@/app/context'
import { themeAsset, type BootstrapValue } from '@/domain/bootstrap'

function iconSetting(name: string, fallback: string): string {
  const settings = appBootstrap.theme.settings.sidebarIcons
  if (settings && typeof settings === 'object' && !Array.isArray(settings)) {
    const value = (settings as Record<string, BootstrapValue>)[name]
    if (typeof value === 'string') return themeAsset(appBootstrap, value.replace(/^assets\//, ''))
  }
  return themeAsset(appBootstrap, fallback)
}

const monitorIcon = iconSetting('monitor', 'icons/monitor.png')
const lastUserIcon = iconSetting('lastUser', 'icons/lastuser.png')
</script>
<template>
  <aside class="sidebar legacy-sidebar" aria-label="Информация о проекте">
    <section class="sidebar-card legacy-sidebar-card">
      <div class="sidebar-card__heading legacy-card-title">
        <img :src="monitorIcon" alt="" aria-hidden="true">
        <div><strong>Мониторинг</strong><small>Состояние игровых миров</small></div>
      </div>
      <ServerMonitor />
    </section>
    <section class="sidebar-card legacy-sidebar-card">
      <div class="sidebar-card__heading legacy-card-title">
        <img :src="lastUserIcon" alt="" aria-hidden="true">
        <div><strong>Новый лис</strong><small>Последняя регистрация</small></div>
      </div>
      <LastUserCard />
    </section>
  </aside>
</template>
