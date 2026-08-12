<script setup lang="ts">
import { t } from '@/i18n'

import { computed, defineAsyncComponent, defineComponent, h, markRaw, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RuntimeTpl from '@engine/runtime/RuntimeTpl.vue'
import { runtimeUserOptionsState } from '@engine/runtime/userOptions'
import { useAdminPanel } from '@modules/AdminPanel/client/useAdminPanel'
import type { AdminCategoryId, AdminSection, AdminToolId } from '@modules/AdminPanel/client/useAdminPanel'
import AdminDashboard from '@theme/foxEngine/admin/Dashboard.vue'
import AdminCategoryView from '@theme/foxEngine/admin/Category.vue'
import AdminAchievements from '@theme/foxEngine/admin/Achievements.vue'
import AdminMailView from '@theme/foxEngine/admin/Mail.vue'

const loadAdminOverview = () => import('@theme/foxEngine/admin/Overview.vue')
const loadAdminSiteSettings = () => import('@theme/foxEngine/admin/SiteSettings.vue')
const loadAdminMail = async () => ({ default: AdminMailView })
const loadAdminSlides = () => import('@theme/foxEngine/admin/Slides.vue')
const loadAdminContent = () => import('@theme/foxEngine/admin/Content.vue')
const loadAdminRewards = () => import('@theme/foxEngine/admin/Rewards.vue')
const loadAdminMaintenance = () => import('@theme/foxEngine/admin/Maintenance.vue')
const loadAdminUsers = () => import('@theme/foxEngine/admin/Users.vue')
const loadAdminAchievements = async () => ({ default: AdminAchievements })
const loadAdminServers = () => import('@theme/foxEngine/admin/Servers.vue')
const loadAdminFileManager = () => import('@theme/foxEngine/admin/FileManager.vue')
const loadAdminLogs = () => import('@theme/foxEngine/admin/Logs.vue')
const loadAdminCatalogs = () => import('@theme/foxEngine/admin/Catalogs.vue')
const loadAdminRuntimeOptions = () => import('@theme/foxEngine/admin/RuntimeOptions.vue')

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
const AdminRuntimeOptions = defineAsyncComponent(loadAdminRuntimeOptions)

const adminToolLoaders = {
  overview: loadAdminOverview,
  settings: loadAdminSiteSettings,
  mail: loadAdminMail,
  slides: loadAdminSlides,
  content: loadAdminContent,
  rewards: loadAdminRewards,
  maintenance: loadAdminMaintenance,
  users: loadAdminUsers,
  achievements: loadAdminAchievements,
  servers: loadAdminServers,
  files: loadAdminFileManager,
  logs: loadAdminLogs,
  infobox: loadAdminCatalogs,
  badges: loadAdminCatalogs,
  groups: loadAdminCatalogs,
  'runtime-options': loadAdminRuntimeOptions,
} satisfies Record<AdminToolId, () => Promise<unknown>>

function preloadAdminTool(toolId: AdminToolId): void {
  void adminToolLoaders[toolId]()
}

const route = useRoute()
const router = useRouter()
const adminPanel = useAdminPanel()
const {
  isAdmin, activeTab, loading, feedback, overview, hardware, siteSettings, siteSettingsUpdatedAt, siteSettingsStorageReady, siteSocialImageUploading, siteSocialImageError,
  mailSettings, mailSettingsUpdatedAt, mailSettingsStorageReady, mailTestStatus, mailAudienceFilter, mailAudiencePreview, mailAudienceGroups, mailAudienceStatuses, mailCampaignDraft, mailCampaignStatus, maintenance, sliderSettings, sliderRoutes, projectPages, systemPages, badgePages, contentBadges, rewardDefinitions, rewardClaimKeys, issuedRewardClaimCode, rewardDraft, groupOptions, badgeOptions, users, userSearch, selectedUser, userDraft, achievementAvailable, achievementServers, achievementPlayers, achievementMods, achievementServerId, achievementModId, achievementPlayerSearch,
  servers, jdkOptions, jdkCatalog, gameVersionOptions, gameVersionCatalog, selectedServer, serverDraft, serverImageUploading, serverImageError, filePath, fileParent, fileEntries, fileWritable, fileTotalBytes, selectedUpload, fileUploading, newDirectoryName,
  logFile, logEntries, autoRefreshLogs, catalogName, catalogRows, catalogDraft, originalCatalogKey, tabs, groupedTabs, catalogKey,
  runtimeOptionsDraft, runtimeOptionsUpdatedAt, runtimeOptionsStorageReady, runtimeUserOptionsRevision,
  formatTimestamp, loadSiteSettings, saveSiteSettings, clearSiteSocialImage, uploadSiteSocialImage, loadMailSettings, saveMailSettings, testMailSettings, previewMailAudience, sendMailCampaign, loadUserOptionsEditor, saveUserOptionsEditor, loadMaintenance, saveMaintenance, addSlide, removeSlide, reorderSlide,
  uploadSlideImage, saveSlides, saveProjectPages, saveBadgePage, deleteBadgePage, newReward, editReward, saveReward, deleteReward, issueRewardClaimKey, revokeRewardClaimKey, clearIssuedRewardClaimCode, loadUsers, searchUsers, editUser, saveUser, grantUserBadge, revokeUserBadge, loadAchievementAdmin, saveAchievementEconomy, selectAchievementServer, selectAchievementMod, setAchievementPlayerSearch, searchAchievementPlayers, clearAchievementMod, clearAchievementServer, clearAchievementPlayer, newServer, editServer, clearServerImage, uploadServerImage, saveServer, deleteServer,
  loadFiles, selectUpload, uploadFile, createDirectory, renameFile, deleteFile, openFile, loadLogs, clearLogs, newCatalogEntry,
  editCatalogEntry, saveCatalogEntry, deleteCatalogEntry, activate,
} = adminPanel

const queryValue = (value: unknown): string => Array.isArray(value)
  ? String(value[0] ?? '')
  : typeof value === 'string' ? value : ''

function normalizeTool(value: unknown): AdminSection {
  const candidate = queryValue(value)
  return tabs.value.some((tool) => tool.id === candidate) ? candidate as AdminToolId : 'home'
}

function normalizeCategory(value: unknown): AdminCategoryId | null {
  const candidate = queryValue(value)
  return groupedTabs.value.some((category) => category.id === candidate)
    ? candidate as AdminCategoryId
    : null
}

const currentTool = computed(() => {
  const tool = normalizeTool(route.query.section)
  return tool === 'home' ? null : tabs.value.find((entry) => entry.id === tool) ?? null
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
  const tool = tabs.value.find((entry) => entry.id === toolId)
  if (!tool) return
  preloadAdminTool(tool.id)
  await router.push({ query: { ...route.query, group: tool.category, section: tool.id } })
}

watch(
  () => [route.query.group, route.query.section, runtimeUserOptionsRevision.value] as const,
  ([groupValue, sectionValue]) => {
    const section = normalizeTool(sectionValue)
    if (section !== 'home') {
      const tool = tabs.value.find((entry) => entry.id === section)
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

const adminTemplate = computed(() => runtimeUserOptionsState.document?.templates.adminPanel ?? null)

// Keep mail campaign data/actions independent from the editable runtime TPL revision.
// Older cached templates only know SMTP props, while the bridge always reads the current store.
const AdminMailBridge = markRaw(defineComponent({
  name: 'AdminMailBridge',
  setup: () => () => h(AdminMailView, {
    settings: mailSettings,
    status: mailTestStatus.value,
    loading: loading.value,
    updatedAt: mailSettingsUpdatedAt.value,
    storageReady: mailSettingsStorageReady.value,
    audienceFilter: mailAudienceFilter,
    audience: mailAudiencePreview.value,
    audienceGroups: mailAudienceGroups.value,
    audienceStatuses: mailAudienceStatuses.value,
    campaign: mailCampaignDraft,
    campaignStatus: mailCampaignStatus.value,
    onSave: saveMailSettings,
    onTest: testMailSettings,
    onPreviewAudience: previewMailAudience,
    onSendCampaign: sendMailCampaign,
  }),
}))

// Keep the achievement admin independent from the editable runtime TPL revision.
// Older cached AdminPanel.tpl revisions do not know about newer props such as
// achievementMods/modId, so wiring through the TPL can silently degrade the UI.
const AdminAchievementsBridge = markRaw(defineComponent({
  name: 'AdminAchievementsBridge',
  setup: () => () => h(AdminAchievements, {
    available: achievementAvailable.value,
    servers: achievementServers.value,
    players: achievementPlayers.value,
    mods: achievementMods.value,
    serverId: achievementServerId.value,
    modId: achievementModId.value,
    search: achievementPlayerSearch.value,
    loading: loading.value,
    economy: adminPanel.achievementEconomy,
    economyStats: adminPanel.achievementEconomyStats,
    onSelectServer: selectAchievementServer,
    onSelectMod: selectAchievementMod,
    'onUpdate:search': setAchievementPlayerSearch,
    onSearch: searchAchievementPlayers,
    onReload: loadAchievementAdmin,
    onClearMod: clearAchievementMod,
    onClearServer: clearAchievementServer,
    onClearPlayer: clearAchievementPlayer,
    onSaveEconomy: saveAchievementEconomy,
  }),
}))

const adminTemplateComponents = markRaw({
  AdminDashboard,
  AdminCategoryView,
  AdminOverview,
  AdminSiteSettings,
  AdminMail: AdminMailBridge,
  AdminSlides,
  AdminContent,
  AdminRewards,
  AdminMaintenance,
  AdminUsers,
  AdminAchievements: AdminAchievementsBridge,
  AdminServers,
  AdminFileManager,
  AdminLogs,
  AdminRuntimeOptions,
  AdminCatalogs,
})
const adminTemplateContext: Record<string, unknown> = {
  t,
  ...adminPanel,
  currentTool,
  currentCategory,
  isHome,
  isCategory,
  isTool,
  pageTitle,
  pageDescription,
  navigateHome,
  navigateCategory,
  navigateTool,
  preloadAdminTool,
}

</script>

<template>
  <RuntimeTpl
    v-if="adminTemplate"
    :template-id="adminTemplate.id"
    :module-url="adminTemplate.moduleUrl"
    :revision="adminTemplate.revision"
    :context="adminTemplateContext"
    :components="adminTemplateComponents"
  />
  <div v-else class="runtime-panel-skeleton runtime-panel-skeleton--admin" aria-hidden="true"><span /><span /><span /><span /></div>
</template>
