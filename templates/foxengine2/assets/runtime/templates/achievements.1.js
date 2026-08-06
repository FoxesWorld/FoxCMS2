/* fox-runtime-template id=achievements sha256=2cb19213001748018af0573f6d2af00fe5938d7da85d144f7c4f85ba387f4266 */
import { createElementVNode as _createElementVNode, toDisplayString as _toDisplayString, createTextVNode as _createTextVNode, vModelText as _vModelText, withDirectives as _withDirectives, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, withModifiers as _withModifiers, normalizeStyle as _normalizeStyle, normalizeClass as _normalizeClass, renderList as _renderList, Fragment as _Fragment, vModelSelect as _vModelSelect, resolveComponent as _resolveComponent, createVNode as _createVNode, Suspense as _Suspense, withCtx as _withCtx, createBlock as _createBlock } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = {
  class: "achievements-page",
  "aria-labelledby": "achievements-page-title"
}
const _hoisted_2 = { class: "achievements-hero__content" }
const _hoisted_3 = { class: "achievements-hero__eyebrow" }
const _hoisted_4 = { id: "achievements-page-title" }
const _hoisted_5 = ["onSubmit"]
const _hoisted_6 = { for: "achievement-player-search" }
const _hoisted_7 = { class: "achievements-player-search__field" }
const _hoisted_8 = ["onUpdate:modelValue", "placeholder"]
const _hoisted_9 = ["disabled"]
const _hoisted_10 = ["onClick"]
const _hoisted_11 = ["onClick"]
const _hoisted_12 = {
  key: 0,
  class: "achievements-hero__progress",
  "aria-hidden": "true"
}
const _hoisted_13 = { viewBox: "0 0 160 160" }
const _hoisted_14 = ["aria-label"]
const _hoisted_15 = { class: "achievement-metric achievement-metric--completed" }
const _hoisted_16 = { class: "achievement-metric achievement-metric--remaining" }
const _hoisted_17 = { class: "achievement-metric achievement-metric--points" }
const _hoisted_18 = { class: "achievement-metric achievement-metric--challenge" }
const _hoisted_19 = ["aria-label"]
const _hoisted_20 = { class: "achievements-overall-progress__copy" }
const _hoisted_21 = {
  class: "achievements-overall-progress__track",
  "aria-hidden": "true"
}
const _hoisted_22 = {
  key: 2,
  class: "achievements-workspace"
}
const _hoisted_23 = { class: "achievements-catalog" }
const _hoisted_24 = { class: "achievements-toolbar" }
const _hoisted_25 = { class: "achievements-toolbar__heading" }
const _hoisted_26 = { class: "achievements-toolbar__controls" }
const _hoisted_27 = { class: "achievements-search" }
const _hoisted_28 = ["onUpdate:modelValue", "placeholder"]
const _hoisted_29 = ["onUpdate:modelValue", "aria-label"]
const _hoisted_30 = { value: "all" }
const _hoisted_31 = ["value"]
const _hoisted_32 = ["onUpdate:modelValue", "aria-label"]
const _hoisted_33 = { value: "all" }
const _hoisted_34 = ["value"]
const _hoisted_35 = ["aria-label"]
const _hoisted_36 = ["onClick"]
const _hoisted_37 = ["onClick"]
const _hoisted_38 = ["onClick"]
const _hoisted_39 = ["onClick"]
const _hoisted_40 = {
  key: 0,
  class: "achievements-state",
  "aria-live": "polite"
}
const _hoisted_41 = {
  key: 1,
  class: "achievements-state achievements-state--error",
  role: "alert"
}
const _hoisted_42 = ["onClick"]
const _hoisted_43 = {
  key: 2,
  class: "achievements-state"
}
const _hoisted_44 = {
  key: 3,
  class: "achievements-state"
}
const _hoisted_45 = ["onClick"]
const _hoisted_46 = {
  key: 4,
  class: "achievements-grid"
}
const _hoisted_47 = { class: "achievement-tile__top" }
const _hoisted_48 = { class: "achievement-tile__icon-wrap" }
const _hoisted_49 = ["src"]
const _hoisted_50 = { class: "achievement-tile__badges" }
const _hoisted_51 = { class: "achievement-tile__state" }
const _hoisted_52 = { class: "achievement-tile__points" }
const _hoisted_53 = { class: "achievement-tile__body" }
const _hoisted_54 = { class: "achievement-tile__progress" }
const _hoisted_55 = { class: "achievements-sidebar" }
const _hoisted_56 = { class: "achievements-sidebar__panel" }
const _hoisted_57 = {
  key: 0,
  class: "achievements-sidebar__empty"
}
const _hoisted_58 = {
  key: 1,
  class: "achievements-recent"
}
const _hoisted_59 = ["src"]
const _hoisted_60 = {
  class: "achievements-state",
  "aria-live": "polite"
}

export function render(_ctx, _cache) {
  const _component_AchievementStatisticsTree = _resolveComponent("AchievementStatisticsTree")

  return (_openBlock(), _createElementBlock("section", _hoisted_1, [
    _createElementVNode("header", {
      class: _normalizeClass(["achievements-hero", { 'achievements-hero--global': _ctx.statisticsMode }])
    }, [
      _createElementVNode("div", _hoisted_2, [
        _createElementVNode("span", _hoisted_3, [
          _cache[0] || (_cache[0] = _createElementVNode("i", {
            class: "fa-solid fa-trophy",
            "aria-hidden": "true"
          }, null, -1 /* CACHED */)),
          _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.001')), 1 /* TEXT */)
        ]),
        _createElementVNode("h1", _hoisted_4, _toDisplayString(_ctx.t('engine.views.achievements.002')), 1 /* TEXT */),
        _createElementVNode("p", null, _toDisplayString(_ctx.t('engine.views.achievements.003')), 1 /* TEXT */),
        _createElementVNode("form", {
          class: "achievements-player-search",
          onSubmit: _withModifiers(_ctx.openPlayer, ["prevent"])
        }, [
          _createElementVNode("label", _hoisted_6, _toDisplayString(_ctx.t('engine.views.achievements.004')), 1 /* TEXT */),
          _createElementVNode("span", _hoisted_7, [
            _cache[1] || (_cache[1] = _createElementVNode("i", {
              class: "fa-solid fa-user-magnifying-glass",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _withDirectives(_createElementVNode("input", {
              id: "achievement-player-search",
              "onUpdate:modelValue": $event => ((_ctx.playerInput) = $event),
              type: "search",
              autocomplete: "off",
              placeholder: _ctx.t('engine.views.achievements.005')
            }, null, 8 /* PROPS */, _hoisted_8), [
              [_vModelText, _ctx.playerInput]
            ])
          ]),
          _createElementVNode("button", {
            class: "button button--primary",
            type: "submit",
            disabled: !_ctx.playerInput.trim()
          }, [
            _cache[2] || (_cache[2] = _createElementVNode("i", {
              class: "fa-solid fa-arrow-right",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.006')), 1 /* TEXT */)
          ], 8 /* PROPS */, _hoisted_9),
          (_ctx.isLogged && (_ctx.statisticsMode || _ctx.playerIdentity !== _ctx.currentLogin && _ctx.playerIdentity !== _ctx.currentUuid))
            ? (_openBlock(), _createElementBlock("button", {
                key: 0,
                class: "button button--ghost",
                type: "button",
                onClick: _ctx.openMyAchievements
              }, _toDisplayString(_ctx.t('engine.views.achievements.007')), 9 /* TEXT, PROPS */, _hoisted_10))
            : _createCommentVNode("v-if", true),
          (!_ctx.statisticsMode)
            ? (_openBlock(), _createElementBlock("button", {
                key: 1,
                class: "button button--ghost",
                type: "button",
                onClick: _ctx.openStatistics
              }, [
                _cache[3] || (_cache[3] = _createElementVNode("i", {
                  class: "fa-solid fa-chart-simple",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.039')), 1 /* TEXT */)
              ], 8 /* PROPS */, _hoisted_11))
            : _createCommentVNode("v-if", true)
        ], 40 /* PROPS, NEED_HYDRATION */, _hoisted_5)
      ]),
      (!_ctx.statisticsMode)
        ? (_openBlock(), _createElementBlock("div", _hoisted_12, [
            (_openBlock(), _createElementBlock("svg", _hoisted_13, [
              _cache[4] || (_cache[4] = _createElementVNode("circle", {
                class: "achievements-hero__progress-track",
                cx: "80",
                cy: "80",
                r: "66"
              }, null, -1 /* CACHED */)),
              _createElementVNode("circle", {
                class: "achievements-hero__progress-value",
                cx: "80",
                cy: "80",
                r: "66",
                style: _normalizeStyle({ '--achievement-progress': `${_ctx.completionPercent}` })
              }, null, 4 /* STYLE */)
            ])),
            _createElementVNode("span", null, [
              _createElementVNode("strong", null, _toDisplayString(_ctx.completionPercent) + "%", 1 /* TEXT */),
              _createElementVNode("small", null, _toDisplayString(_ctx.playerName), 1 /* TEXT */)
            ])
          ]))
        : _createCommentVNode("v-if", true)
    ], 2 /* CLASS */),
    (!_ctx.statisticsMode)
      ? (_openBlock(), _createElementBlock("div", {
          key: 0,
          class: "achievements-metrics",
          "aria-label": _ctx.t('engine.views.achievements.008')
        }, [
          _createElementVNode("article", _hoisted_15, [
            _cache[5] || (_cache[5] = _createElementVNode("i", {
              class: "fa-solid fa-circle-check",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("span", null, [
              _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.009')), 1 /* TEXT */),
              _createElementVNode("strong", null, _toDisplayString(_ctx.summary.completedCount), 1 /* TEXT */)
            ])
          ]),
          _createElementVNode("article", _hoisted_16, [
            _cache[6] || (_cache[6] = _createElementVNode("i", {
              class: "fa-solid fa-lock",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("span", null, [
              _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.010')), 1 /* TEXT */),
              _createElementVNode("strong", null, _toDisplayString(_ctx.remainingCount), 1 /* TEXT */)
            ])
          ]),
          _createElementVNode("article", _hoisted_17, [
            _cache[7] || (_cache[7] = _createElementVNode("i", {
              class: "fa-solid fa-star",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("span", null, [
              _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.011')), 1 /* TEXT */),
              _createElementVNode("strong", null, _toDisplayString(_ctx.summary.points), 1 /* TEXT */)
            ])
          ]),
          _createElementVNode("article", _hoisted_18, [
            _cache[8] || (_cache[8] = _createElementVNode("i", {
              class: "fa-solid fa-crown",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createElementVNode("span", null, [
              _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.012')), 1 /* TEXT */),
              _createElementVNode("strong", null, _toDisplayString(_ctx.completedChallengeCount) + " / " + _toDisplayString(_ctx.challengeCount), 1 /* TEXT */)
            ])
          ])
        ], 8 /* PROPS */, _hoisted_14))
      : _createCommentVNode("v-if", true),
    (!_ctx.statisticsMode)
      ? (_openBlock(), _createElementBlock("section", {
          key: 1,
          class: "achievements-overall-progress",
          "aria-label": _ctx.t('engine.views.achievements.008')
        }, [
          _createElementVNode("div", _hoisted_20, [
            _createElementVNode("span", null, [
              _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.009')), 1 /* TEXT */),
              _createElementVNode("strong", null, _toDisplayString(_ctx.summary.completedCount) + " / " + _toDisplayString(_ctx.summary.trackedCount), 1 /* TEXT */)
            ]),
            _createElementVNode("b", null, _toDisplayString(_ctx.completionPercent) + "%", 1 /* TEXT */)
          ]),
          _createElementVNode("span", _hoisted_21, [
            _createElementVNode("i", {
              style: _normalizeStyle({ width: `${_ctx.completionPercent}%` })
            }, null, 4 /* STYLE */)
          ])
        ], 8 /* PROPS */, _hoisted_19))
      : _createCommentVNode("v-if", true),
    (!_ctx.statisticsMode)
      ? (_openBlock(), _createElementBlock("div", _hoisted_22, [
          _createElementVNode("main", _hoisted_23, [
            _createElementVNode("div", _hoisted_24, [
              _createElementVNode("div", _hoisted_25, [
                _createElementVNode("span", null, [
                  _createElementVNode("small", null, _toDisplayString(_ctx.playerName), 1 /* TEXT */),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.013')), 1 /* TEXT */)
                ]),
                _createElementVNode("em", null, _toDisplayString(_ctx.t('engine.views.achievements.014', [_ctx.filteredItems.length, _ctx.items.length])), 1 /* TEXT */)
              ]),
              _createElementVNode("div", _hoisted_26, [
                _createElementVNode("label", _hoisted_27, [
                  _cache[9] || (_cache[9] = _createElementVNode("i", {
                    class: "fa-solid fa-magnifying-glass",
                    "aria-hidden": "true"
                  }, null, -1 /* CACHED */)),
                  _withDirectives(_createElementVNode("input", {
                    "onUpdate:modelValue": $event => ((_ctx.search) = $event),
                    type: "search",
                    placeholder: _ctx.t('engine.views.achievements.015')
                  }, null, 8 /* PROPS */, _hoisted_28), [
                    [_vModelText, _ctx.search]
                  ])
                ]),
                _withDirectives(_createElementVNode("select", {
                  "onUpdate:modelValue": $event => ((_ctx.server) = $event),
                  "aria-label": _ctx.t('engine.views.achievements.016')
                }, [
                  _createElementVNode("option", _hoisted_30, _toDisplayString(_ctx.t('engine.views.achievements.016')), 1 /* TEXT */),
                  (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.servers, (value) => {
                    return (_openBlock(), _createElementBlock("option", {
                      key: value,
                      value: value
                    }, _toDisplayString(value), 9 /* TEXT, PROPS */, _hoisted_31))
                  }), 128 /* KEYED_FRAGMENT */))
                ], 8 /* PROPS */, _hoisted_29), [
                  [_vModelSelect, _ctx.server]
                ]),
                _withDirectives(_createElementVNode("select", {
                  "onUpdate:modelValue": $event => ((_ctx.category) = $event),
                  "aria-label": _ctx.t('engine.views.achievements.017')
                }, [
                  _createElementVNode("option", _hoisted_33, _toDisplayString(_ctx.t('engine.views.achievements.017')), 1 /* TEXT */),
                  (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.categories, (value) => {
                    return (_openBlock(), _createElementBlock("option", {
                      key: value,
                      value: value
                    }, _toDisplayString(value), 9 /* TEXT, PROPS */, _hoisted_34))
                  }), 128 /* KEYED_FRAGMENT */))
                ], 8 /* PROPS */, _hoisted_32), [
                  [_vModelSelect, _ctx.category]
                ])
              ]),
              _createElementVNode("div", {
                class: "achievements-status-tabs",
                role: "group",
                "aria-label": _ctx.t('engine.views.achievements.019')
              }, [
                _createElementVNode("button", {
                  type: "button",
                  class: _normalizeClass({ 'is-active': _ctx.status === 'all' }),
                  onClick: $event => (_ctx.status = 'all')
                }, _toDisplayString(_ctx.t('engine.views.achievements.020')), 11 /* TEXT, CLASS, PROPS */, _hoisted_36),
                _createElementVNode("button", {
                  type: "button",
                  class: _normalizeClass({ 'is-active': _ctx.status === 'completed' }),
                  onClick: $event => (_ctx.status = 'completed')
                }, _toDisplayString(_ctx.t('engine.views.achievements.021')), 11 /* TEXT, CLASS, PROPS */, _hoisted_37),
                _createElementVNode("button", {
                  type: "button",
                  class: _normalizeClass({ 'is-active': _ctx.status === 'locked' }),
                  onClick: $event => (_ctx.status = 'locked')
                }, _toDisplayString(_ctx.t('engine.views.achievements.022')), 11 /* TEXT, CLASS, PROPS */, _hoisted_38),
                _createElementVNode("button", {
                  type: "button",
                  class: _normalizeClass({ 'is-active': _ctx.status === 'challenge' }),
                  onClick: $event => (_ctx.status = 'challenge')
                }, _toDisplayString(_ctx.t('engine.views.achievements.023')), 11 /* TEXT, CLASS, PROPS */, _hoisted_39)
              ], 8 /* PROPS */, _hoisted_35)
            ]),
            (_ctx.loading)
              ? (_openBlock(), _createElementBlock("div", _hoisted_40, [
                  _cache[10] || (_cache[10] = _createElementVNode("i", {
                    class: "fa-solid fa-spinner achievements-spin",
                    "aria-hidden": "true"
                  }, null, -1 /* CACHED */)),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.024')), 1 /* TEXT */),
                  _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.025')), 1 /* TEXT */)
                ]))
              : (_ctx.error)
                ? (_openBlock(), _createElementBlock("div", _hoisted_41, [
                    _cache[11] || (_cache[11] = _createElementVNode("i", {
                      class: "fa-solid fa-triangle-exclamation",
                      "aria-hidden": "true"
                    }, null, -1 /* CACHED */)),
                    _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.018')), 1 /* TEXT */),
                    _createElementVNode("span", null, _toDisplayString(_ctx.error), 1 /* TEXT */),
                    _createElementVNode("button", {
                      class: "button button--ghost",
                      type: "button",
                      onClick: $event => (_ctx.refresh())
                    }, _toDisplayString(_ctx.t('engine.views.achievements.026')), 9 /* TEXT, PROPS */, _hoisted_42)
                  ]))
                : (_ctx.items.length === 0)
                  ? (_openBlock(), _createElementBlock("div", _hoisted_43, [
                      _cache[12] || (_cache[12] = _createElementVNode("i", {
                        class: "fa-solid fa-medal",
                        "aria-hidden": "true"
                      }, null, -1 /* CACHED */)),
                      _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.027')), 1 /* TEXT */),
                      _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.028')), 1 /* TEXT */)
                    ]))
                  : (_ctx.filteredItems.length === 0)
                    ? (_openBlock(), _createElementBlock("div", _hoisted_44, [
                        _cache[13] || (_cache[13] = _createElementVNode("i", {
                          class: "fa-solid fa-filter-circle-xmark",
                          "aria-hidden": "true"
                        }, null, -1 /* CACHED */)),
                        _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.029')), 1 /* TEXT */),
                        _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.030')), 1 /* TEXT */),
                        _createElementVNode("button", {
                          class: "button button--ghost",
                          type: "button",
                          onClick: _ctx.resetFilters
                        }, _toDisplayString(_ctx.t('engine.views.achievements.032')), 9 /* TEXT, PROPS */, _hoisted_45)
                      ]))
                    : (_openBlock(), _createElementBlock("div", _hoisted_46, [
                        (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.filteredItems, (item) => {
                          return (_openBlock(), _createElementBlock("article", {
                            key: `${item.serverId}:${item.achievementKey}`,
                            class: _normalizeClass(["achievement-tile", {
              'is-completed': item.completed,
              'is-challenge': item.frameType === 'challenge',
              'is-locked': !item.completed,
            }])
                          }, [
                            _createElementVNode("div", _hoisted_47, [
                              _createElementVNode("span", _hoisted_48, [
                                _createElementVNode("img", {
                                  src: item.iconDataUrl,
                                  alt: "",
                                  loading: "lazy",
                                  decoding: "async"
                                }, null, 8 /* PROPS */, _hoisted_49),
                                _createElementVNode("i", {
                                  class: _normalizeClass(item.completed ? 'fa-solid fa-check' : 'fa-solid fa-lock'),
                                  "aria-hidden": "true"
                                }, null, 2 /* CLASS */)
                              ]),
                              _createElementVNode("div", _hoisted_50, [
                                _createElementVNode("span", _hoisted_51, [
                                  _createElementVNode("i", {
                                    class: _normalizeClass(item.completed ? 'fa-solid fa-circle-check' : 'fa-solid fa-lock'),
                                    "aria-hidden": "true"
                                  }, null, 2 /* CLASS */),
                                  _createTextVNode(" " + _toDisplayString(item.completed ? _ctx.t('engine.views.achievements.021') : _ctx.t('engine.views.achievements.022')), 1 /* TEXT */)
                                ]),
                                _createElementVNode("span", _hoisted_52, "+" + _toDisplayString(item.points), 1 /* TEXT */)
                              ])
                            ]),
                            _createElementVNode("div", _hoisted_53, [
                              _createElementVNode("small", null, _toDisplayString(item.category) + " · " + _toDisplayString(item.serverId), 1 /* TEXT */),
                              _createElementVNode("h2", null, _toDisplayString(item.title), 1 /* TEXT */),
                              _createElementVNode("p", null, _toDisplayString(item.description || _ctx.t('engine.views.achievements.033')), 1 /* TEXT */)
                            ]),
                            _createElementVNode("div", _hoisted_54, [
                              _createElementVNode("span", null, [
                                _createElementVNode("i", {
                                  style: _normalizeStyle({ width: `${_ctx.progressPercent(item)}%` })
                                }, null, 4 /* STYLE */)
                              ]),
                              _createElementVNode("small", null, _toDisplayString(item.progress) + " / " + _toDisplayString(item.target), 1 /* TEXT */)
                            ]),
                            _createElementVNode("footer", null, [
                              _createElementVNode("span", null, [
                                _createElementVNode("i", {
                                  class: _normalizeClass(item.frameType === 'challenge' ? 'fa-solid fa-crown' : 'fa-solid fa-medal'),
                                  "aria-hidden": "true"
                                }, null, 2 /* CLASS */),
                                _createTextVNode(" " + _toDisplayString(item.frameType === 'challenge' ? _ctx.t('engine.views.achievements.023') : _ctx.t('engine.views.achievements.035')), 1 /* TEXT */)
                              ]),
                              _createElementVNode("time", null, _toDisplayString(_ctx.achievementDate(item)), 1 /* TEXT */)
                            ])
                          ], 2 /* CLASS */))
                        }), 128 /* KEYED_FRAGMENT */))
                      ]))
          ]),
          _createElementVNode("aside", _hoisted_55, [
            _createElementVNode("section", _hoisted_56, [
              _createElementVNode("header", null, [
                _cache[14] || (_cache[14] = _createElementVNode("i", {
                  class: "fa-solid fa-clock-rotate-left",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("span", null, [
                  _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.036')), 1 /* TEXT */),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.037')), 1 /* TEXT */)
                ])
              ]),
              (_ctx.recentAchievements.length === 0)
                ? (_openBlock(), _createElementBlock("p", _hoisted_57, _toDisplayString(_ctx.t('engine.views.achievements.038')), 1 /* TEXT */))
                : (_openBlock(), _createElementBlock("ol", _hoisted_58, [
                    (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.recentAchievements, (item) => {
                      return (_openBlock(), _createElementBlock("li", {
                        key: `recent:${item.serverId}:${item.achievementKey}`
                      }, [
                        _createElementVNode("img", {
                          src: item.iconDataUrl,
                          alt: "",
                          loading: "lazy"
                        }, null, 8 /* PROPS */, _hoisted_59),
                        _createElementVNode("span", null, [
                          _createElementVNode("strong", null, _toDisplayString(item.title), 1 /* TEXT */),
                          _createElementVNode("small", null, _toDisplayString(_ctx.achievementDate(item)), 1 /* TEXT */)
                        ]),
                        _createElementVNode("b", null, "+" + _toDisplayString(item.points), 1 /* TEXT */)
                      ]))
                    }), 128 /* KEYED_FRAGMENT */))
                  ]))
            ])
          ])
        ]))
      : (_openBlock(), _createBlock(_Suspense, { key: 3 }, {
          fallback: _withCtx(() => [
            _createElementVNode("div", _hoisted_60, [
              _cache[15] || (_cache[15] = _createElementVNode("i", {
                class: "fa-solid fa-spinner achievements-spin",
                "aria-hidden": "true"
              }, null, -1 /* CACHED */)),
              _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.050')), 1 /* TEXT */),
              _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.051')), 1 /* TEXT */)
            ])
          ]),
          default: _withCtx(() => [
            _createVNode(_component_AchievementStatisticsTree)
          ]),
          _: 1 /* STABLE */
        }))
  ]))
}
export const templateId = "achievements"
export const sourceHash = "2cb19213001748018af0573f6d2af00fe5938d7da85d144f7c4f85ba387f4266"
