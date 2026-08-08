/* fox-runtime-template id=achievement-statistics sha256=67d3f95f1baa3d57dc4191e8cf7ad924fa005aecb88f73e80863b52d9dc2a996 */
import { createElementVNode as _createElementVNode, toDisplayString as _toDisplayString, createTextVNode as _createTextVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, vModelText as _vModelText, withDirectives as _withDirectives, renderList as _renderList, Fragment as _Fragment, vModelSelect as _vModelSelect, normalizeClass as _normalizeClass, normalizeStyle as _normalizeStyle, resolveComponent as _resolveComponent, createBlock as _createBlock } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

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
const _hoisted_10 = ["onUpdate:modelValue", "aria-label", "title", "disabled"]
const _hoisted_11 = ["value"]
const _hoisted_12 = ["onClick"]
const _hoisted_13 = {
  key: 0,
  class: "achievement-statistics__state",
  "aria-live": "polite"
}
const _hoisted_14 = {
  key: 1,
  class: "achievement-statistics__state achievement-statistics__state--error",
  role: "alert"
}
const _hoisted_15 = ["onClick"]
const _hoisted_16 = {
  key: 2,
  class: "achievement-statistics__state"
}
const _hoisted_17 = {
  key: 3,
  class: "achievement-statistics__state"
}
const _hoisted_18 = ["onClick"]
const _hoisted_19 = ["aria-label"]
const _hoisted_20 = ["onClick"]
const _hoisted_21 = ["title", "aria-label"]
const _hoisted_22 = { class: "achievement-category-card__icon" }
const _hoisted_23 = ["src", "title"]
const _hoisted_24 = {
  key: 1,
  class: "fa-solid fa-trophy",
  "aria-hidden": "true"
}
const _hoisted_25 = { class: "achievement-category-card__body" }
const _hoisted_26 = { class: "achievement-category-card__meta" }
const _hoisted_27 = { class: "achievement-category-card__progress-row" }
const _hoisted_28 = { class: "achievement-category-card__unlocks" }
const _hoisted_29 = { class: "achievement-category-card__action" }
const _hoisted_30 = {
  key: 5,
  class: "achievement-statistics__state"
}
const _hoisted_31 = ["onClick"]
const _hoisted_32 = {
  key: 6,
  class: "achievement-statistics__servers"
}
const _hoisted_33 = { class: "achievement-statistics__tree" }

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
        "aria-label": _ctx.t('engine.views.achievements.061'),
        title: _ctx.t('engine.views.achievements.061'),
        disabled: _ctx.loading || _ctx.servers.length <= 1
      }, [
        (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.servers, (value) => {
          return (_openBlock(), _createElementBlock("option", {
            key: value,
            value: value
          }, _toDisplayString(value), 9 /* TEXT, PROPS */, _hoisted_11))
        }), 128 /* KEYED_FRAGMENT */))
      ], 8 /* PROPS */, _hoisted_10), [
        [_vModelSelect, _ctx.server]
      ]),
      (_ctx.activeCategorySummary)
        ? (_openBlock(), _createElementBlock("button", {
            key: 0,
            class: "button button--ghost achievements-category-back",
            type: "button",
            onClick: _ctx.closeCategory
          }, [
            _cache[6] || (_cache[6] = _createElementVNode("i", {
              class: "fa-solid fa-arrow-left",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.067')), 1 /* TEXT */)
          ], 8 /* PROPS */, _hoisted_12))
        : _createCommentVNode("v-if", true)
    ]),
    (_ctx.loading)
      ? (_openBlock(), _createElementBlock("div", _hoisted_13, [
          _cache[7] || (_cache[7] = _createElementVNode("i", {
            class: "fa-solid fa-spinner achievement-statistics__spin",
            "aria-hidden": "true"
          }, null, -1 /* CACHED */)),
          _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.050')), 1 /* TEXT */),
          _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.051')), 1 /* TEXT */)
        ]))
      : (_ctx.error)
        ? (_openBlock(), _createElementBlock("div", _hoisted_14, [
            _cache[8] || (_cache[8] = _createElementVNode("i", {
              class: "fa-solid fa-triangle-exclamation",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.052')), 1 /* TEXT */),
            _createElementVNode("span", null, _toDisplayString(_ctx.error), 1 /* TEXT */),
            _createElementVNode("button", {
              class: "button button--ghost",
              type: "button",
              onClick: _ctx.refresh
            }, _toDisplayString(_ctx.t('engine.views.achievements.026')), 9 /* TEXT, PROPS */, _hoisted_15)
          ]))
        : (_ctx.items.length === 0)
          ? (_openBlock(), _createElementBlock("div", _hoisted_16, [
              _cache[9] || (_cache[9] = _createElementVNode("i", {
                class: "fa-solid fa-sitemap",
                "aria-hidden": "true"
              }, null, -1 /* CACHED */)),
              _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.053')), 1 /* TEXT */),
              _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.054')), 1 /* TEXT */)
            ]))
          : (_ctx.categoryIndex && _ctx.visibleCategorySummaries.length === 0)
            ? (_openBlock(), _createElementBlock("div", _hoisted_17, [
                _cache[10] || (_cache[10] = _createElementVNode("i", {
                  class: "fa-solid fa-filter-circle-xmark",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.029')), 1 /* TEXT */),
                _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.030')), 1 /* TEXT */),
                _createElementVNode("button", {
                  class: "button button--ghost",
                  type: "button",
                  onClick: _ctx.resetFilters
                }, _toDisplayString(_ctx.t('engine.views.achievements.032')), 9 /* TEXT, PROPS */, _hoisted_18)
              ]))
            : (_ctx.categoryIndex)
              ? (_openBlock(), _createElementBlock("div", {
                  key: 4,
                  class: "achievement-category-grid achievement-statistics__categories",
                  "aria-label": _ctx.t('engine.views.achievements.049')
                }, [
                  (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.visibleCategorySummaries, (entry) => {
                    return (_openBlock(), _createElementBlock("button", {
                      key: entry.id,
                      class: _normalizeClass(["achievement-category-card", { 'is-complete': entry.isCompleted }]),
                      style: _normalizeStyle({ '--category-progress': `${entry.completionPercent}%` }),
                      type: "button",
                      onClick: $event => (_ctx.openCategory(entry.id))
                    }, [
                      (entry.isCompleted)
                        ? (_openBlock(), _createElementBlock("span", {
                            key: 0,
                            class: "achievement-category-card__complete",
                            title: _ctx.t('engine.views.achievements.070'),
                            "aria-label": _ctx.t('engine.views.achievements.070')
                          }, [...(_cache[11] || (_cache[11] = [
                            _createElementVNode("i", {
                              class: "fa-solid fa-check",
                              "aria-hidden": "true"
                            }, null, -1 /* CACHED */)
                          ]))], 8 /* PROPS */, _hoisted_21))
                        : _createCommentVNode("v-if", true),
                      _createElementVNode("span", _hoisted_22, [
                        (entry.iconDataUrl)
                          ? (_openBlock(), _createElementBlock("img", {
                              key: 0,
                              src: entry.iconDataUrl,
                              title: entry.iconItem || entry.label,
                              alt: "",
                              loading: "lazy",
                              decoding: "async"
                            }, null, 8 /* PROPS */, _hoisted_23))
                          : (_openBlock(), _createElementBlock("i", _hoisted_24))
                      ]),
                      _createElementVNode("span", _hoisted_25, [
                        _createElementVNode("span", _hoisted_26, [
                          _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.064')), 1 /* TEXT */),
                          _createElementVNode("b", null, _toDisplayString(entry.completedCount) + " / " + _toDisplayString(entry.totalCount), 1 /* TEXT */)
                        ]),
                        _createElementVNode("strong", null, _toDisplayString(entry.label), 1 /* TEXT */),
                        _createElementVNode("span", _hoisted_27, [
                          _cache[12] || (_cache[12] = _createElementVNode("span", {
                            class: "achievement-category-card__progress",
                            "aria-hidden": "true"
                          }, [
                            _createElementVNode("i")
                          ], -1 /* CACHED */)),
                          _createElementVNode("b", null, _toDisplayString(entry.completionPercent) + "%", 1 /* TEXT */)
                        ]),
                        _createElementVNode("em", null, _toDisplayString(_ctx.t('engine.views.achievements.065', [entry.completedCount, entry.totalCount])), 1 /* TEXT */),
                        _createElementVNode("em", _hoisted_28, [
                          _cache[13] || (_cache[13] = _createElementVNode("i", {
                            class: "fa-solid fa-trophy",
                            "aria-hidden": "true"
                          }, null, -1 /* CACHED */)),
                          _createTextVNode(" " + _toDisplayString(entry.unlockCount) + " " + _toDisplayString(_ctx.t('engine.views.achievements.063')), 1 /* TEXT */)
                        ])
                      ]),
                      _createElementVNode("span", _hoisted_29, [
                        _createTextVNode(_toDisplayString(_ctx.t('engine.views.achievements.066')) + " ", 1 /* TEXT */),
                        _cache[14] || (_cache[14] = _createElementVNode("i", {
                          class: "fa-solid fa-arrow-right",
                          "aria-hidden": "true"
                        }, null, -1 /* CACHED */))
                      ])
                    ], 14 /* CLASS, STYLE, PROPS */, _hoisted_20))
                  }), 128 /* KEYED_FRAGMENT */))
                ], 8 /* PROPS */, _hoisted_19))
              : (_ctx.filteredTrees.length === 0)
                ? (_openBlock(), _createElementBlock("div", _hoisted_30, [
                    _cache[15] || (_cache[15] = _createElementVNode("i", {
                      class: "fa-solid fa-filter-circle-xmark",
                      "aria-hidden": "true"
                    }, null, -1 /* CACHED */)),
                    _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.029')), 1 /* TEXT */),
                    _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.030')), 1 /* TEXT */),
                    _createElementVNode("button", {
                      class: "button button--ghost",
                      type: "button",
                      onClick: _ctx.resetFilters
                    }, _toDisplayString(_ctx.t('engine.views.achievements.032')), 9 /* TEXT, PROPS */, _hoisted_31)
                  ]))
                : (_openBlock(), _createElementBlock("div", _hoisted_32, [
                    (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.filteredTrees, (tree) => {
                      return (_openBlock(), _createElementBlock("section", {
                        key: tree.serverId,
                        class: "achievement-statistics__server"
                      }, [
                        _createElementVNode("header", null, [
                          _createElementVNode("span", null, [
                            _cache[16] || (_cache[16] = _createElementVNode("i", {
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
                        _createElementVNode("ol", _hoisted_33, [
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
export const sourceHash = "67d3f95f1baa3d57dc4191e8cf7ad924fa005aecb88f73e80863b52d9dc2a996"
