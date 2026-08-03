<script setup lang="ts">
import { t } from '@/i18n'

import { computed, defineAsyncComponent, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdminPanel } from '@modules/AdminPanel/client/useAdminPanel'
import type { AdminCategoryId, AdminSection, AdminToolId } from '@modules/AdminPanel/client/useAdminPanel'
import AdminDashboard from '@theme/foxEngine/admin/Dashboard.vue'
import AdminCategoryView from '@theme/foxEngine/admin/Category.vue'

const loadAdminOverview = () => import('@theme/foxEngine/admin/Overview.vue')
const loadAdminSiteSettings = () => import('@theme/foxEngine/admin/SiteSettings.vue')
const loadAdminSlides = () => import('@theme/foxEngine/admin/Slides.vue')
const loadAdminContent = () => import('@theme/foxEngine/admin/Content.vue')
const loadAdminRewards = () => import('@theme/foxEngine/admin/Rewards.vue')
const loadAdminMaintenance = () => import('@theme/foxEngine/admin/Maintenance.vue')
const loadAdminUsers = () => import('@theme/foxEngine/admin/Users.vue')
const loadAdminServers = () => import('@theme/foxEngine/admin/Servers.vue')
const loadAdminFileManager = () => import('@theme/foxEngine/admin/FileManager.vue')
const loadAdminLogs = () => import('@theme/foxEngine/admin/Logs.vue')
const loadAdminCatalogs = () => import('@theme/foxEngine/admin/Catalogs.vue')

const AdminOverview = defineAsyncComponent(loadAdminOverview)
const AdminSiteSettings = defineAsyncComponent(loadAdminSiteSettings)
const AdminSlides = defineAsyncComponent(loadAdminSlides)
const AdminContent = defineAsyncComponent(loadAdminContent)
const AdminRewards = defineAsyncComponent(loadAdminRewards)
const AdminMaintenance = defineAsyncComponent(loadAdminMaintenance)
const AdminUsers = defineAsyncComponent(loadAdminUsers)
const AdminServers = defineAsyncComponent(loadAdminServers)
const AdminFileManager = defineAsyncComponent(loadAdminFileManager)
const AdminLogs = defineAsyncComponent(loadAdminLogs)
const AdminCatalogs = defineAsyncComponent(loadAdminCatalogs)

const adminToolLoaders = {
  overview: loadAdminOverview,
  settings: loadAdminSiteSettings,
  slides: loadAdminSlides,
  content: loadAdminContent,
  rewards: loadAdminRewards,
  maintenance: loadAdminMaintenance,
  users: loadAdminUsers,
  servers: loadAdminServers,
  files: loadAdminFileManager,
  logs: loadAdminLogs,
  infobox: loadAdminCatalogs,
  badges: loadAdminCatalogs,
  groups: loadAdminCatalogs,
} satisfies Record<AdminToolId, () => Promise<unknown>>

function preloadAdminTool(toolId: AdminToolId): void {
  void adminToolLoaders[toolId]()
}

const route = useRoute()
const router = useRouter()
const {
  isAdmin, activeTab, loading, feedback, overview, hardware, siteSettings, siteSettingsUpdatedAt, siteSettingsStorageReady, siteSocialImageUploading, siteSocialImageError,
  maintenance, sliderSettings, sliderRoutes, projectPages, badgePages, contentBadges, rewardDefinitions, rewardClaimKeys, issuedRewardClaimCode, rewardDraft, groupOptions, badgeOptions, users, userSearch, selectedUser, userDraft,
  servers, jdkOptions, jdkCatalog, selectedServer, serverDraft, serverImageUploading, serverImageError, filePath, fileParent, fileEntries, fileWritable, fileTotalBytes, selectedUpload, fileUploading, newDirectoryName,
  logFile, logEntries, autoRefreshLogs, catalogName, catalogRows, catalogDraft, originalCatalogKey, tabs, groupedTabs, catalogKey,
  formatTimestamp, loadSiteSettings, saveSiteSettings, clearSiteSocialImage, uploadSiteSocialImage, loadMaintenance, saveMaintenance, addSlide, removeSlide, moveSlide,
  uploadSlideImage, saveSlides, saveProjectPages, saveBadgePage, deleteBadgePage, newReward, editReward, saveReward, deleteReward, issueRewardClaimKey, revokeRewardClaimKey, clearIssuedRewardClaimCode, loadUsers, searchUsers, editUser, saveUser, grantUserBadge, revokeUserBadge, newServer, editServer, clearServerImage, uploadServerImage, saveServer, deleteServer,
  loadFiles, selectUpload, uploadFile, createDirectory, renameFile, deleteFile, openFile, loadLogs, clearLogs, newCatalogEntry,
  editCatalogEntry, saveCatalogEntry, deleteCatalogEntry, activate,
} = useAdminPanel()

const queryValue = (value: unknown): string => Array.isArray(value)
  ? String(value[0] ?? '')
  : typeof value === 'string' ? value : ''

function normalizeTool(value: unknown): AdminSection {
  const candidate = queryValue(value)
  return tabs.some((tool) => tool.id === candidate) ? candidate as AdminToolId : 'home'
}

function normalizeCategory(value: unknown): AdminCategoryId | null {
  const candidate = queryValue(value)
  return groupedTabs.value.some((category) => category.id === candidate)
    ? candidate as AdminCategoryId
    : null
}

const currentTool = computed(() => {
  const tool = normalizeTool(route.query.section)
  return tool === 'home' ? null : tabs.find((entry) => entry.id === tool) ?? null
})
const currentCategory = computed(() => {
  const categoryId = currentTool.value?.category ?? normalizeCategory(route.query.group)
  return categoryId ? groupedTabs.value.find((category) => category.id === categoryId) ?? null : null
})
const isHome = computed(() => !currentCategory.value && !currentTool.value)
const isCategory = computed(() => Boolean(currentCategory.value) && !currentTool.value)
const isTool = computed(() => Boolean(currentTool.value))
const pageTitle = computed(() => currentTool.value?.label ?? t('theme.useroptions.useroptions.adminpanel.012'))
const pageDescription = computed(() => currentTool.value?.description
  ?? t('theme.useroptions.useroptions.adminpanel.013'))

async function navigateHome(): Promise<void> {
  await router.push({ query: { ...route.query, group: undefined, section: undefined } })
}

async function navigateCategory(category: AdminCategoryId): Promise<void> {
  await router.push({ query: { ...route.query, group: category, section: undefined } })
}

async function navigateTool(toolId: AdminToolId): Promise<void> {
  const tool = tabs.find((entry) => entry.id === toolId)
  if (!tool) return
  preloadAdminTool(tool.id)
  await router.push({ query: { ...route.query, group: tool.category, section: tool.id } })
}

watch(
  () => [route.query.group, route.query.section] as const,
  ([groupValue, sectionValue]) => {
    const section = normalizeTool(sectionValue)
    if (section !== 'home') {
      const tool = tabs.find((entry) => entry.id === section)
      if (!tool) { void activate('home'); return }
      preloadAdminTool(tool.id)
      if (normalizeCategory(groupValue) !== tool.category) {
        void router.replace({ query: { ...route.query, group: tool.category, section: tool.id } })
        return
      }
      void activate(section)
      return
    }
    void activate('home')
  },
  { immediate: true },
)
</script>

<template>
  <div v-if="!isAdmin" class="system-message system-message--error">
    <strong>{{ t('theme.useroptions.useroptions.adminpanel.001') }}</strong>
    <p>{{ t('theme.useroptions.useroptions.adminpanel.002') }}</p>
  </div>

  <article v-else class="content-surface admin-page admin-workspace" :class="{ 'admin-workspace--home': isHome, 'admin-workspace--navigation': isHome || isCategory }">
    <nav class="admin-breadcrumbs" :aria-label="t('theme.useroptions.useroptions.adminpanel.003')">
      <button
        type="button"
        :class="{ 'is-current': isHome }"
        :aria-current="isHome ? 'page' : undefined"
        @click="navigateHome"
      >
        <i class="fa-solid fa-shield-halved" aria-hidden="true" />
        <span>{{ t('theme.useroptions.useroptions.adminpanel.004') }}</span>
      </button>
      <template v-if="currentCategory">
        <i class="fa-solid fa-chevron-right" aria-hidden="true" />
        <button
          type="button"
          class="admin-breadcrumbs__level"
          :class="{ 'is-current': isCategory }"
          :aria-current="isCategory ? 'page' : undefined"
          @click="navigateCategory(currentCategory.id)"
        >
          <i class="fa-solid" :class="currentCategory.icon" aria-hidden="true" />
          {{ currentCategory.label }}
        </button>
      </template>
      <template v-if="currentTool">
        <i class="fa-solid fa-chevron-right" aria-hidden="true" />
        <span class="admin-breadcrumbs__level is-current" aria-current="page">
          <i class="fa-solid" :class="currentTool.icon" aria-hidden="true" />
          {{ currentTool.label }}
        </span>
      </template>
      <span class="admin-breadcrumbs__status" :class="{ 'is-loading': loading }">
        <i class="fa-solid" :class="loading ? 'fa-spinner' : 'fa-circle-check'" aria-hidden="true" />
        {{ loading ? t('theme.useroptions.useroptions.adminpanel.005') : t('theme.useroptions.useroptions.adminpanel.006') }}
      </span>
    </nav>

    <main class="admin-workspace__main">
      <div class="admin-workspace__inner">
        <header v-if="isTool" class="admin-header admin-header--section">
          <button
            class="admin-header__back"
            type="button"
            @click="currentCategory && navigateCategory(currentCategory.id)"
          >
            <i class="fa-solid fa-arrow-left" aria-hidden="true" />
            <span>{{ t('theme.useroptions.useroptions.adminpanel.007') }}</span>
          </button>
          <div class="admin-header__identity">
            <span class="admin-header__icon" aria-hidden="true">
              <i class="fa-solid" :class="currentTool?.icon" />
            </span>
            <div>
              <span class="eyebrow">{{ currentCategory?.label }}</span>
              <h1>{{ pageTitle }}</h1>
              <p class="lead">{{ pageDescription }}</p>
            </div>
          </div>
        </header>

        <section
          v-if="feedback"
          class="form-feedback admin-feedback"
          :class="{
            'form-feedback--success': feedback.type === 'success',
            'form-feedback--warning': feedback.type === 'warning',
            'admin-feedback--warning': feedback.type === 'warning',
            'admin-feedback--error': feedback.type === 'error',
          }"
          role="status"
        >
          <div class="admin-feedback__message">
            <i
              class="fa-solid"
              :class="feedback.type === 'success'
                ? 'fa-circle-check'
                : feedback.type === 'warning'
                  ? 'fa-circle-exclamation'
                  : 'fa-circle-xmark'"
              aria-hidden="true"
            />
            <span>{{ feedback.message }}</span>
          </div>
          <dl v-if="feedback.error" class="admin-feedback__details">
            <div>
              <dt>{{ t('theme.useroptions.useroptions.adminpanel.008') }}</dt>
              <dd><code>{{ feedback.error.action }}</code></dd>
            </div>
            <div>
              <dt>{{ t('theme.useroptions.useroptions.adminpanel.009') }}</dt>
              <dd><code>{{ feedback.error.exception }}</code></dd>
            </div>
            <div class="admin-feedback__details-reason">
              <dt>{{ t('theme.useroptions.useroptions.adminpanel.010') }}</dt>
              <dd>{{ feedback.error.detail }}</dd>
            </div>
            <div>
              <dt>{{ t('theme.useroptions.useroptions.adminpanel.011') }}</dt>
              <dd><code>{{ feedback.error.requestId || feedback.requestId || '—' }}</code></dd>
            </div>
          </dl>
        </section>

        <section class="admin-workspace__content" :class="{ 'admin-workspace__content--dashboard': isHome || isCategory }">
          <AdminDashboard
            v-if="isHome"
            :categories="groupedTabs"
            :overview="overview"
            :hardware="hardware"
            :loading="loading"
            @select="navigateTool"
            @select-category="navigateCategory"
          />
          <AdminCategoryView
            v-else-if="isCategory && currentCategory"
            :category="currentCategory"
            :overview="overview"
            :hardware="hardware"
            :loading="loading"
            @select="navigateTool"
          />
          <Suspense v-else timeout="0">
            <template #default>
              <AdminOverview v-if="activeTab === 'overview'" :overview="overview" :hardware="hardware" />
              <AdminSiteSettings
                v-else-if="activeTab === 'settings'"
            :settings="siteSettings"
            :loading="loading"
            :updated-at="siteSettingsUpdatedAt"
            :storage-ready="siteSettingsStorageReady"
            :image-uploading="siteSocialImageUploading"
            :image-error="siteSocialImageError"
            @upload-image="uploadSiteSocialImage"
            @clear-image="clearSiteSocialImage"
            @save="saveSiteSettings"
          />
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
          <AdminRewards
            v-else-if="activeTab === 'rewards'"
            :rewards="rewardDefinitions"
            :claim-keys="rewardClaimKeys"
            :issued-code="issuedRewardClaimCode"
            :badges="badgeOptions"
            :draft="rewardDraft"
            :loading="loading"
            :format-timestamp="formatTimestamp"
            @create="newReward"
            @edit="editReward"
            @save="saveReward"
            @remove="deleteReward"
            @issue-key="issueRewardClaimKey"
            @revoke-key="revokeRewardClaimKey"
            @clear-issued-code="clearIssuedRewardClaimCode"
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
            :loading="loading"
            @search="searchUsers"
            @edit="editUser"
            @save="saveUser"
            @grant-badge="grantUserBadge"
            @revoke-badge="revokeUserBadge"
          />
          <AdminServers
            v-else-if="activeTab === 'servers'"
            :servers="servers"
            :selected="selectedServer"
            :draft="serverDraft"
            :groups="groupOptions"
            :jdk-options="jdkOptions"
            :jdk-catalog="jdkCatalog"
            :loading="loading"
            :image-uploading="serverImageUploading"
            :image-error="serverImageError"
            @create="newServer"
            @edit="editServer"
            @remove="deleteServer"
            @upload-image="uploadServerImage"
            @clear-image="clearServerImage"
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
            :uploading="fileUploading"
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
                :name="catalogName"
                :rows="catalogRows"
                :key-field="catalogKey"
                :original-key="originalCatalogKey"
                v-model:draft="catalogDraft"
                @create="newCatalogEntry"
                @edit="editCatalogEntry"
                @remove="deleteCatalogEntry"
                @save="saveCatalogEntry"
              />
            </template>
            <template #fallback>
              <div class="runtime-panel-skeleton runtime-panel-skeleton--admin" aria-hidden="true">
                <span /><span /><span /><span />
              </div>
            </template>
          </Suspense>

        </section>
      </div>
    </main>
  </article>
</template>
