/* fox-runtime-template id=achievements sha256=b9357809550fcf51432c3a3d757e813d6f81a0e401f675a845e05eb9c25f8b5f */
import { createElementVNode as _createElementVNode, toDisplayString as _toDisplayString, createTextVNode as _createTextVNode, vModelText as _vModelText, withDirectives as _withDirectives, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, withModifiers as _withModifiers, normalizeStyle as _normalizeStyle, normalizeClass as _normalizeClass, Fragment as _Fragment, renderList as _renderList, vModelSelect as _vModelSelect, resolveComponent as _resolveComponent, createVNode as _createVNode, Suspense as _Suspense, withCtx as _withCtx, createBlock as _createBlock } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

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
const _hoisted_22 = ["aria-label"]
const _hoisted_23 = { class: "achievement-economy__header" }
const _hoisted_24 = { class: "eyebrow" }
const _hoisted_25 = {
  key: 0,
  class: "achievement-economy__state",
  "aria-live": "polite"
}
const _hoisted_26 = { class: "achievement-economy__metrics" }
const _hoisted_27 = ["onSubmit"]
const _hoisted_28 = ["onUpdate:modelValue", "min", "max", "step", "disabled"]
const _hoisted_29 = {
  class: "achievement-economy__conversion",
  "aria-live": "polite"
}
const _hoisted_30 = ["disabled"]
const _hoisted_31 = ["disabled", "onClick"]
const _hoisted_32 = {
  key: 0,
  class: "achievement-economy__notice achievement-economy__notice--warning"
}
const _hoisted_33 = {
  key: 1,
  class: "achievement-economy__notice"
}
const _hoisted_34 = {
  key: 2,
  class: "achievement-economy__notice achievement-economy__notice--success"
}
const _hoisted_35 = {
  key: 2,
  class: "achievement-economy__notice achievement-economy__notice--error",
  role: "alert"
}
const _hoisted_36 = ["onClick"]
const _hoisted_37 = { class: "achievements-catalog" }
const _hoisted_38 = ["aria-label"]
const _hoisted_39 = { class: "achievements-server-context__identity" }
const _hoisted_40 = { class: "achievements-server-context__select" }
const _hoisted_41 = ["onUpdate:modelValue", "aria-label", "disabled"]
const _hoisted_42 = ["value"]
const _hoisted_43 = { class: "achievements-toolbar" }
const _hoisted_44 = { class: "achievements-toolbar__heading" }
const _hoisted_45 = { key: 0 }
const _hoisted_46 = { key: 1 }
const _hoisted_47 = { class: "achievements-toolbar__controls achievements-toolbar__controls--catalog" }
const _hoisted_48 = ["onClick"]
const _hoisted_49 = { class: "achievements-search" }
const _hoisted_50 = ["onUpdate:modelValue", "placeholder"]
const _hoisted_51 = ["aria-label"]
const _hoisted_52 = ["onClick"]
const _hoisted_53 = ["onClick"]
const _hoisted_54 = ["onClick"]
const _hoisted_55 = ["onClick"]
const _hoisted_56 = {
  key: 0,
  class: "achievements-state",
  "aria-live": "polite"
}
const _hoisted_57 = {
  key: 1,
  class: "achievements-state achievements-state--error",
  role: "alert"
}
const _hoisted_58 = ["onClick"]
const _hoisted_59 = {
  key: 2,
  class: "achievements-state"
}
const _hoisted_60 = {
  key: 3,
  class: "achievements-state"
}
const _hoisted_61 = ["onClick"]
const _hoisted_62 = ["aria-label"]
const _hoisted_63 = ["onClick"]
const _hoisted_64 = ["title", "aria-label"]
const _hoisted_65 = { class: "achievement-category-card__icon" }
const _hoisted_66 = ["src", "title"]
const _hoisted_67 = {
  key: 1,
  class: "fa-solid fa-trophy",
  "aria-hidden": "true"
}
const _hoisted_68 = { class: "achievement-category-card__body" }
const _hoisted_69 = { class: "achievement-category-card__meta" }
const _hoisted_70 = { class: "achievement-category-card__progress-row" }
const _hoisted_71 = { class: "achievement-category-card__action" }
const _hoisted_72 = {
  key: 5,
  class: "achievements-state"
}
const _hoisted_73 = ["onClick"]
const _hoisted_74 = {
  key: 6,
  class: "achievements-grid"
}
const _hoisted_75 = { class: "achievement-tile__top" }
const _hoisted_76 = { class: "achievement-tile__icon-wrap" }
const _hoisted_77 = ["src"]
const _hoisted_78 = { class: "achievement-tile__badges" }
const _hoisted_79 = {
  key: 0,
  class: "achievement-tile__state"
}
const _hoisted_80 = { class: "achievement-tile__points" }
const _hoisted_81 = { class: "achievement-tile__body" }
const _hoisted_82 = {
  key: 0,
  class: "achievement-tile__progress"
}
const _hoisted_83 = {
  key: 0,
  class: "achievements-sidebar"
}
const _hoisted_84 = { class: "achievements-sidebar__panel" }
const _hoisted_85 = {
  key: 0,
  class: "achievements-sidebar__empty"
}
const _hoisted_86 = {
  key: 1,
  class: "achievements-recent"
}
const _hoisted_87 = ["src"]
const _hoisted_88 = {
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
        _createElementVNode("h1", _hoisted_4, _toDisplayString(_ctx.t('engine.views.achievements.013')), 1 /* TEXT */),
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
      (!_ctx.statisticsMode && _ctx.hasPlayerContext)
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
    (!_ctx.statisticsMode && _ctx.hasPlayerContext)
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
    (!_ctx.statisticsMode && _ctx.hasPlayerContext)
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
    (_ctx.isOwnAchievements)
      ? (_openBlock(), _createElementBlock("section", {
          key: 2,
          class: "achievement-economy",
          "aria-label": _ctx.t('engine.views.achievements.071')
        }, [
          _createElementVNode("header", _hoisted_23, [
            _cache[9] || (_cache[9] = _createElementVNode("span", {
              class: "achievement-economy__icon",
              "aria-hidden": "true"
            }, [
              _createElementVNode("i", { class: "fa-solid fa-coins" })
            ], -1 /* CACHED */)),
            _createElementVNode("div", null, [
              _createElementVNode("span", _hoisted_24, _toDisplayString(_ctx.t('engine.views.achievements.071')), 1 /* TEXT */),
              _createElementVNode("h2", null, _toDisplayString(_ctx.t('engine.views.achievements.072')), 1 /* TEXT */),
              _createElementVNode("p", null, _toDisplayString(_ctx.t('engine.views.achievements.073')), 1 /* TEXT */)
            ])
          ]),
          (_ctx.economyLoading)
            ? (_openBlock(), _createElementBlock("div", _hoisted_25, [
                _cache[10] || (_cache[10] = _createElementVNode("i", {
                  class: "fa-solid fa-spinner achievements-spin",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.074')), 1 /* TEXT */)
              ]))
            : (_ctx.economy)
              ? (_openBlock(), _createElementBlock(_Fragment, { key: 1 }, [
                  _createElementVNode("div", _hoisted_26, [
                    _createElementVNode("article", null, [
                      _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.075')), 1 /* TEXT */),
                      _createElementVNode("strong", null, _toDisplayString(_ctx.economy.availablePoints), 1 /* TEXT */),
                      _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.076')), 1 /* TEXT */)
                    ]),
                    _createElementVNode("article", null, [
                      _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.077')), 1 /* TEXT */),
                      _createElementVNode("strong", null, _toDisplayString(_ctx.economy.pointsPerUnit) + " : 1", 1 /* TEXT */),
                      _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.090', [_ctx.economy.pointsPerUnit])), 1 /* TEXT */)
                    ]),
                    _createElementVNode("article", null, [
                      _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.078')), 1 /* TEXT */),
                      _createElementVNode("strong", null, _toDisplayString(_ctx.economy.unitBalance) + " " + _toDisplayString(_ctx.economy.currencySymbol), 1 /* TEXT */),
                      _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.079', [_ctx.economy.lifetimeUnits])), 1 /* TEXT */)
                    ])
                  ]),
                  _createElementVNode("form", {
                    class: "achievement-economy__exchange",
                    onSubmit: _withModifiers(_ctx.exchangeMyAchievementPoints, ["prevent"])
                  }, [
                    _createElementVNode("label", null, [
                      _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.080')), 1 /* TEXT */),
                      _withDirectives(_createElementVNode("input", {
                        "onUpdate:modelValue": $event => ((_ctx.exchangePointsInput) = $event),
                        type: "number",
                        inputmode: "numeric",
                        min: _ctx.economy.minimumPoints,
                        max: _ctx.economy.maxExchangeablePoints,
                        step: _ctx.economy.pointsPerUnit,
                        disabled: !_ctx.economy.enabled || _ctx.exchangeBusy || _ctx.economy.maxExchangeablePoints < _ctx.economy.minimumPoints
                      }, null, 8 /* PROPS */, _hoisted_28), [
                        [
                          _vModelText,
                          _ctx.exchangePointsInput,
                          void 0,
                          { number: true }
                        ]
                      ])
                    ]),
                    _createElementVNode("span", _hoisted_29, [
                      _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.081')), 1 /* TEXT */),
                      _createElementVNode("strong", null, _toDisplayString(_ctx.exchangePreviewUnits) + " " + _toDisplayString(_ctx.economy.currencySymbol), 1 /* TEXT */)
                    ]),
                    _createElementVNode("button", {
                      class: "button button--primary achievement-economy__submit",
                      type: "submit",
                      disabled: !_ctx.canExchangePoints
                    }, [
                      _cache[11] || (_cache[11] = _createElementVNode("i", {
                        class: "fa-solid fa-arrow-right-arrow-left",
                        "aria-hidden": "true"
                      }, null, -1 /* CACHED */)),
                      _createElementVNode("span", null, _toDisplayString(_ctx.exchangeBusy ? _ctx.t('engine.views.achievements.085') : _ctx.t('engine.views.achievements.086')), 1 /* TEXT */)
                    ], 8 /* PROPS */, _hoisted_30),
                    (_ctx.economy.maxExchangeablePoints > _ctx.economy.minimumPoints)
                      ? (_openBlock(), _createElementBlock("button", {
                          key: 0,
                          class: "button button--ghost",
                          type: "button",
                          disabled: _ctx.exchangeBusy || !_ctx.economy.enabled,
                          onClick: _ctx.exchangeAllAchievementPoints
                        }, _toDisplayString(_ctx.t('engine.views.achievements.087')), 9 /* TEXT, PROPS */, _hoisted_31))
                      : _createCommentVNode("v-if", true)
                  ], 40 /* PROPS, NEED_HYDRATION */, _hoisted_27),
                  (!_ctx.economy.enabled)
                    ? (_openBlock(), _createElementBlock("p", _hoisted_32, [
                        _cache[12] || (_cache[12] = _createElementVNode("i", {
                          class: "fa-solid fa-circle-pause",
                          "aria-hidden": "true"
                        }, null, -1 /* CACHED */)),
                        _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.088')), 1 /* TEXT */)
                      ]))
                    : (_ctx.economy.availablePoints < _ctx.economy.minimumPoints)
                      ? (_openBlock(), _createElementBlock("p", _hoisted_33, [
                          _cache[13] || (_cache[13] = _createElementVNode("i", {
                            class: "fa-solid fa-circle-info",
                            "aria-hidden": "true"
                          }, null, -1 /* CACHED */)),
                          _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.089', [_ctx.economy.minimumPoints])), 1 /* TEXT */)
                        ]))
                      : _createCommentVNode("v-if", true),
                  (_ctx.exchangeMessage)
                    ? (_openBlock(), _createElementBlock("p", _hoisted_34, [
                        _cache[14] || (_cache[14] = _createElementVNode("i", {
                          class: "fa-solid fa-circle-check",
                          "aria-hidden": "true"
                        }, null, -1 /* CACHED */)),
                        _createTextVNode(" " + _toDisplayString(_ctx.exchangeMessage), 1 /* TEXT */)
                      ]))
                    : _createCommentVNode("v-if", true)
                ], 64 /* STABLE_FRAGMENT */))
              : _createCommentVNode("v-if", true),
          (_ctx.economyError)
            ? (_openBlock(), _createElementBlock("p", _hoisted_35, [
                _cache[15] || (_cache[15] = _createElementVNode("i", {
                  class: "fa-solid fa-triangle-exclamation",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("span", null, _toDisplayString(_ctx.economyError), 1 /* TEXT */),
                _createElementVNode("button", {
                  class: "button button--ghost",
                  type: "button",
                  onClick: _ctx.refreshAchievementEconomy
                }, _toDisplayString(_ctx.t('engine.views.achievements.026')), 9 /* TEXT, PROPS */, _hoisted_36)
              ]))
            : _createCommentVNode("v-if", true)
        ], 8 /* PROPS */, _hoisted_22))
      : _createCommentVNode("v-if", true),
    (!_ctx.statisticsMode)
      ? (_openBlock(), _createElementBlock("div", {
          key: 3,
          class: _normalizeClass(["achievements-workspace", { 'achievements-workspace--catalog-only': !_ctx.hasPlayerContext }])
        }, [
          _createElementVNode("main", _hoisted_37, [
            _createElementVNode("section", {
              class: "achievements-server-context",
              "aria-label": _ctx.t('engine.views.achievements.061')
            }, [
              _createElementVNode("span", _hoisted_39, [
                _cache[16] || (_cache[16] = _createElementVNode("i", {
                  class: "fa-solid fa-server",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("span", null, [
                  _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.013')), 1 /* TEXT */),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.server || '—'), 1 /* TEXT */)
                ])
              ]),
              _createElementVNode("label", _hoisted_40, [
                _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.061')), 1 /* TEXT */),
                _withDirectives(_createElementVNode("select", {
                  "onUpdate:modelValue": $event => ((_ctx.server) = $event),
                  "aria-label": _ctx.t('engine.views.achievements.061'),
                  disabled: _ctx.servers.length <= 1
                }, [
                  (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.servers, (value) => {
                    return (_openBlock(), _createElementBlock("option", {
                      key: value,
                      value: value
                    }, _toDisplayString(value), 9 /* TEXT, PROPS */, _hoisted_42))
                  }), 128 /* KEYED_FRAGMENT */))
                ], 8 /* PROPS */, _hoisted_41), [
                  [_vModelSelect, _ctx.server]
                ])
              ])
            ], 8 /* PROPS */, _hoisted_38),
            _createElementVNode("div", _hoisted_43, [
              _createElementVNode("div", _hoisted_44, [
                _createElementVNode("span", null, [
                  _createElementVNode("small", null, _toDisplayString(_ctx.hasPlayerContext ? _ctx.playerName : _ctx.server), 1 /* TEXT */),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.categoryIndex ? _ctx.t('engine.views.achievements.064') : _ctx.activeCategorySummary?.label || _ctx.t('engine.views.achievements.013')), 1 /* TEXT */)
                ]),
                (_ctx.categoryIndex)
                  ? (_openBlock(), _createElementBlock("em", _hoisted_45, _toDisplayString(_ctx.t('engine.views.achievements.069', [_ctx.visibleCategorySummaries.length])), 1 /* TEXT */))
                  : (_openBlock(), _createElementBlock("em", _hoisted_46, _toDisplayString(_ctx.t('engine.views.achievements.014', [_ctx.filteredItems.length, _ctx.activeCategorySummary?.totalCount || _ctx.selectedItems.length])), 1 /* TEXT */))
              ]),
              _createElementVNode("div", _hoisted_47, [
                (_ctx.activeCategorySummary)
                  ? (_openBlock(), _createElementBlock("button", {
                      key: 0,
                      class: "button button--ghost achievements-category-back",
                      type: "button",
                      onClick: _ctx.closeCategory
                    }, [
                      _cache[17] || (_cache[17] = _createElementVNode("i", {
                        class: "fa-solid fa-arrow-left",
                        "aria-hidden": "true"
                      }, null, -1 /* CACHED */)),
                      _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.067')), 1 /* TEXT */)
                    ], 8 /* PROPS */, _hoisted_48))
                  : _createCommentVNode("v-if", true),
                _createElementVNode("label", _hoisted_49, [
                  _cache[18] || (_cache[18] = _createElementVNode("i", {
                    class: "fa-solid fa-magnifying-glass",
                    "aria-hidden": "true"
                  }, null, -1 /* CACHED */)),
                  _withDirectives(_createElementVNode("input", {
                    "onUpdate:modelValue": $event => ((_ctx.search) = $event),
                    type: "search",
                    placeholder: _ctx.t('engine.views.achievements.015')
                  }, null, 8 /* PROPS */, _hoisted_50), [
                    [_vModelText, _ctx.search]
                  ])
                ])
              ]),
              (_ctx.hasPlayerContext)
                ? (_openBlock(), _createElementBlock("div", {
                    key: 0,
                    class: "achievements-status-tabs",
                    role: "group",
                    "aria-label": _ctx.t('engine.views.achievements.019')
                  }, [
                    _createElementVNode("button", {
                      type: "button",
                      class: _normalizeClass({ 'is-active': _ctx.status === 'all' }),
                      onClick: $event => (_ctx.status = 'all')
                    }, _toDisplayString(_ctx.t('engine.views.achievements.020')), 11 /* TEXT, CLASS, PROPS */, _hoisted_52),
                    _createElementVNode("button", {
                      type: "button",
                      class: _normalizeClass({ 'is-active': _ctx.status === 'completed' }),
                      onClick: $event => (_ctx.status = 'completed')
                    }, _toDisplayString(_ctx.t('engine.views.achievements.021')), 11 /* TEXT, CLASS, PROPS */, _hoisted_53),
                    _createElementVNode("button", {
                      type: "button",
                      class: _normalizeClass({ 'is-active': _ctx.status === 'locked' }),
                      onClick: $event => (_ctx.status = 'locked')
                    }, _toDisplayString(_ctx.t('engine.views.achievements.022')), 11 /* TEXT, CLASS, PROPS */, _hoisted_54),
                    _createElementVNode("button", {
                      type: "button",
                      class: _normalizeClass({ 'is-active': _ctx.status === 'challenge' }),
                      onClick: $event => (_ctx.status = 'challenge')
                    }, _toDisplayString(_ctx.t('engine.views.achievements.023')), 11 /* TEXT, CLASS, PROPS */, _hoisted_55)
                  ], 8 /* PROPS */, _hoisted_51))
                : _createCommentVNode("v-if", true)
            ]),
            (_ctx.loading)
              ? (_openBlock(), _createElementBlock("div", _hoisted_56, [
                  _cache[19] || (_cache[19] = _createElementVNode("i", {
                    class: "fa-solid fa-spinner achievements-spin",
                    "aria-hidden": "true"
                  }, null, -1 /* CACHED */)),
                  _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.024')), 1 /* TEXT */),
                  _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.025')), 1 /* TEXT */)
                ]))
              : (_ctx.error)
                ? (_openBlock(), _createElementBlock("div", _hoisted_57, [
                    _cache[20] || (_cache[20] = _createElementVNode("i", {
                      class: "fa-solid fa-triangle-exclamation",
                      "aria-hidden": "true"
                    }, null, -1 /* CACHED */)),
                    _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.018')), 1 /* TEXT */),
                    _createElementVNode("span", null, _toDisplayString(_ctx.error), 1 /* TEXT */),
                    _createElementVNode("button", {
                      class: "button button--ghost",
                      type: "button",
                      onClick: $event => (_ctx.refresh())
                    }, _toDisplayString(_ctx.t('engine.views.achievements.026')), 9 /* TEXT, PROPS */, _hoisted_58)
                  ]))
                : (_ctx.items.length === 0)
                  ? (_openBlock(), _createElementBlock("div", _hoisted_59, [
                      _cache[21] || (_cache[21] = _createElementVNode("i", {
                        class: "fa-solid fa-medal",
                        "aria-hidden": "true"
                      }, null, -1 /* CACHED */)),
                      _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.027')), 1 /* TEXT */),
                      _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.028')), 1 /* TEXT */)
                    ]))
                  : (_ctx.categoryIndex && _ctx.visibleCategorySummaries.length === 0)
                    ? (_openBlock(), _createElementBlock("div", _hoisted_60, [
                        _cache[22] || (_cache[22] = _createElementVNode("i", {
                          class: "fa-solid fa-filter-circle-xmark",
                          "aria-hidden": "true"
                        }, null, -1 /* CACHED */)),
                        _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.029')), 1 /* TEXT */),
                        _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.030')), 1 /* TEXT */),
                        _createElementVNode("button", {
                          class: "button button--ghost",
                          type: "button",
                          onClick: _ctx.resetFilters
                        }, _toDisplayString(_ctx.t('engine.views.achievements.032')), 9 /* TEXT, PROPS */, _hoisted_61)
                      ]))
                    : (_ctx.categoryIndex)
                      ? (_openBlock(), _createElementBlock("div", {
                          key: 4,
                          class: "achievement-category-grid",
                          "aria-label": _ctx.t('engine.views.achievements.017')
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
                                  }, [...(_cache[23] || (_cache[23] = [
                                    _createElementVNode("i", {
                                      class: "fa-solid fa-check",
                                      "aria-hidden": "true"
                                    }, null, -1 /* CACHED */)
                                  ]))], 8 /* PROPS */, _hoisted_64))
                                : _createCommentVNode("v-if", true),
                              _createElementVNode("span", _hoisted_65, [
                                (entry.iconDataUrl)
                                  ? (_openBlock(), _createElementBlock("img", {
                                      key: 0,
                                      src: entry.iconDataUrl,
                                      title: entry.iconItem || entry.label,
                                      alt: "",
                                      loading: "lazy",
                                      decoding: "async"
                                    }, null, 8 /* PROPS */, _hoisted_66))
                                  : (_openBlock(), _createElementBlock("i", _hoisted_67))
                              ]),
                              _createElementVNode("span", _hoisted_68, [
                                _createElementVNode("span", _hoisted_69, [
                                  _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.064')), 1 /* TEXT */),
                                  _createElementVNode("b", null, _toDisplayString(entry.completedCount) + " / " + _toDisplayString(entry.totalCount), 1 /* TEXT */)
                                ]),
                                _createElementVNode("strong", null, _toDisplayString(entry.label), 1 /* TEXT */),
                                _createElementVNode("span", _hoisted_70, [
                                  _cache[24] || (_cache[24] = _createElementVNode("span", {
                                    class: "achievement-category-card__progress",
                                    "aria-hidden": "true"
                                  }, [
                                    _createElementVNode("i")
                                  ], -1 /* CACHED */)),
                                  _createElementVNode("b", null, _toDisplayString(entry.completionPercent) + "%", 1 /* TEXT */)
                                ]),
                                _createElementVNode("em", null, _toDisplayString(_ctx.t('engine.views.achievements.065', [entry.completedCount, entry.totalCount])), 1 /* TEXT */)
                              ]),
                              _createElementVNode("span", _hoisted_71, [
                                _createTextVNode(_toDisplayString(_ctx.t('engine.views.achievements.066')) + " ", 1 /* TEXT */),
                                _cache[25] || (_cache[25] = _createElementVNode("i", {
                                  class: "fa-solid fa-arrow-right",
                                  "aria-hidden": "true"
                                }, null, -1 /* CACHED */))
                              ])
                            ], 14 /* CLASS, STYLE, PROPS */, _hoisted_63))
                          }), 128 /* KEYED_FRAGMENT */))
                        ], 8 /* PROPS */, _hoisted_62))
                      : (_ctx.filteredItems.length === 0)
                        ? (_openBlock(), _createElementBlock("div", _hoisted_72, [
                            _cache[26] || (_cache[26] = _createElementVNode("i", {
                              class: "fa-solid fa-filter-circle-xmark",
                              "aria-hidden": "true"
                            }, null, -1 /* CACHED */)),
                            _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.029')), 1 /* TEXT */),
                            _createElementVNode("span", null, _toDisplayString(_ctx.t('engine.views.achievements.030')), 1 /* TEXT */),
                            _createElementVNode("button", {
                              class: "button button--ghost",
                              type: "button",
                              onClick: _ctx.resetFilters
                            }, _toDisplayString(_ctx.t('engine.views.achievements.032')), 9 /* TEXT, PROPS */, _hoisted_73)
                          ]))
                        : (_openBlock(), _createElementBlock("div", _hoisted_74, [
                            (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.filteredItems, (item) => {
                              return (_openBlock(), _createElementBlock("article", {
                                key: `${item.serverId}:${item.achievementKey}`,
                                class: _normalizeClass(["achievement-tile", {
              'is-completed': item.completed,
              'is-challenge': item.frameType === 'challenge',
              'is-locked': _ctx.hasPlayerContext && !item.completed,
            }])
                              }, [
                                _createElementVNode("div", _hoisted_75, [
                                  _createElementVNode("span", _hoisted_76, [
                                    _createElementVNode("img", {
                                      src: item.iconDataUrl,
                                      alt: "",
                                      loading: "lazy",
                                      decoding: "async"
                                    }, null, 8 /* PROPS */, _hoisted_77),
                                    _createElementVNode("i", {
                                      class: _normalizeClass(item.completed ? 'fa-solid fa-check' : 'fa-solid fa-lock'),
                                      "aria-hidden": "true"
                                    }, null, 2 /* CLASS */)
                                  ]),
                                  _createElementVNode("div", _hoisted_78, [
                                    (_ctx.hasPlayerContext)
                                      ? (_openBlock(), _createElementBlock("span", _hoisted_79, [
                                          _createElementVNode("i", {
                                            class: _normalizeClass(item.completed ? 'fa-solid fa-circle-check' : 'fa-solid fa-lock'),
                                            "aria-hidden": "true"
                                          }, null, 2 /* CLASS */),
                                          _createTextVNode(" " + _toDisplayString(item.completed ? _ctx.t('engine.views.achievements.021') : _ctx.t('engine.views.achievements.022')), 1 /* TEXT */)
                                        ]))
                                      : _createCommentVNode("v-if", true),
                                    _createElementVNode("span", _hoisted_80, "+" + _toDisplayString(item.points), 1 /* TEXT */)
                                  ])
                                ]),
                                _createElementVNode("div", _hoisted_81, [
                                  _createElementVNode("small", null, _toDisplayString(item.categoryLabel || item.category) + " · " + _toDisplayString(item.serverId), 1 /* TEXT */),
                                  _createElementVNode("h2", null, _toDisplayString(item.title), 1 /* TEXT */),
                                  _createElementVNode("p", null, _toDisplayString(item.description || _ctx.t('engine.views.achievements.033')), 1 /* TEXT */)
                                ]),
                                (_ctx.hasPlayerContext)
                                  ? (_openBlock(), _createElementBlock("div", _hoisted_82, [
                                      _createElementVNode("span", null, [
                                        _createElementVNode("i", {
                                          style: _normalizeStyle({ width: `${_ctx.progressPercent(item)}%` })
                                        }, null, 4 /* STYLE */)
                                      ]),
                                      _createElementVNode("small", null, _toDisplayString(item.progress) + " / " + _toDisplayString(item.target), 1 /* TEXT */)
                                    ]))
                                  : _createCommentVNode("v-if", true),
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
          (_ctx.hasPlayerContext)
            ? (_openBlock(), _createElementBlock("aside", _hoisted_83, [
                _createElementVNode("section", _hoisted_84, [
                  _createElementVNode("header", null, [
                    _cache[27] || (_cache[27] = _createElementVNode("i", {
                      class: "fa-solid fa-clock-rotate-left",
                      "aria-hidden": "true"
                    }, null, -1 /* CACHED */)),
                    _createElementVNode("span", null, [
                      _createElementVNode("small", null, _toDisplayString(_ctx.t('engine.views.achievements.036')), 1 /* TEXT */),
                      _createElementVNode("strong", null, _toDisplayString(_ctx.t('engine.views.achievements.037')), 1 /* TEXT */)
                    ])
                  ]),
                  (_ctx.recentAchievements.length === 0)
                    ? (_openBlock(), _createElementBlock("p", _hoisted_85, _toDisplayString(_ctx.t('engine.views.achievements.038')), 1 /* TEXT */))
                    : (_openBlock(), _createElementBlock("ol", _hoisted_86, [
                        (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.recentAchievements, (item) => {
                          return (_openBlock(), _createElementBlock("li", {
                            key: `recent:${item.serverId}:${item.achievementKey}`
                          }, [
                            _createElementVNode("img", {
                              src: item.iconDataUrl,
                              alt: "",
                              loading: "lazy"
                            }, null, 8 /* PROPS */, _hoisted_87),
                            _createElementVNode("span", null, [
                              _createElementVNode("strong", null, _toDisplayString(item.title), 1 /* TEXT */),
                              _createElementVNode("small", null, _toDisplayString(_ctx.achievementDate(item)), 1 /* TEXT */)
                            ]),
                            _createElementVNode("b", null, "+" + _toDisplayString(item.points), 1 /* TEXT */)
                          ]))
                        }), 128 /* KEYED_FRAGMENT */))
                      ]))
                ])
              ]))
            : _createCommentVNode("v-if", true)
        ], 2 /* CLASS */))
      : (_openBlock(), _createBlock(_Suspense, { key: 4 }, {
          fallback: _withCtx(() => [
            _createElementVNode("div", _hoisted_88, [
              _cache[28] || (_cache[28] = _createElementVNode("i", {
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
export const sourceHash = "b9357809550fcf51432c3a3d757e813d6f81a0e401f675a845e05eb9c25f8b5f"
