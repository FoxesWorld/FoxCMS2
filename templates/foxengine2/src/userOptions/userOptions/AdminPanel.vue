<script setup lang="ts">
import { computed } from 'vue'
import { useAdminPanel } from '@modules/AdminPanel/client/useAdminPanel'
import AdminOverview from '@theme/foxEngine/admin/Overview.vue'
import AdminSlides from '@theme/foxEngine/admin/Slides.vue'
import AdminContent from '@theme/foxEngine/admin/Content.vue'
import AdminMaintenance from '@theme/foxEngine/admin/Maintenance.vue'
import AdminUsers from '@theme/foxEngine/admin/Users.vue'
import AdminServers from '@theme/foxEngine/admin/Servers.vue'
import AdminFileManager from '@theme/foxEngine/admin/FileManager.vue'
import AdminLogs from '@theme/foxEngine/admin/Logs.vue'
import AdminCatalogs from '@theme/foxEngine/admin/Catalogs.vue'

const {
  isAdmin, activeTab, loading, feedback, overview, hardware, maintenance, sliderSettings, sliderRoutes, projectPages, badgePages, contentBadges, groupOptions, badgeOptions, users, userSearch, selectedUser, userDraft,
  servers, selectedServer, serverDraft, filePath, fileParent, fileEntries, fileWritable, fileTotalBytes, selectedUpload, newDirectoryName,
  logFile, logEntries, autoRefreshLogs, catalogName, catalogRows, catalogDraft, originalCatalogKey, tabs, catalogKey, hardwareMax,
  formatTimestamp, loadMaintenance, saveMaintenance, addSlide, removeSlide, moveSlide, uploadSlideImage, saveSlides, saveProjectPages, saveBadgePage, deleteBadgePage, loadUsers, editUser, saveUser, newServer, editServer, saveServer, deleteServer,
  loadFiles, selectUpload, uploadFile, createDirectory, renameFile, deleteFile, openFile, loadLogs, clearLogs, newCatalogEntry,
  editCatalogEntry, saveCatalogEntry, deleteCatalogEntry, activate,
} = useAdminPanel()

const currentTab = computed(() => tabs.find((tab) => tab.id === activeTab.value) ?? tabs[0])
</script>

<template>
  <div v-if="!isAdmin" class="system-message system-message--error">
    <strong>Доступ запрещён</strong>
    <p>Административная панель доступна только пользователям с соответствующими правами.</p>
  </div>

  <article v-else class="content-surface admin-page admin-workspace">
    <aside class="admin-sidebar">
      <header class="admin-sidebar__header">
        <span class="admin-sidebar__mark" aria-hidden="true">
          <i class="fa-solid fa-shield-halved" />
        </span>
        <div>
          <span class="eyebrow">Control center</span>
          <strong>FoxesCraft</strong>
          <small>Администрирование</small>
        </div>
      </header>

      <nav class="admin-tabs" aria-label="Разделы управления">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          :class="{ active: activeTab === tab.id }"
          :aria-current="activeTab === tab.id ? 'page' : undefined"
          @click="activate(tab.id)"
        >
          <i class="fa-solid" :class="tab.icon" aria-hidden="true" />
          <span>
            <strong>{{ tab.label }}</strong>
            <small>{{ tab.description }}</small>
          </span>
        </button>
      </nav>

      <footer class="admin-sidebar__footer">
        <span class="admin-sidebar__status" :class="{ 'is-loading': loading }" aria-hidden="true" />
        <span>{{ loading ? 'Выполняется операция' : 'Система готова' }}</span>
      </footer>
    </aside>

    <main class="admin-workspace__main">
      <div class="admin-workspace__inner">
        <header class="admin-header">
          <div>
            <span class="eyebrow">{{ currentTab?.label }}</span>
            <h1>{{ currentTab?.label }}</h1>
            <p class="lead">{{ currentTab?.description }}</p>
          </div>
          <span class="admin-state">
            <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-circle-check'" aria-hidden="true" />
            {{ loading ? 'Обработка запроса' : 'API доступен' }}
          </span>
        </header>

        <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">
          {{ feedback.message }}
        </p>

        <section class="admin-workspace__content">
          <AdminOverview v-if="activeTab === 'overview'" :overview="overview" :hardware="hardware" :hardware-max="hardwareMax" />
          <AdminSlides
            v-else-if="activeTab === 'slides'"
            :settings="sliderSettings"
            :routes="sliderRoutes"
            :loading="loading"
            @add="addSlide"
            @remove="removeSlide"
            @move="moveSlide"
            @upload="uploadSlideImage"
            @save="saveSlides"
          />
          <AdminContent
            v-else-if="activeTab === 'content'"
            :project-pages="projectPages"
            :badge-pages="badgePages"
            :badges="contentBadges"
            :loading="loading"
            @save-project-pages="saveProjectPages"
            @save-badge-page="saveBadgePage"
            @delete-badge-page="deleteBadgePage"
          />
          <AdminMaintenance
            v-else-if="activeTab === 'maintenance'"
            :settings="maintenance"
            :groups="groupOptions"
            :loading="loading"
            @save="saveMaintenance"
          />
          <AdminUsers
            v-else-if="activeTab === 'users'"
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
            v-else-if="activeTab === 'servers'"
            :servers="servers"
            :selected="selectedServer"
            :draft="serverDraft"
            :groups="groupOptions"
            @create="newServer"
            @edit="editServer"
            @remove="deleteServer"
            @save="saveServer"
          />
          <AdminFileManager
            v-else-if="activeTab === 'files'"
            :path="filePath"
            :parent="fileParent"
            :entries="fileEntries"
            :writable="fileWritable"
            :total-bytes="fileTotalBytes"
            :selected-upload="selectedUpload"
            v-model:new-directory-name="newDirectoryName"
            :loading="loading"
            @navigate="loadFiles"
            @reload="loadFiles()"
            @select-upload="selectUpload"
            @upload="uploadFile"
            @create-directory="createDirectory"
            @open="openFile"
            @rename="renameFile"
            @remove="deleteFile"
          />
          <AdminLogs
            v-else-if="activeTab === 'logs'"
            v-model:file="logFile"
            :entries="logEntries"
            v-model:auto-refresh="autoRefreshLogs"
            @reload="loadLogs"
            @clear="clearLogs"
          />
          <AdminCatalogs
            v-else-if="activeTab === 'catalogs'"
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
        </section>
      </div>
    </main>
  </article>
</template>
