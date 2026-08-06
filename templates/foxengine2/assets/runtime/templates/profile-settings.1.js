/* fox-runtime-template id=profile-settings sha256=d10c8b7e9468bc07ee2ed905027108fbe01febb129249d50c465b691dd64474e */
import { toDisplayString as _toDisplayString, createElementVNode as _createElementVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, renderList as _renderList, Fragment as _Fragment, normalizeClass as _normalizeClass, resolveComponent as _resolveComponent, createBlock as _createBlock, Suspense as _Suspense, withCtx as _withCtx, withModifiers as _withModifiers, normalizeStyle as _normalizeStyle } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = { class: "eyebrow" }
const _hoisted_2 = { class: "lead" }
const _hoisted_3 = {
  key: 0,
  class: "runtime-panel-skeleton",
  "aria-hidden": "true"
}
const _hoisted_4 = {
  key: 1,
  class: "system-message system-message--error",
  role: "alert"
}
const _hoisted_5 = ["aria-label"]
const _hoisted_6 = ["title", "onPointerenter", "onFocus", "onClick"]
const _hoisted_7 = ["onSubmit"]
const _hoisted_8 = {
  key: 3,
  class: "system-message system-message--error"
}
const _hoisted_9 = { class: "settings-actions" }
const _hoisted_10 = ["onClick"]
const _hoisted_11 = ["disabled"]

export function render(_ctx, _cache) {
  const _component_ProfileOption = _resolveComponent("ProfileOption")
  const _component_AppearanceOption = _resolveComponent("AppearanceOption")
  const _component_SecurityOption = _resolveComponent("SecurityOption")

  return (_openBlock(), _createElementBlock("article", {
    class: "content-surface settings-page",
    style: _normalizeStyle({ '--settings-accent': _ctx.accent })
  }, [
    _createElementVNode("header", null, [
      _createElementVNode("span", _hoisted_1, _toDisplayString(_ctx.t('theme.useroptions.useroptions.profilesettings.001')), 1 /* TEXT */),
      _createElementVNode("h1", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.profilesettings.002')), 1 /* TEXT */),
      _createElementVNode("p", _hoisted_2, _toDisplayString(_ctx.t('theme.useroptions.useroptions.profilesettings.003')), 1 /* TEXT */)
    ]),
    (_ctx.runtimeUserOptionsState.loading && !_ctx.runtimeUserOptionsState.loaded)
      ? (_openBlock(), _createElementBlock("div", _hoisted_3, [...(_cache[0] || (_cache[0] = [
          _createElementVNode("span", null, null, -1 /* CACHED */),
          _createElementVNode("span", null, null, -1 /* CACHED */),
          _createElementVNode("span", null, null, -1 /* CACHED */)
        ]))]))
      : (_ctx.runtimeUserOptionsState.error)
        ? (_openBlock(), _createElementBlock("div", _hoisted_4, [
            _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.profilesettings.011')), 1 /* TEXT */),
            _createElementVNode("p", null, _toDisplayString(_ctx.runtimeUserOptionsState.error), 1 /* TEXT */)
          ]))
        : (_openBlock(), _createElementBlock(_Fragment, { key: 2 }, [
            _createElementVNode("nav", {
              class: "settings-tabs",
              "aria-label": _ctx.t('theme.useroptions.useroptions.profilesettings.004')
            }, [
              (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.runtimeProfileOptions, (option) => {
                return (_openBlock(), _createElementBlock("button", {
                  key: option.id,
                  class: _normalizeClass(["button button--primary", { active: _ctx.activeTab === option.id }]),
                  type: "button",
                  title: option.description || undefined,
                  onPointerenter: $event => (_ctx.preloadOption(option.id)),
                  onFocus: $event => (_ctx.preloadOption(option.id)),
                  onClick: $event => (_ctx.emit('update:activeTab', option.id))
                }, [
                  _createElementVNode("i", {
                    class: _normalizeClass(["fa-solid", option.icon]),
                    "aria-hidden": "true"
                  }, null, 2 /* CLASS */),
                  _createElementVNode("span", null, _toDisplayString(option.label), 1 /* TEXT */)
                ], 42 /* CLASS, PROPS, NEED_HYDRATION */, _hoisted_6))
              }), 128 /* KEYED_FRAGMENT */))
            ], 8 /* PROPS */, _hoisted_5),
            _createElementVNode("form", {
              class: "settings-form",
              onSubmit: _withModifiers($event => (_ctx.emit('submit')), ["prevent"])
            }, [
              (_openBlock(), _createBlock(_Suspense, { timeout: "0" }, {
                default: _withCtx(() => [
                  (_ctx.currentComponent === 'ProfileOption')
                    ? (_openBlock(), _createBlock(_component_ProfileOption, {
                        key: 0,
                        form: _ctx.form
                      }, null, 8 /* PROPS */, ["form"]))
                    : (_ctx.currentComponent === 'AppearanceOption')
                      ? (_openBlock(), _createBlock(_component_AppearanceOption, {
                          key: _ctx.avatarPreview,
                          form: _ctx.form,
                          "avatar-preview": _ctx.avatarPreview,
                          "avatar-selected": _ctx.avatarSelected,
                          uploading: _ctx.uploading,
                          "photo-feedback": _ctx.photoFeedback,
                          accent: _ctx.accent,
                          "show-skin-settings": _ctx.showSkinSettings,
                          "viewer-group-tag": _ctx.viewerGroupTag,
                          "minecraft-uuid": _ctx.minecraftUuid,
                          "minecraft-front-preview": _ctx.minecraftFrontPreview,
                          "minecraft-back-preview": _ctx.minecraftBackPreview,
                          "minecraft-preview-loading": _ctx.minecraftPreviewLoading,
                          "minecraft-selected-skin-name": _ctx.minecraftSelectedSkinName,
                          "minecraft-selected-skin-size": _ctx.minecraftSelectedSkinSize,
                          "minecraft-selected-cloak-name": _ctx.minecraftSelectedCloakName,
                          "minecraft-selected-cloak-size": _ctx.minecraftSelectedCloakSize,
                          "minecraft-skin-input-version": _ctx.minecraftSkinInputVersion,
                          "minecraft-cloak-input-version": _ctx.minecraftCloakInputVersion,
                          "minecraft-busy": _ctx.minecraftBusy,
                          "minecraft-feedback": _ctx.minecraftFeedback,
                          onSelectAvatar: $event => (_ctx.emit('selectAvatar', $event)),
                          onClearAvatar: $event => (_ctx.emit('clearAvatar')),
                          onUploadAvatar: $event => (_ctx.emit('uploadAvatar')),
                          onSelectMinecraft: (type, event) => _ctx.emit('selectMinecraft', type, event),
                          onUploadMinecraft: $event => (_ctx.emit('uploadMinecraft', $event)),
                          onRemoveMinecraft: $event => (_ctx.emit('removeMinecraft', $event)),
                          onRefreshMinecraft: $event => (_ctx.emit('refreshMinecraft')),
                          "onUpdate:accent": $event => (_ctx.emit('update:accent', $event))
                        }, null, 8 /* PROPS */, ["form", "avatar-preview", "avatar-selected", "uploading", "photo-feedback", "accent", "show-skin-settings", "viewer-group-tag", "minecraft-uuid", "minecraft-front-preview", "minecraft-back-preview", "minecraft-preview-loading", "minecraft-selected-skin-name", "minecraft-selected-skin-size", "minecraft-selected-cloak-name", "minecraft-selected-cloak-size", "minecraft-skin-input-version", "minecraft-cloak-input-version", "minecraft-busy", "minecraft-feedback", "onSelectAvatar", "onClearAvatar", "onUploadAvatar", "onSelectMinecraft", "onUploadMinecraft", "onRemoveMinecraft", "onRefreshMinecraft", "onUpdate:accent"]))
                      : (_ctx.currentComponent === 'SecurityOption')
                        ? (_openBlock(), _createBlock(_component_SecurityOption, {
                            key: 2,
                            form: _ctx.form,
                            "require-current-password": !_ctx.canManageUsers
                          }, null, 8 /* PROPS */, ["form", "require-current-password"]))
                        : (_openBlock(), _createElementBlock("div", _hoisted_8, [
                            _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.useroptions.profilesettings.012')), 1 /* TEXT */)
                          ]))
                ]),
                fallback: _withCtx(() => [...(_cache[1] || (_cache[1] = [
                  _createElementVNode("div", {
                    class: "runtime-panel-skeleton",
                    "aria-hidden": "true"
                  }, [
                    _createElementVNode("span"),
                    _createElementVNode("span"),
                    _createElementVNode("span")
                  ], -1 /* CACHED */)
                ]))]),
                _: 1 /* STABLE */
              })),
              (_ctx.feedback)
                ? (_openBlock(), _createElementBlock("p", {
                    key: 0,
                    class: _normalizeClass(["form-feedback", { 'form-feedback--success': _ctx.feedback.type === 'success' }])
                  }, _toDisplayString(_ctx.feedback.message), 3 /* TEXT, CLASS */))
                : _createCommentVNode("v-if", true),
              _createElementVNode("div", _hoisted_9, [
                _createElementVNode("button", {
                  class: "button button--ghost",
                  type: "button",
                  onClick: $event => (_ctx.emit('navigate', 'profile'))
                }, _toDisplayString(_ctx.t('theme.useroptions.useroptions.profilesettings.008')), 9 /* TEXT, PROPS */, _hoisted_10),
                _createElementVNode("button", {
                  class: "button button--primary button--large",
                  type: "submit",
                  disabled: _ctx.submitting
                }, _toDisplayString(_ctx.submitting ? _ctx.t('theme.useroptions.useroptions.profilesettings.009') : _ctx.t('theme.useroptions.useroptions.profilesettings.010')), 9 /* TEXT, PROPS */, _hoisted_11)
              ])
            ], 40 /* PROPS, NEED_HYDRATION */, _hoisted_7)
          ], 64 /* STABLE_FRAGMENT */))
  ], 4 /* STYLE */))
}
export const templateId = "profile-settings"
export const sourceHash = "d10c8b7e9468bc07ee2ed905027108fbe01febb129249d50c465b691dd64474e"
