/* fox-runtime-template id=achievement-statistics sha256=4e155c839633ba6ef1511685fe8af894194e95441f35de43df8bfd061dc754dc */
import { createElementVNode as _createElementVNode, toDisplayString as _toDisplayString, createTextVNode as _createTextVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, vModelText as _vModelText, withDirectives as _withDirectives, renderList as _renderList, Fragment as _Fragment, vModelSelect as _vModelSelect, resolveComponent as _resolveComponent, createBlock as _createBlock } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = {
  class: "achievement-statistics",
  "aria-labelledby": "achievement-statistics-title"
}
const _hoisted_2 = { class: "achievement-statistics__header" }
const _hoisted_3 = { class: "achievement-statistics__eyebrow" }
const _hoisted_4 = { id: "achievement-statistics-title" }
const _hoisted_5 = {
  key: 0,
  class: "achievement-statistics__visible"
}
const _hoisted_6 = ["aria-label"]
const _hoisted_7 = { class: "achievement-statistics__toolbar" }
const _hoisted_8 = { class: "achievement-statistics__search" }
const _hoisted_9 = ["onUpdate:modelValue", "placeholder"]
const _hoisted_10 = ["onUpdate:modelValue", "aria-label"]
const _hoisted_11 = { value: "all" }
const _hoisted_12 = ["value"]
const _hoisted_13 = ["onUpdate:modelValue", "aria-label"]
const _hoisted_14 = { value: "all" }
const _hoisted_15 = ["value"]
const _hoisted_16 = {
  key: 0,
  class: "achievement-statistics__state",
  "aria-live": "polite"
}
const _hoisted_17 = {
  key: 1,
  class: "achievement-statistics__state achievement-statistics__state--error",
  role: "alert"
}
const _hoisted_18 = ["onClick"]
const _hoisted_19 = {
  key: 2,
  class: "achievement-statistics__state"
}
const _hoisted_20 = {
  key: 3,
  class: "achievement-statistics__state"
}
const _hoisted_21 = ["onClick"]
const _hoisted_22 = {
  key: 4,
  class: "achievement-statistics__servers"
}
const _hoisted_23 = { class: "achievement-statistics__tree" }

export function render(_ctx, _cache) {
  const _component_AchievementTreeNode = _resolveComponent("AchievementTreeNode")

  return (_openBlock(), _createElementBlock("section", _hoisted_1, [
    _createElementVNode("header", _hoisted_2, [
      _createElementVNode("div", null, [
        _createElementVNode("span", _hoisted_3, [
          _cache[0] || (_cache[0] = _createElementVNode("i", {
            class: "fa-solid fa-chart-simple",
            "aria-hidden": "true"
          }, null, -1 /* CACHED */)),
          _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.039')), 1 /* TEXT */)
        ]),
        _createElementVNode("h2", _hoisted_4, _toDisplayString(_ctx.t('engine.views.achievements.040')), 1 /* TEXT */),
        _createElementVNode("p", null, _toDisplayString(_ctx.t('engine.views.achievements.060')), 1 /* TEXT */)
      ]),
      (!_ctx.loading && _ctx.items.length > 0)
        ? (_openBlock(), _createElementBlock("span", _hoisted_5, _toDisplayString(_ctx.t('engine.views.achievements.014', [_ctx.visibleCount, _ctx.summary.achievementCount])), 1 /* TEXT */))
        : _createCommentVNode("v-if", true)
    ]),
    _createElementVNode("div", {
      class: "achievement-statistics__metrics",
      "aria-label": _ctx.t('engine.views.achievements.039')
    }, [
      _createElementVNode("article", null, [
        _cache[1] || (_cache[1] = _createElementVNode("i", {
          class: "fa-solid fa-sitemap",
          "aria-hidden": "true"
        }, null, -1 /* CACHED */)),
        _createElementVNode("span", null, [
          _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.041')), 1 /* TEXT */),
          _createElementVNode("strong", null, _toDisplayString(_ctx.summary.achievementCount), 1 /* TEXT */)
        ])
      ]),
      _createElementVNode("article", null, [
        _cache[2] || (_cache[2] = _createElementVNode("i", {
          class: "fa-solid fa-circle-check",
          "aria-hidden": "true"
        }, null, -1 /* CACHED */)),
        _createElementVNode("span", null, [
          _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.042')), 1 /* TEXT */),
          _createElementVNode("strong", null, _toDisplayString(_ctx.summary.earnedAchievementCount), 1 /* TEXT */)
        ])
      ]),
      _createElementVNode("article", null, [
        _cache[3] || (_cache[3] = _createElementVNode("i", {
          class: "fa-solid fa-users",
          "aria-hidden": "true"
        }, null, -1 /* CACHED */)),
        _createElementVNode("span", null, [
          _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.043')), 1 /* TEXT */),
          _createElementVNode("strong", null, _toDisplayString(_ctx.summary.playerCount), 1 /* TEXT */)
        ])
      ]),
      _createElementVNode("article", null, [
        _cache[4] || (_cache[4] = _createElementVNode("i", {
          class: "fa-solid fa-trophy",
          "aria-hidden": "true"
        }, null, -1 /* CACHED */)),
        _createElementVNode("span", null, [
          _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.044')), 1 /* TEXT */),
          _createElementVNode("strong", null, _toDisplayString(_ctx.summary.unlockCount), 1 /* TEXT */)
        ])
      ])
    ], 8 /* PROPS */, _hoisted_6),
    _createElementVNode("div", _hoisted_7, [
      _createElementVNode("label", _hoisted_8, [
        _cache[5] || (_cache[5] = _createElementVNode("i", {
          class: "fa-solid fa-magnifying-glass",
          "aria-hidden": "true"
        }, null, -1 /* CACHED */)),
        _withDirectives(_createElementVNode("input", {
          "onUpdate:modelValue": $event => ((_ctx.search) = $event),
          type: "search",
          placeholder: _ctx.t('engine.views.achievements.047')
        }, null, 8 /* PROPS */, _hoisted_9), [
          [_vModelText, _ctx.search]
        ])
      ]),
      _withDirectives(_createElementVNode("select", {
        "onUpdate:modelValue": $event => ((_ctx.server) = $event),
        "aria-label": _ctx.t('engine.views.achievements.048')
      }, [
        _createElementVNode("option", _hoisted_11, _toDisplayString(_ctx.t('engine.views.achievements.048')), 1 /* TEXT */),
        (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.servers, (value) => {
          return (_openBlock(), _createElementBlock("option", {
            key: value,
            value: value
          }, _toDisplayString(value), 9 /* TEXT, PROPS */, _hoisted_12))
        }), 128 /* KEYED_FRAGMENT */))
      ], 8 /* PROPS */, _hoisted_10), [
        [_vModelSelect, _ctx.server]
      ]),
      _withDirectives(_createElementVNode("select", {
        "onUpdate:modelValue": $event => ((_ctx.category) = $event),
        "aria-label": _ctx.t('engine.views.achievements.049')
      }, [
        _createElementVNode("option", _hoisted_14, _toDisplayString(_ctx.t('engine.views.achievements.049')), 1 /* TEXT */),
        (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.categories, (value) => {
          return (_openBlock(), _createElementBlock("option", {
            key: value,
            value: value
          }, _toDisplayString(value), 9 /* TEXT, PROPS */, _hoisted_15))
        }), 128 /* KEYED_FRAGMENT */))
      ], 8 /* PROPS */, _hoisted_13), [
        [_vModelSelect, _ctx.category]
      ])
    ]),
    (_ctx.loading)
      ? (_openBlock(), _createElementBlock("div", _hoisted_16, [
          _cache[6] || (_cache[6] = _createElementVNode("i", {
            class: "fa-solid fa-spinner achievement-statistics__spin",
            "aria-hidden": "true"
          }, null, -1 /* CACHED */)),
          _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.050')), 1 /* TEXT */),
          _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.051')), 1 /* TEXT */)
        ]))
      : (_ctx.error)
        ? (_openBlock(), _createElementBlock("div", _hoisted_17, [
            _cache[7] || (_cache[7] = _createElementVNode("i", {
              class: "fa-solid fa-triangle-exclamation",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.052')), 1 /* TEXT */),
            _createElementVNode("span", null, _toDisplayString(_ctx.error), 1 /* TEXT */),
            _createElementVNode("button", {
              class: "button button--ghost",
              type: "button",
              onClick: _ctx.refresh
            }, _toDisplayString(_ctx.t('engine.views.achievements.026')), 9 /* TEXT, PROPS */, _hoisted_18)
          ]))
        : (_ctx.items.length === 0)
          ? (_openBlock(), _createElementBlock("div", _hoisted_19, [
              _cache[8] || (_cache[8] = _createElementVNode("i", {
                class: "fa-solid fa-sitemap",
                "aria-hidden": "true"
              }, null, -1 /* CACHED */)),
              _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.053')), 1 /* TEXT */),
              _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.054')), 1 /* TEXT */)
            ]))
          : (_ctx.filteredTrees.length === 0)
            ? (_openBlock(), _createElementBlock("div", _hoisted_20, [
                _cache[9] || (_cache[9] = _createElementVNode("i", {
                  class: "fa-solid fa-filter-circle-xmark",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.029')), 1 /* TEXT */),
                _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.030')), 1 /* TEXT */),
                _createElementVNode("button", {
                  class: "button button--ghost",
                  type: "button",
                  onClick: _ctx.resetFilters
                }, _toDisplayString(_ctx.t('engine.views.achievements.032')), 9 /* TEXT, PROPS */, _hoisted_21)
              ]))
            : (_openBlock(), _createElementBlock("div", _hoisted_22, [
                (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.filteredTrees, (tree) => {
                  return (_openBlock(), _createElementBlock("section", {
                    key: tree.serverId,
                    class: "achievement-statistics__server"
                  }, [
                    _createElementVNode("header", null, [
                      _createElementVNode("span", null, [
                        _cache[10] || (_cache[10] = _createElementVNode("i", {
                          class: "fa-solid fa-server",
                          "aria-hidden": "true"
                        }, null, -1 /* CACHED */)),
                        _createElementVNode("span", null, [
                          _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.061')), 1 /* TEXT */),
                          _createElementVNode("strong", null, _toDisplayString(tree.serverId), 1 /* TEXT */)
                        ])
                      ]),
                      _createElementVNode("div", null, [
                        _createElementVNode("b", null, _toDisplayString(tree.achievementCount), 1 /* TEXT */),
                        _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.062')), 1 /* TEXT */),
                        _createElementVNode("b", null, _toDisplayString(tree.unlockCount), 1 /* TEXT */),
                        _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.063')), 1 /* TEXT */)
                      ])
                    ]),
                    _createElementVNode("ol", _hoisted_23, [
                      (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(tree.roots, (root) => {
                        return (_openBlock(), _createBlock(_component_AchievementTreeNode, {
                          key: `${root.serverId}:${root.achievementKey}`,
                          node: root
                        }, null, 8 /* PROPS */, ["node"]))
                      }), 128 /* KEYED_FRAGMENT */))
                    ])
                  ]))
                }), 128 /* KEYED_FRAGMENT */))
              ]))
  ]))
}
export const templateId = "achievement-statistics"
export const sourceHash = "4e155c839633ba6ef1511685fe8af894194e95441f35de43df8bfd061dc754dc"
