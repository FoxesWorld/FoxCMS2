<script setup lang="ts">
import { useAdminPanel } from '@modules/AdminPanel/client/useAdminPanel'
import AdminOverview from '@theme/foxEngine/admin/Overview.vue'
import AdminMaintenance from '@theme/foxEngine/admin/Maintenance.vue'
import AdminUsers from '@theme/foxEngine/admin/Users.vue'
import AdminServers from '@theme/foxEngine/admin/Servers.vue'
import AdminLogs from '@theme/foxEngine/admin/Logs.vue'
import AdminCatalogs from '@theme/foxEngine/admin/Catalogs.vue'

const {
  isAdmin, activeTab, loading, feedback, overview, hardware, maintenance, groupOptions, badgeOptions, users, userSearch, selectedUser, userDraft,
  servers, selectedServer, serverDraft, logFile, logEntries, autoRefreshLogs, catalogName, catalogRows,
  catalogDraft, originalCatalogKey, tabs, catalogKey, hardwareMax, formatTimestamp, loadMaintenance, saveMaintenance, loadUsers, editUser,
  saveUser, newServer, editServer, saveServer, deleteServer, loadLogs, clearLogs, newCatalogEntry,
  editCatalogEntry, saveCatalogEntry, deleteCatalogEntry, activate,
} = useAdminPanel()
</script>

<template>
  <div v-if="!isAdmin" class="system-message system-message--error">
    <strong>Доступ запрещён</strong>
    <p>Административное рабочее пространство доступно только группе с тегом admin.</p>
  </div>
  <article v-else class="content-surface admin-page">
    <header class="admin-header">
      <div>
        <span class="eyebrow">Operations workspace</span>
        <h1>Управление FoxesCraft</h1>
        <p class="lead">Безопасный Vue-интерфейс для пользователей, серверов, журналов и справочных каталогов.</p>
      </div>
      <span class="admin-state">{{ loading ? 'Выполняется запрос' : 'API готов' }}</span>
    </header>

    <nav class="admin-tabs" aria-label="Разделы управления">
      <button v-for="tab in tabs" :key="tab.id" type="button" :class="{ active:activeTab===tab.id }" @click="activate(tab.id)">
        {{ tab.label }}
      </button>
    </nav>

    <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success':feedback.type==='success' }">
      {{ feedback.message }}
    </p>

    <AdminOverview v-if="activeTab==='overview'" :overview="overview" :hardware="hardware" :hardware-max="hardwareMax" />
    <AdminMaintenance
      v-else-if="activeTab==='maintenance'"
      :settings="maintenance"
      :groups="groupOptions"
      :loading="loading"
      @save="saveMaintenance"
    />
    <AdminUsers
      v-else-if="activeTab==='users'"
      :users="users"
      :groups="groupOptions"
      :badge-options="badgeOptions"
      v-model:search="userSearch"
      :selected="selectedUser"
      :draft="userDraft"
      :format-timestamp="formatTimestamp"
      @search="loadUsers"
      @edit="editUser"
      @save="saveUser"
    />
    <AdminServers
      v-else-if="activeTab==='servers'"
      :servers="servers"
      :selected="selectedServer"
      :draft="serverDraft"
      :groups="groupOptions"
      @create="newServer"
      @edit="editServer"
      @remove="deleteServer"
      @save="saveServer"
    />
    <AdminLogs
      v-else-if="activeTab==='logs'"
      v-model:file="logFile"
      :entries="logEntries"
      v-model:auto-refresh="autoRefreshLogs"
      @reload="loadLogs"
      @clear="clearLogs"
    />
    <AdminCatalogs
      v-else
      v-model:name="catalogName"
      :rows="catalogRows"
      :key-field="catalogKey"
      :original-key="originalCatalogKey"
      v-model:draft="catalogDraft"
      @create="newCatalogEntry"
      @edit="editCatalogEntry"
      @remove="deleteCatalogEntry"
      @save="saveCatalogEntry"
    />
  </article>
</template>
