<script setup lang="ts">
import { t } from '@/i18n'

import type { ServerRow } from '@modules/AdminPanel/client/useAdminPanel'
defineProps<{ servers:ServerRow[]; selected:ServerRow|null }>()
const emit=defineEmits<{ create:[]; edit:[server:ServerRow]; remove:[server:ServerRow] }>()
</script>
<template>
  <div>
    <div class="admin-toolbar"><strong>{{ t('theme.foxengine.admin.servers.servertable.001') }}</strong><button class="button button--ghost" type="button" @click="emit('create')">{{ t('theme.foxengine.admin.servers.servertable.002') }}</button></div>
    <div class="admin-list">
      <div v-for="server in servers" :key="server.serverName" class="admin-server-row">
        <button type="button" :class="{ active:selected?.serverName===server.serverName }" @click="emit('edit',server)">
          <span>{{ server.serverName.slice(0,1).toUpperCase() }}</span><div><strong>{{ server.serverName }}</strong><small>{{ server.host }}:{{ server.port }} · {{ server.enabled===true || server.enabled==='true' ? 'enabled':'disabled' }}</small></div>
        </button>
        <button type="button" :aria-label="t('theme.foxengine.admin.servers.servertable.003')" @click="emit('remove',server)">×</button>
      </div>
    </div>
  </div>
</template>
