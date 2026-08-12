<fox-user-options-template id="admin-panel" schema="1" revision="12" updated-at="2026-08-10T15:30:00Z">
  <fox-admin-categories>
    <fox-admin-category id="observability" label="i18n:theme.runtime.admin.category.observability.label" description="i18n:theme.runtime.admin.category.observability.description" icon="fa-chart-line" order="10" enabled="true" />
    <fox-admin-category id="community" label="i18n:theme.runtime.admin.category.community.label" description="i18n:theme.runtime.admin.category.community.description" icon="fa-users" order="20" enabled="true" />
    <fox-admin-category id="content" label="i18n:theme.runtime.admin.category.content.label" description="i18n:theme.runtime.admin.category.content.description" icon="fa-layer-group" order="30" enabled="true" />
    <fox-admin-category id="infrastructure" label="i18n:theme.runtime.admin.category.infrastructure.label" description="i18n:theme.runtime.admin.category.infrastructure.description" icon="fa-server" order="40" enabled="true" />
  </fox-admin-categories>
  <fox-admin-tools>
    <fox-admin-tool id="overview" component="Overview" tab="overview" category="observability" label="i18n:theme.runtime.admin.tool.overview.label" description="i18n:theme.runtime.admin.tool.overview.description" icon="fa-chart-line" order="10" enabled="true" />
    <fox-admin-tool id="logs" component="Logs" tab="logs" category="observability" label="i18n:theme.runtime.admin.tool.logs.label" description="i18n:theme.runtime.admin.tool.logs.description" icon="fa-rectangle-list" order="20" enabled="true" />
    <fox-admin-tool id="users" component="Users" tab="users" category="community" label="i18n:theme.runtime.admin.tool.users.label" description="i18n:theme.runtime.admin.tool.users.description" icon="fa-users" order="10" enabled="true" />
    <fox-admin-tool id="achievements" component="Achievements" tab="achievements" category="community" label="i18n:theme.runtime.admin.tool.achievements.label" description="i18n:theme.runtime.admin.tool.achievements.description" icon="fa-trophy" order="15" enabled="true" />
    <fox-admin-tool id="infobox" component="Catalogs" tab="catalogs" category="community" label="i18n:theme.runtime.admin.tool.infobox.label" description="i18n:theme.runtime.admin.tool.infobox.description" icon="fa-circle-info" order="20" enabled="true" catalog="infobox" />
    <fox-admin-tool id="badges" component="Catalogs" tab="catalogs" category="community" label="i18n:theme.runtime.admin.tool.badges.label" description="i18n:theme.runtime.admin.tool.badges.description" icon="fa-award" order="30" enabled="true" catalog="badges" />
    <fox-admin-tool id="rewards" component="Rewards" tab="rewards" category="community" label="i18n:theme.runtime.admin.tool.rewards.label" description="i18n:theme.runtime.admin.tool.rewards.description" icon="fa-coins" order="40" enabled="true" />
    <fox-admin-tool id="groups" component="Catalogs" tab="catalogs" category="community" label="i18n:theme.runtime.admin.tool.groups.label" description="i18n:theme.runtime.admin.tool.groups.description" icon="fa-user-group" order="50" enabled="true" catalog="groups" />
    <fox-admin-tool id="content" component="Content" tab="content" category="content" label="i18n:theme.runtime.admin.tool.content.label" description="i18n:theme.runtime.admin.tool.content.description" icon="fa-newspaper" order="10" enabled="true" />
    <fox-admin-tool id="slides" component="Slides" tab="slides" category="content" label="i18n:theme.runtime.admin.tool.slides.label" description="i18n:theme.runtime.admin.tool.slides.description" icon="fa-images" order="20" enabled="true" />
    <fox-admin-tool id="settings" component="SiteSettings" tab="settings" category="content" label="i18n:theme.runtime.admin.tool.settings.label" description="i18n:theme.runtime.admin.tool.settings.description" icon="fa-sliders" order="30" enabled="true" />
    <fox-admin-tool id="runtime-options" component="RuntimeOptions" tab="runtime-options" category="content" label="i18n:theme.runtime.admin.tool.runtime-options.label" description="i18n:theme.runtime.admin.tool.runtime-options.description" icon="fa-puzzle-piece" order="40" enabled="true" protected="true" />
    <fox-admin-tool id="servers" component="Servers" tab="servers" category="infrastructure" label="i18n:theme.runtime.admin.tool.servers.label" description="i18n:theme.runtime.admin.tool.servers.description" icon="fa-server" order="10" enabled="true" />
    <fox-admin-tool id="files" component="FileManager" tab="files" category="infrastructure" label="i18n:theme.runtime.admin.tool.files.label" description="i18n:theme.runtime.admin.tool.files.description" icon="fa-folder-open" order="20" enabled="true" />
    <fox-admin-tool id="mail" component="Mail" tab="mail" category="infrastructure" label="i18n:theme.runtime.admin.tool.mail.label" description="i18n:theme.runtime.admin.tool.mail.description" icon="fa-envelope" order="25" enabled="true" />
    <fox-admin-tool id="maintenance" component="Maintenance" tab="maintenance" category="infrastructure" label="i18n:theme.runtime.admin.tool.maintenance.label" description="i18n:theme.runtime.admin.tool.maintenance.description" icon="fa-screwdriver-wrench" order="30" enabled="true" />
  </fox-admin-tools>
  <fox-template-body>
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
          <AdminMail
            v-else-if="activeTab === 'mail'"
            :settings="mailSettings"
            :status="mailTestStatus"
            :loading="loading"
            :updated-at="mailSettingsUpdatedAt"
            :storage-ready="mailSettingsStorageReady"
            @save="saveMailSettings"
            @test="testMailSettings"
          />
          <AdminSlides
            v-else-if="activeTab === 'slides'"
            :settings="sliderSettings"
            :routes="sliderRoutes"
            :loading="loading"
            @add="addSlide"
            @remove="removeSlide"
            @reorder="reorderSlide"
            @upload="uploadSlideImage"
            @save="saveSlides"
          />
          <AdminContent
            v-else-if="activeTab === 'content'"
            :project-pages="projectPages"
            :page-templates="runtimePageTemplatesDraft"
            :page-templates-storage-ready="runtimePageTemplatesStorageReady"
            :system-pages="systemPages"
            :badge-pages="badgePages"
            :badges="contentBadges"
            :loading="loading"
            @save-project-pages="saveProjectPages"
            @save-page-template="savePageTemplate"
            @reload-page-templates="loadContent"
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
          <AdminAchievements
            v-else-if="activeTab === 'achievements'"
            :available="achievementAvailable"
            :servers="achievementServers"
            :players="achievementPlayers"
            :mods="achievementMods"
            :server-id="achievementServerId"
            :mod-id="achievementModId"
            :search="achievementPlayerSearch"
            :economy="achievementEconomy"
            :economy-stats="achievementEconomyStats"
            :loading="loading"
            @select-server="selectAchievementServer"
            @select-mod="selectAchievementMod"
            @update:search="setAchievementPlayerSearch"
            @search="searchAchievementPlayers"
            @reload="loadAchievementAdmin"
            @save-economy="saveAchievementEconomy"
            @clear-mod="clearAchievementMod"
            @clear-server="clearAchievementServer"
            @clear-player="clearAchievementPlayer"
          />
          <AdminServers
            v-else-if="activeTab === 'servers'"
            :servers="servers"
            :selected="selectedServer"
            :draft="serverDraft"
            :groups="groupOptions"
            :jdk-options="jdkOptions"
            :jdk-catalog="jdkCatalog"
            :game-version-options="gameVersionOptions"
            :game-version-catalog="gameVersionCatalog"
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
              <AdminRuntimeOptions
                v-else-if="activeTab === 'runtime-options'"
                :document="runtimeOptionsDraft"
                :loading="loading"
                :updated-at="runtimeOptionsUpdatedAt"
                :storage-ready="runtimeOptionsStorageReady"
                @reload="loadUserOptionsEditor"
                @save="saveUserOptionsEditor"
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
  </fox-template-body>
</fox-user-options-template>
