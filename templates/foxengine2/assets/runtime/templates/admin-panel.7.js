/* fox-runtime-template id=admin-panel sha256=240c0b6e4e71463ae2f170438ef05fcf371cf2021b566ae137eac3a595638ea5 */
import { toDisplayString as _toDisplayString, createElementVNode as _createElementVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, normalizeClass as _normalizeClass, createTextVNode as _createTextVNode, Fragment as _Fragment, resolveComponent as _resolveComponent, createBlock as _createBlock, Suspense as _Suspense, withCtx as _withCtx } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = {
  key: 0,
  class: "system-message system-message--error"
}
const _hoisted_2 = ["aria-label"]
const _hoisted_3 = ["aria-current", "onClick"]
const _hoisted_4 = ["aria-current", "onClick"]
const _hoisted_5 = {
  class: "admin-breadcrumbs__level is-current",
  "aria-current": "page"
}
const _hoisted_6 = { class: "admin-workspace__main" }
const _hoisted_7 = { class: "admin-workspace__inner" }
const _hoisted_8 = {
  key: 0,
  class: "admin-header admin-header--section"
}
const _hoisted_9 = ["onClick"]
const _hoisted_10 = { class: "admin-header__identity" }
const _hoisted_11 = {
  class: "admin-header__icon",
  "aria-hidden": "true"
}
const _hoisted_12 = { class: "eyebrow" }
const _hoisted_13 = { class: "lead" }
const _hoisted_14 = { class: "admin-feedback__message" }
const _hoisted_15 = {
  key: 0,
  class: "admin-feedback__details"
}
const _hoisted_16 = { class: "admin-feedback__details-reason" }

export function render(_ctx, _cache) {
  const _component_AdminDashboard = _resolveComponent("AdminDashboard")
  const _component_AdminCategoryView = _resolveComponent("AdminCategoryView")
  const _component_AdminOverview = _resolveComponent("AdminOverview")
  const _component_AdminSiteSettings = _resolveComponent("AdminSiteSettings")
  const _component_AdminHCaptcha = _resolveComponent("AdminHCaptcha")
  const _component_AdminMail = _resolveComponent("AdminMail")
  const _component_AdminSlides = _resolveComponent("AdminSlides")
  const _component_AdminContent = _resolveComponent("AdminContent")
  const _component_AdminRewards = _resolveComponent("AdminRewards")
  const _component_AdminMaintenance = _resolveComponent("AdminMaintenance")
  const _component_AdminUsers = _resolveComponent("AdminUsers")
  const _component_AdminAchievements = _resolveComponent("AdminAchievements")
  const _component_AdminServers = _resolveComponent("AdminServers")
  const _component_AdminFileManager = _resolveComponent("AdminFileManager")
  const _component_AdminLogs = _resolveComponent("AdminLogs")
  const _component_AdminRuntimeOptions = _resolveComponent("AdminRuntimeOptions")
  const _component_AdminCatalogs = _resolveComponent("AdminCatalogs")

  return (!_ctx.isAdmin)
    ? (_openBlock(), _createElementBlock("div", _hoisted_1, [
        _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.001')), 1 /* TEXT */),
        _createElementVNode("p", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.002')), 1 /* TEXT */)
      ]))
    : (_openBlock(), _createElementBlock("article", {
        key: 1,
        class: _normalizeClass(["content-surface admin-page admin-workspace", { 'admin-workspace--home': _ctx.isHome, 'admin-workspace--navigation': _ctx.isHome || _ctx.isCategory }])
      }, [
        _createElementVNode("nav", {
          class: "admin-breadcrumbs",
          "aria-label": _ctx.t('theme.useroptions.useroptions.adminpanel.003')
        }, [
          _createElementVNode("button", {
            type: "button",
            class: _normalizeClass({ 'is-current': _ctx.isHome }),
            "aria-current": _ctx.isHome ? 'page' : undefined,
            onClick: _ctx.navigateHome
          }, [
            _cache[0] || (_cache[0] = _createElementVNode("i", {
              class: "fa-solid fa-shield-halved",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("span", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.004')), 1 /* TEXT */)
          ], 10 /* CLASS, PROPS */, _hoisted_3),
          (_ctx.currentCategory)
            ? (_openBlock(), _createElementBlock(_Fragment, { key: 0 }, [
                _cache[1] || (_cache[1] = _createElementVNode("i", {
                  class: "fa-solid fa-chevron-right",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("button", {
                  type: "button",
                  class: _normalizeClass(["admin-breadcrumbs__level", { 'is-current': _ctx.isCategory }]),
                  "aria-current": _ctx.isCategory ? 'page' : undefined,
                  onClick: $event => (_ctx.navigateCategory(_ctx.currentCategory.id))
                }, [
                  _createElementVNode("i", {
                    class: _normalizeClass(["fa-solid", _ctx.currentCategory.icon]),
                    "aria-hidden": "true"
                  }, null, 2 /* CLASS */),
                  _createTextVNode(" " + _toDisplayString(_ctx.currentCategory.label), 1 /* TEXT */)
                ], 10 /* CLASS, PROPS */, _hoisted_4)
              ], 64 /* STABLE_FRAGMENT */))
            : _createCommentVNode("v-if", true),
          (_ctx.currentTool)
            ? (_openBlock(), _createElementBlock(_Fragment, { key: 1 }, [
                _cache[2] || (_cache[2] = _createElementVNode("i", {
                  class: "fa-solid fa-chevron-right",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("span", _hoisted_5, [
                  _createElementVNode("i", {
                    class: _normalizeClass(["fa-solid", _ctx.currentTool.icon]),
                    "aria-hidden": "true"
                  }, null, 2 /* CLASS */),
                  _createTextVNode(" " + _toDisplayString(_ctx.currentTool.label), 1 /* TEXT */)
                ])
              ], 64 /* STABLE_FRAGMENT */))
            : _createCommentVNode("v-if", true),
          _createElementVNode("span", {
            class: _normalizeClass(["admin-breadcrumbs__status", { 'is-loading': _ctx.loading }])
          }, [
            _createElementVNode("i", {
              class: _normalizeClass(["fa-solid", _ctx.loading ? 'fa-spinner' : 'fa-circle-check']),
              "aria-hidden": "true"
            }, null, 2 /* CLASS */),
            _createTextVNode(" " + _toDisplayString(_ctx.loading ? _ctx.t('theme.useroptions.useroptions.adminpanel.005') : _ctx.t('theme.useroptions.useroptions.adminpanel.006')), 1 /* TEXT */)
          ], 2 /* CLASS */)
        ], 8 /* PROPS */, _hoisted_2),
        _createElementVNode("main", _hoisted_6, [
          _createElementVNode("div", _hoisted_7, [
            (_ctx.isTool)
              ? (_openBlock(), _createElementBlock("header", _hoisted_8, [
                  _createElementVNode("button", {
                    class: "admin-header__back",
                    type: "button",
                    onClick: $event => (_ctx.currentCategory && _ctx.navigateCategory(_ctx.currentCategory.id))
                  }, [
                    _cache[3] || (_cache[3] = _createElementVNode("i", {
                      class: "fa-solid fa-arrow-left",
                      "aria-hidden": "true"
                    }, null, -1 /* CACHED */)),
                    _createElementVNode("span", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.007')), 1 /* TEXT */)
                  ], 8 /* PROPS */, _hoisted_9),
                  _createElementVNode("div", _hoisted_10, [
                    _createElementVNode("span", _hoisted_11, [
                      _createElementVNode("i", {
                        class: _normalizeClass(["fa-solid", _ctx.currentTool?.icon])
                      }, null, 2 /* CLASS */)
                    ]),
                    _createElementVNode("div", null, [
                      _createElementVNode("span", _hoisted_12, _toDisplayString(_ctx.currentCategory?.label), 1 /* TEXT */),
                      _createElementVNode("h1", null, _toDisplayString(_ctx.pageTitle), 1 /* TEXT */),
                      _createElementVNode("p", _hoisted_13, _toDisplayString(_ctx.pageDescription), 1 /* TEXT */)
                    ])
                  ])
                ]))
              : _createCommentVNode("v-if", true),
            (_ctx.feedback)
              ? (_openBlock(), _createElementBlock("section", {
                  key: 1,
                  class: _normalizeClass(["form-feedback admin-feedback", {
            'form-feedback--success': _ctx.feedback.type === 'success',
            'form-feedback--warning': _ctx.feedback.type === 'warning',
            'admin-feedback--warning': _ctx.feedback.type === 'warning',
            'admin-feedback--error': _ctx.feedback.type === 'error',
          }]),
                  role: "status"
                }, [
                  _createElementVNode("div", _hoisted_14, [
                    _createElementVNode("i", {
                      class: _normalizeClass(["fa-solid", _ctx.feedback.type === 'success'
                ? 'fa-circle-check'
                : _ctx.feedback.type === 'warning'
                  ? 'fa-circle-exclamation'
                  : 'fa-circle-xmark']),
                      "aria-hidden": "true"
                    }, null, 2 /* CLASS */),
                    _createElementVNode("span", null, _toDisplayString(_ctx.feedback.message), 1 /* TEXT */)
                  ]),
                  (_ctx.feedback.error)
                    ? (_openBlock(), _createElementBlock("dl", _hoisted_15, [
                        _createElementVNode("div", null, [
                          _createElementVNode("dt", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.008')), 1 /* TEXT */),
                          _createElementVNode("dd", null, [
                            _createElementVNode("code", null, _toDisplayString(_ctx.feedback.error.action), 1 /* TEXT */)
                          ])
                        ]),
                        _createElementVNode("div", null, [
                          _createElementVNode("dt", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.009')), 1 /* TEXT */),
                          _createElementVNode("dd", null, [
                            _createElementVNode("code", null, _toDisplayString(_ctx.feedback.error.exception), 1 /* TEXT */)
                          ])
                        ]),
                        _createElementVNode("div", _hoisted_16, [
                          _createElementVNode("dt", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.010')), 1 /* TEXT */),
                          _createElementVNode("dd", null, _toDisplayString(_ctx.feedback.error.detail), 1 /* TEXT */)
                        ]),
                        _createElementVNode("div", null, [
                          _createElementVNode("dt", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.adminpanel.011')), 1 /* TEXT */),
                          _createElementVNode("dd", null, [
                            _createElementVNode("code", null, _toDisplayString(_ctx.feedback.error.requestId || _ctx.feedback.requestId || '—'), 1 /* TEXT */)
                          ])
                        ])
                      ]))
                    : _createCommentVNode("v-if", true)
                ], 2 /* CLASS */))
              : _createCommentVNode("v-if", true),
            _createElementVNode("section", {
              class: _normalizeClass(["admin-workspace__content", { 'admin-workspace__content--dashboard': _ctx.isHome || _ctx.isCategory }])
            }, [
              (_ctx.isHome)
                ? (_openBlock(), _createBlock(_component_AdminDashboard, {
                    key: 0,
                    categories: _ctx.groupedTabs,
                    overview: _ctx.overview,
                    hardware: _ctx.hardware,
                    loading: _ctx.loading,
                    onSelect: _ctx.navigateTool,
                    onSelectCategory: _ctx.navigateCategory
                  }, null, 8 /* PROPS */, ["categories", "overview", "hardware", "loading", "onSelect", "onSelectCategory"]))
                : (_ctx.isCategory && _ctx.currentCategory)
                  ? (_openBlock(), _createBlock(_component_AdminCategoryView, {
                      key: 1,
                      category: _ctx.currentCategory,
                      overview: _ctx.overview,
                      hardware: _ctx.hardware,
                      loading: _ctx.loading,
                      onSelect: _ctx.navigateTool
                    }, null, 8 /* PROPS */, ["category", "overview", "hardware", "loading", "onSelect"]))
                  : (_openBlock(), _createBlock(_Suspense, {
                      key: 2,
                      timeout: "0"
                    }, {
                      default: _withCtx(() => [
                        (_ctx.activeTab === 'overview')
                          ? (_openBlock(), _createBlock(_component_AdminOverview, {
                              key: 0,
                              overview: _ctx.overview,
                              hardware: _ctx.hardware
                            }, null, 8 /* PROPS */, ["overview", "hardware"]))
                          : (_ctx.activeTab === 'settings')
                            ? (_openBlock(), _createBlock(_component_AdminSiteSettings, {
                                key: 1,
                                settings: _ctx.siteSettings,
                                loading: _ctx.loading,
                                "updated-at": _ctx.siteSettingsUpdatedAt,
                                "storage-ready": _ctx.siteSettingsStorageReady,
                                "image-uploading": _ctx.siteSocialImageUploading,
                                "image-error": _ctx.siteSocialImageError,
                                onUploadImage: _ctx.uploadSiteSocialImage,
                                onClearImage: _ctx.clearSiteSocialImage,
                                onSave: _ctx.saveSiteSettings
                              }, null, 8 /* PROPS */, ["settings", "loading", "updated-at", "storage-ready", "image-uploading", "image-error", "onUploadImage", "onClearImage", "onSave"]))
                            : (_ctx.activeTab === 'hcaptcha')
                              ? (_openBlock(), _createBlock(_component_AdminHCaptcha, {
                                  key: 2,
                                  settings: _ctx.siteSettings,
                                  loading: _ctx.loading,
                                  "updated-at": _ctx.siteSettingsUpdatedAt,
                                  "storage-ready": _ctx.siteSettingsStorageReady,
                                  onSave: _ctx.saveSiteSettings
                                }, null, 8 /* PROPS */, ["settings", "loading", "updated-at", "storage-ready", "onSave"]))
                              : (_ctx.activeTab === 'mail')
                                ? (_openBlock(), _createBlock(_component_AdminMail, {
                                    key: 3,
                                    settings: _ctx.mailSettings,
                                    status: _ctx.mailTestStatus,
                                    loading: _ctx.loading,
                                    "updated-at": _ctx.mailSettingsUpdatedAt,
                                    "storage-ready": _ctx.mailSettingsStorageReady,
                                    onSave: _ctx.saveMailSettings,
                                    onTest: _ctx.testMailSettings
                                  }, null, 8 /* PROPS */, ["settings", "status", "loading", "updated-at", "storage-ready", "onSave", "onTest"]))
                                : (_ctx.activeTab === 'slides')
                                  ? (_openBlock(), _createBlock(_component_AdminSlides, {
                                      key: 4,
                                      settings: _ctx.sliderSettings,
                                      routes: _ctx.sliderRoutes,
                                      loading: _ctx.loading,
                                      onAdd: _ctx.addSlide,
                                      onRemove: _ctx.removeSlide,
                                      onReorder: _ctx.reorderSlide,
                                      onUpload: _ctx.uploadSlideImage,
                                      onSave: _ctx.saveSlides
                                    }, null, 8 /* PROPS */, ["settings", "routes", "loading", "onAdd", "onRemove", "onReorder", "onUpload", "onSave"]))
                                  : (_ctx.activeTab === 'content')
                                    ? (_openBlock(), _createBlock(_component_AdminContent, {
                                        key: 5,
                                        "project-pages": _ctx.projectPages,
                                        "page-templates": _ctx.runtimePageTemplatesDraft,
                                        "page-templates-storage-ready": _ctx.runtimePageTemplatesStorageReady,
                                        "system-pages": _ctx.systemPages,
                                        "badge-pages": _ctx.badgePages,
                                        badges: _ctx.contentBadges,
                                        loading: _ctx.loading,
                                        onSaveProjectPages: _ctx.saveProjectPages,
                                        onSavePageTemplate: _ctx.savePageTemplate,
                                        onReloadPageTemplates: _ctx.loadContent,
                                        onSaveBadgePage: _ctx.saveBadgePage,
                                        onDeleteBadgePage: _ctx.deleteBadgePage
                                      }, null, 8 /* PROPS */, ["project-pages", "page-templates", "page-templates-storage-ready", "system-pages", "badge-pages", "badges", "loading", "onSaveProjectPages", "onSavePageTemplate", "onReloadPageTemplates", "onSaveBadgePage", "onDeleteBadgePage"]))
                                    : (_ctx.activeTab === 'rewards')
                                      ? (_openBlock(), _createBlock(_component_AdminRewards, {
                                          key: 6,
                                          rewards: _ctx.rewardDefinitions,
                                          "claim-keys": _ctx.rewardClaimKeys,
                                          "issued-code": _ctx.issuedRewardClaimCode,
                                          badges: _ctx.badgeOptions,
                                          draft: _ctx.rewardDraft,
                                          loading: _ctx.loading,
                                          "format-timestamp": _ctx.formatTimestamp,
                                          onCreate: _ctx.newReward,
                                          onEdit: _ctx.editReward,
                                          onSave: _ctx.saveReward,
                                          onRemove: _ctx.deleteReward,
                                          onIssueKey: _ctx.issueRewardClaimKey,
                                          onRevokeKey: _ctx.revokeRewardClaimKey,
                                          onClearIssuedCode: _ctx.clearIssuedRewardClaimCode
                                        }, null, 8 /* PROPS */, ["rewards", "claim-keys", "issued-code", "badges", "draft", "loading", "format-timestamp", "onCreate", "onEdit", "onSave", "onRemove", "onIssueKey", "onRevokeKey", "onClearIssuedCode"]))
                                      : (_ctx.activeTab === 'maintenance')
                                        ? (_openBlock(), _createBlock(_component_AdminMaintenance, {
                                            key: 7,
                                            settings: _ctx.maintenance,
                                            groups: _ctx.groupOptions,
                                            loading: _ctx.loading,
                                            onSave: _ctx.saveMaintenance
                                          }, null, 8 /* PROPS */, ["settings", "groups", "loading", "onSave"]))
                                        : (_ctx.activeTab === 'users')
                                          ? (_openBlock(), _createBlock(_component_AdminUsers, {
                                              key: 8,
                                              users: _ctx.users,
                                              groups: _ctx.groupOptions,
                                              "badge-options": _ctx.badgeOptions,
                                              search: _ctx.userSearch,
                                              "onUpdate:search": $event => ((_ctx.userSearch) = $event),
                                              selected: _ctx.selectedUser,
                                              draft: _ctx.userDraft,
                                              "format-timestamp": _ctx.formatTimestamp,
                                              loading: _ctx.loading,
                                              onSearch: _ctx.searchUsers,
                                              onEdit: _ctx.editUser,
                                              onSave: _ctx.saveUser,
                                              onGrantBadge: _ctx.grantUserBadge,
                                              onRevokeBadge: _ctx.revokeUserBadge
                                            }, null, 8 /* PROPS */, ["users", "groups", "badge-options", "search", "onUpdate:search", "selected", "draft", "format-timestamp", "loading", "onSearch", "onEdit", "onSave", "onGrantBadge", "onRevokeBadge"]))
                                          : (_ctx.activeTab === 'achievements')
                                            ? (_openBlock(), _createBlock(_component_AdminAchievements, {
                                                key: 9,
                                                available: _ctx.achievementAvailable,
                                                servers: _ctx.achievementServers,
                                                players: _ctx.achievementPlayers,
                                                "server-id": _ctx.achievementServerId,
                                                search: _ctx.achievementPlayerSearch,
                                                economy: _ctx.achievementEconomy,
                                                "economy-stats": _ctx.achievementEconomyStats,
                                                loading: _ctx.loading,
                                                onSelectServer: _ctx.selectAchievementServer,
                                                "onUpdate:search": _ctx.setAchievementPlayerSearch,
                                                onSearch: _ctx.searchAchievementPlayers,
                                                onReload: _ctx.loadAchievementAdmin,
                                                onSaveEconomy: _ctx.saveAchievementEconomy,
                                                onClearServer: _ctx.clearAchievementServer,
                                                onClearPlayer: _ctx.clearAchievementPlayer
                                              }, null, 8 /* PROPS */, ["available", "servers", "players", "server-id", "search", "economy", "economy-stats", "loading", "onSelectServer", "onUpdate:search", "onSearch", "onReload", "onSaveEconomy", "onClearServer", "onClearPlayer"]))
                                            : (_ctx.activeTab === 'servers')
                                              ? (_openBlock(), _createBlock(_component_AdminServers, {
                                                  key: 10,
                                                  servers: _ctx.servers,
                                                  selected: _ctx.selectedServer,
                                                  draft: _ctx.serverDraft,
                                                  groups: _ctx.groupOptions,
                                                  "jdk-options": _ctx.jdkOptions,
                                                  "jdk-catalog": _ctx.jdkCatalog,
                                                  "game-version-options": _ctx.gameVersionOptions,
                                                  "game-version-catalog": _ctx.gameVersionCatalog,
                                                  loading: _ctx.loading,
                                                  "image-uploading": _ctx.serverImageUploading,
                                                  "image-error": _ctx.serverImageError,
                                                  onCreate: _ctx.newServer,
                                                  onEdit: _ctx.editServer,
                                                  onRemove: _ctx.deleteServer,
                                                  onUploadImage: _ctx.uploadServerImage,
                                                  onClearImage: _ctx.clearServerImage,
                                                  onSave: _ctx.saveServer
                                                }, null, 8 /* PROPS */, ["servers", "selected", "draft", "groups", "jdk-options", "jdk-catalog", "game-version-options", "game-version-catalog", "loading", "image-uploading", "image-error", "onCreate", "onEdit", "onRemove", "onUploadImage", "onClearImage", "onSave"]))
                                              : (_ctx.activeTab === 'files')
                                                ? (_openBlock(), _createBlock(_component_AdminFileManager, {
                                                    key: 11,
                                                    path: _ctx.filePath,
                                                    parent: _ctx.fileParent,
                                                    entries: _ctx.fileEntries,
                                                    writable: _ctx.fileWritable,
                                                    "total-bytes": _ctx.fileTotalBytes,
                                                    "selected-upload": _ctx.selectedUpload,
                                                    uploading: _ctx.fileUploading,
                                                    "new-directory-name": _ctx.newDirectoryName,
                                                    "onUpdate:newDirectoryName": $event => ((_ctx.newDirectoryName) = $event),
                                                    loading: _ctx.loading,
                                                    onNavigate: _ctx.loadFiles,
                                                    onReload: $event => (_ctx.loadFiles()),
                                                    onSelectUpload: _ctx.selectUpload,
                                                    onUpload: _ctx.uploadFile,
                                                    onCreateDirectory: _ctx.createDirectory,
                                                    onOpen: _ctx.openFile,
                                                    onRename: _ctx.renameFile,
                                                    onRemove: _ctx.deleteFile
                                                  }, null, 8 /* PROPS */, ["path", "parent", "entries", "writable", "total-bytes", "selected-upload", "uploading", "new-directory-name", "onUpdate:newDirectoryName", "loading", "onNavigate", "onReload", "onSelectUpload", "onUpload", "onCreateDirectory", "onOpen", "onRename", "onRemove"]))
                                                : (_ctx.activeTab === 'logs')
                                                  ? (_openBlock(), _createBlock(_component_AdminLogs, {
                                                      key: 12,
                                                      file: _ctx.logFile,
                                                      "onUpdate:file": $event => ((_ctx.logFile) = $event),
                                                      entries: _ctx.logEntries,
                                                      "auto-refresh": _ctx.autoRefreshLogs,
                                                      "onUpdate:autoRefresh": $event => ((_ctx.autoRefreshLogs) = $event),
                                                      onReload: _ctx.loadLogs,
                                                      onClear: _ctx.clearLogs
                                                    }, null, 8 /* PROPS */, ["file", "onUpdate:file", "entries", "auto-refresh", "onUpdate:autoRefresh", "onReload", "onClear"]))
                                                  : (_ctx.activeTab === 'runtime-options')
                                                    ? (_openBlock(), _createBlock(_component_AdminRuntimeOptions, {
                                                        key: 13,
                                                        document: _ctx.runtimeOptionsDraft,
                                                        loading: _ctx.loading,
                                                        "updated-at": _ctx.runtimeOptionsUpdatedAt,
                                                        "storage-ready": _ctx.runtimeOptionsStorageReady,
                                                        onReload: _ctx.loadUserOptionsEditor,
                                                        onSave: _ctx.saveUserOptionsEditor
                                                      }, null, 8 /* PROPS */, ["document", "loading", "updated-at", "storage-ready", "onReload", "onSave"]))
                                                    : (_ctx.activeTab === 'catalogs')
                                                      ? (_openBlock(), _createBlock(_component_AdminCatalogs, {
                                                          key: 14,
                                                          name: _ctx.catalogName,
                                                          rows: _ctx.catalogRows,
                                                          "key-field": _ctx.catalogKey,
                                                          "original-key": _ctx.originalCatalogKey,
                                                          draft: _ctx.catalogDraft,
                                                          "onUpdate:draft": $event => ((_ctx.catalogDraft) = $event),
                                                          onCreate: _ctx.newCatalogEntry,
                                                          onEdit: _ctx.editCatalogEntry,
                                                          onRemove: _ctx.deleteCatalogEntry,
                                                          onSave: _ctx.saveCatalogEntry
                                                        }, null, 8 /* PROPS */, ["name", "rows", "key-field", "original-key", "draft", "onUpdate:draft", "onCreate", "onEdit", "onRemove", "onSave"]))
                                                      : _createCommentVNode("v-if", true)
                      ]),
                      fallback: _withCtx(() => [...(_cache[4] || (_cache[4] = [
                        _createElementVNode("div", {
                          class: "runtime-panel-skeleton runtime-panel-skeleton--admin",
                          "aria-hidden": "true"
                        }, [
                          _createElementVNode("span"),
                          _createElementVNode("span"),
                          _createElementVNode("span"),
                          _createElementVNode("span")
                        ], -1 /* CACHED */)
                      ]))]),
                      _: 1 /* STABLE */
                    }))
            ], 2 /* CLASS */)
          ])
        ])
      ], 2 /* CLASS */))
}
export const templateId = "admin-panel"
export const sourceHash = "240c0b6e4e71463ae2f170438ef05fcf371cf2021b566ae137eac3a595638ea5"
