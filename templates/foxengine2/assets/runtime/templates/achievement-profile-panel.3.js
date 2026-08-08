/* fox-runtime-template id=achievement-profile-panel sha256=b83c3cbe85033e587ceb6d20c0df3026606d9eccc05b2588e2afe2f9ce079263 */
import { createElementVNode as _createElementVNode, toDisplayString as _toDisplayString, renderList as _renderList, Fragment as _Fragment, openBlock as _openBlock, createElementBlock as _createElementBlock, vModelSelect as _vModelSelect, withDirectives as _withDirectives, normalizeClass as _normalizeClass, createCommentVNode as _createCommentVNode, normalizeStyle as _normalizeStyle } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = ["aria-label"]
const _hoisted_2 = { class: "profile-achievements__header" }
const _hoisted_3 = { class: "profile-achievements__heading-copy" }
const _hoisted_4 = { class: "profile-achievements__actions" }
const _hoisted_5 = { class: "profile-achievements__server-select" }
const _hoisted_6 = ["onUpdate:modelValue", "aria-label", "title", "disabled"]
const _hoisted_7 = ["value"]
const _hoisted_8 = ["disabled", "aria-label", "onClick"]
const _hoisted_9 = {
  key: 0,
  class: "profile-achievements__state"
}
const _hoisted_10 = {
  key: 1,
  class: "profile-achievements__state profile-achievements__state--error",
  role: "alert"
}
const _hoisted_11 = {
  key: 2,
  class: "profile-achievements__state"
}
const _hoisted_12 = { class: "profile-achievements__summary" }
const _hoisted_13 = { class: "profile-achievements__list" }
const _hoisted_14 = ["src"]
const _hoisted_15 = { class: "profile-achievement-card__content" }
const _hoisted_16 = {
  class: "profile-achievement-card__progress",
  "aria-hidden": "true"
}
const _hoisted_17 = { class: "profile-achievement-card__meta" }

export function render(_ctx, _cache) {
  return (_openBlock(), _createElementBlock("section", {
    class: "profile-panel profile-achievements",
    "aria-label": _ctx.t('theme.profileachievements.001')
  }, [
    _createElementVNode("header", _hoisted_2, [
      _cache[1] || (_cache[1] = _createElementVNode("span", {
        class: "profile-achievements__heading-icon",
        "aria-hidden": "true"
      }, [
        _createElementVNode("i", { class: "fa-solid fa-trophy" })
      ], -1 /* CACHED */)),
      _createElementVNode("span", _hoisted_3, [
        _createElementVNode("small", null, _toDisplayString(_ctx.t('theme.profileachievements.002')), 1 /* TEXT */),
        _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.profileachievements.001')), 1 /* TEXT */),
        _createElementVNode("span", null, _toDisplayString(_ctx.t('theme.profileachievements.003')), 1 /* TEXT */)
      ]),
      _createElementVNode("span", _hoisted_4, [
        _createElementVNode("label", _hoisted_5, [
          _cache[0] || (_cache[0] = _createElementVNode("i", {
            class: "fa-solid fa-server",
            "aria-hidden": "true"
          }, null, -1 /* CACHED */)),
          _withDirectives(_createElementVNode("select", {
            "onUpdate:modelValue": $event => ((_ctx.server) = $event),
            "aria-label": _ctx.t('engine.views.achievements.061'),
            title: _ctx.t('engine.views.achievements.061'),
            disabled: _ctx.servers.length <= 1
          }, [
            (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.servers, (value) => {
              return (_openBlock(), _createElementBlock("option", {
                key: value,
                value: value
              }, _toDisplayString(value), 9 /* TEXT, PROPS */, _hoisted_7))
            }), 128 /* KEYED_FRAGMENT */))
          ], 8 /* PROPS */, _hoisted_6), [
            [_vModelSelect, _ctx.server]
          ])
        ]),
        _createElementVNode("button", {
          class: "profile-achievements__refresh",
          type: "button",
          disabled: _ctx.loading,
          "aria-label": _ctx.t('theme.profileachievements.004'),
          onClick: $event => (_ctx.refresh())
        }, [
          _createElementVNode("i", {
            class: _normalizeClass(["fa-solid fa-rotate", { 'profile-achievements__spin': _ctx.loading }]),
            "aria-hidden": "true"
          }, null, 2 /* CLASS */)
        ], 8 /* PROPS */, _hoisted_8)
      ])
    ]),
    (_ctx.loading && _ctx.items.length === 0)
      ? (_openBlock(), _createElementBlock("div", _hoisted_9, [
          _cache[2] || (_cache[2] = _createElementVNode("i", {
            class: "fa-solid fa-spinner profile-achievements__spin",
            "aria-hidden": "true"
          }, null, -1 /* CACHED */)),
          _createElementVNode("span", null, _toDisplayString(_ctx.t('theme.profileachievements.005')), 1 /* TEXT */)
        ]))
      : (_ctx.error)
        ? (_openBlock(), _createElementBlock("div", _hoisted_10, [
            _cache[3] || (_cache[3] = _createElementVNode("i", {
              class: "fa-solid fa-triangle-exclamation",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.profileachievements.010')), 1 /* TEXT */),
            _createElementVNode("span", null, _toDisplayString(_ctx.error), 1 /* TEXT */)
          ]))
        : (_ctx.items.length === 0)
          ? (_openBlock(), _createElementBlock("div", _hoisted_11, [
              _cache[4] || (_cache[4] = _createElementVNode("i", {
                class: "fa-solid fa-medal",
                "aria-hidden": "true"
              }, null, -1 /* CACHED */)),
              _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.profileachievements.006')), 1 /* TEXT */),
              _createElementVNode("span", null, _toDisplayString(_ctx.t('theme.profileachievements.007')), 1 /* TEXT */)
            ]))
          : (_openBlock(), _createElementBlock(_Fragment, { key: 3 }, [
              _createElementVNode("div", _hoisted_12, [
                _createElementVNode("article", null, [
                  _createElementVNode("small", null, _toDisplayString(_ctx.t('theme.profileachievements.012')), 1 /* TEXT */),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.summary.completedCount) + " / " + _toDisplayString(_ctx.summary.trackedCount), 1 /* TEXT */),
                  _createElementVNode("span", null, _toDisplayString(_ctx.completionPercent) + "%", 1 /* TEXT */)
                ]),
                _createElementVNode("article", null, [
                  _createElementVNode("small", null, _toDisplayString(_ctx.t('theme.profileachievements.013')), 1 /* TEXT */),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.summary.points), 1 /* TEXT */),
                  _createElementVNode("span", null, _toDisplayString(_ctx.t('theme.profileachievements.014')), 1 /* TEXT */)
                ])
              ]),
              _createElementVNode("div", _hoisted_13, [
                (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.selectedItems, (item) => {
                  return (_openBlock(), _createElementBlock("article", {
                    key: `${item.serverId}:${item.achievementKey}`,
                    class: _normalizeClass(["profile-achievement-card", {
            'is-completed': item.completed,
            'is-challenge': item.frameType === 'challenge',
          }])
                  }, [
                    _createElementVNode("img", {
                      class: "profile-achievement-card__icon",
                      src: item.iconDataUrl,
                      alt: '',
                      loading: "lazy",
                      decoding: "async"
                    }, null, 8 /* PROPS */, _hoisted_14),
                    _createElementVNode("span", _hoisted_15, [
                      _createElementVNode("small", null, _toDisplayString(item.categoryLabel || item.category) + " · " + _toDisplayString(item.serverId), 1 /* TEXT */),
                      _createElementVNode("strong", null, _toDisplayString(item.title), 1 /* TEXT */),
                      _createElementVNode("span", null, _toDisplayString(item.description || _ctx.t('theme.profileachievements.015')), 1 /* TEXT */),
                      _createElementVNode("span", _hoisted_16, [
                        _createElementVNode("span", {
                          style: _normalizeStyle({ width: `${_ctx.progressPercent(item)}%` })
                        }, null, 4 /* STYLE */)
                      ])
                    ]),
                    _createElementVNode("span", _hoisted_17, [
                      _createElementVNode("strong", null, "+" + _toDisplayString(item.points), 1 /* TEXT */),
                      _createElementVNode("span", null, _toDisplayString(_ctx.t('theme.profileachievements.014')), 1 /* TEXT */),
                      _createElementVNode("small", null, _toDisplayString(_ctx.timestamp(item.completedAt || item.updatedAt)), 1 /* TEXT */)
                    ])
                  ], 2 /* CLASS */))
                }), 128 /* KEYED_FRAGMENT */))
              ])
            ], 64 /* STABLE_FRAGMENT */))
  ], 8 /* PROPS */, _hoisted_1))
}
export const templateId = "achievement-profile-panel"
export const sourceHash = "b83c3cbe85033e587ceb6d20c0df3026606d9eccc05b2588e2afe2f9ce079263"
