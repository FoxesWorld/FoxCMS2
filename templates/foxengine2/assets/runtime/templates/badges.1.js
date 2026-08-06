/* fox-runtime-template id=badges sha256=f6bd91383e3f4cc100f00b96972b09a3caf1fc85c084e44399a5c30e818a957f */
import { createElementVNode as _createElementVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, toDisplayString as _toDisplayString, normalizeClass as _normalizeClass, withModifiers as _withModifiers, createTextVNode as _createTextVNode, vModelText as _vModelText, withDirectives as _withDirectives, renderList as _renderList, Fragment as _Fragment } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = {
  key: 0,
  class: "content-skeleton"
}
const _hoisted_2 = {
  key: 1,
  class: "content-surface badges-directory"
}
const _hoisted_3 = { class: "badges-directory__header" }
const _hoisted_4 = { class: "eyebrow" }
const _hoisted_5 = { class: "lead" }
const _hoisted_6 = { class: "badges-directory__summary" }
const _hoisted_7 = { class: "badge-claim-panel__intro" }
const _hoisted_8 = { class: "eyebrow" }
const _hoisted_9 = { key: 0 }
const _hoisted_10 = { key: 1 }
const _hoisted_11 = ["onSubmit"]
const _hoisted_12 = { class: "visually-hidden" }
const _hoisted_13 = ["value", "disabled", "onInput"]
const _hoisted_14 = ["disabled"]
const _hoisted_15 = {
  key: 1,
  class: "badge-claim-result"
}
const _hoisted_16 = { class: "badge-claim-result__image" }
const _hoisted_17 = ["src", "alt"]
const _hoisted_18 = {
  key: 1,
  class: "fa-solid fa-award",
  "aria-hidden": "true"
}
const _hoisted_19 = {
  key: 2,
  class: "fa-solid fa-coins",
  "aria-hidden": "true"
}
const _hoisted_20 = { class: "badge-claim-result__components" }
const _hoisted_21 = { key: 0 }
const _hoisted_22 = { key: 1 }
const _hoisted_23 = { class: "badges-directory__search" }
const _hoisted_24 = ["onUpdate:modelValue", "placeholder"]
const _hoisted_25 = {
  key: 0,
  class: "badges-table-wrap"
}
const _hoisted_26 = { class: "badges-table" }
const _hoisted_27 = { scope: "col" }
const _hoisted_28 = { scope: "col" }
const _hoisted_29 = { scope: "col" }
const _hoisted_30 = { scope: "col" }
const _hoisted_31 = { class: "visually-hidden" }
const _hoisted_32 = ["tabindex", "role", "aria-label", "onClick", "onKeydown"]
const _hoisted_33 = ["data-label"]
const _hoisted_34 = { class: "badges-table__image" }
const _hoisted_35 = ["src", "alt"]
const _hoisted_36 = {
  key: 1,
  class: "fa-solid fa-award",
  "aria-hidden": "true"
}
const _hoisted_37 = ["data-label"]
const _hoisted_38 = { key: 0 }
const _hoisted_39 = ["data-label"]
const _hoisted_40 = { class: "badges-table__action" }
const _hoisted_41 = {
  key: 0,
  class: "fa-solid fa-chevron-right",
  "aria-hidden": "true"
}
const _hoisted_42 = { key: 1 }
const _hoisted_43 = {
  key: 1,
  class: "badges-directory__empty"
}
const _hoisted_44 = {
  key: 2,
  class: "system-message system-message--error"
}

export function render(_ctx, _cache) {
  return (_ctx.loading)
    ? (_openBlock(), _createElementBlock("div", _hoisted_1, [...(_cache[0] || (_cache[0] = [
        _createElementVNode("span", null, null, -1 /* CACHED */),
        _createElementVNode("span", null, null, -1 /* CACHED */),
        _createElementVNode("span", null, null, -1 /* CACHED */)
      ]))]))
    : (!_ctx.error)
      ? (_openBlock(), _createElementBlock("article", _hoisted_2, [
          _createElementVNode("header", _hoisted_3, [
            _createElementVNode("div", null, [
              _createElementVNode("span", _hoisted_4, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.001')), 1 /* TEXT */),
              _createElementVNode("h1", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.002')), 1 /* TEXT */),
              _createElementVNode("p", _hoisted_5, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.003')), 1 /* TEXT */)
            ]),
            _createElementVNode("dl", _hoisted_6, [
              _createElementVNode("div", null, [
                _createElementVNode("dt", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.004')), 1 /* TEXT */),
                _createElementVNode("dd", null, _toDisplayString(_ctx.badges.length), 1 /* TEXT */)
              ]),
              _createElementVNode("div", null, [
                _createElementVNode("dt", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.005')), 1 /* TEXT */),
                _createElementVNode("dd", null, _toDisplayString(_ctx.configuredCount), 1 /* TEXT */)
              ])
            ])
          ]),
          _createElementVNode("section", {
            class: _normalizeClass(["badge-claim-panel", { 'is-guest': !_ctx.authenticated }])
          }, [
            _createElementVNode("div", _hoisted_7, [
              _cache[1] || (_cache[1] = _createElementVNode("span", {
                class: "badge-claim-panel__icon",
                "aria-hidden": "true"
              }, [
                _createElementVNode("i", { class: "fa-solid fa-key" })
              ], -1 /* CACHED */)),
              _createElementVNode("div", null, [
                _createElementVNode("span", _hoisted_8, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.006')), 1 /* TEXT */),
                _createElementVNode("h2", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.007')), 1 /* TEXT */),
                (_ctx.authenticated)
                  ? (_openBlock(), _createElementBlock("p", _hoisted_9, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.008')), 1 /* TEXT */))
                  : (_openBlock(), _createElementBlock("p", _hoisted_10, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.009')), 1 /* TEXT */))
              ])
            ]),
            _createElementVNode("form", {
              class: "badge-claim-panel__form",
              onSubmit: _withModifiers($event => (_ctx.emit('claim')), ["prevent"])
            }, [
              _createElementVNode("label", null, [
                _createElementVNode("span", _hoisted_12, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.010')), 1 /* TEXT */),
                _cache[2] || (_cache[2] = _createElementVNode("i", {
                  class: "fa-solid fa-key",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("input", {
                  value: _ctx.claimCode,
                  type: "text",
                  inputmode: "text",
                  autocomplete: "off",
                  spellcheck: "false",
                  placeholder: "fcr_...",
                  disabled: !_ctx.authenticated || _ctx.claiming,
                  onInput: _ctx.updateClaimCode
                }, null, 40 /* PROPS, NEED_HYDRATION */, _hoisted_13)
              ]),
              _createElementVNode("button", {
                class: "button button--primary",
                type: "submit",
                disabled: !_ctx.authenticated || _ctx.claiming || !_ctx.claimCode.trim()
              }, [
                _createElementVNode("i", {
                  class: _normalizeClass(["fa-solid", _ctx.claiming ? 'fa-spinner' : 'fa-coins']),
                  "aria-hidden": "true"
                }, null, 2 /* CLASS */),
                _createElementVNode("span", null, _toDisplayString(_ctx.claiming ? _ctx.t('theme.useroptions.pages.badges.badges.011') : _ctx.t('theme.useroptions.pages.badges.badges.012')), 1 /* TEXT */)
              ], 8 /* PROPS */, _hoisted_14)
            ], 40 /* PROPS, NEED_HYDRATION */, _hoisted_11),
            (_ctx.claimFeedback)
              ? (_openBlock(), _createElementBlock("div", {
                  key: 0,
                  class: _normalizeClass(["badge-claim-feedback", `is-${_ctx.claimFeedback.type}`]),
                  role: "status"
                }, [
                  _createElementVNode("i", {
                    class: _normalizeClass(["fa-solid", _ctx.claimFeedback.type === 'success' ? 'fa-circle-check' : _ctx.claimFeedback.type === 'warning' ? 'fa-circle-exclamation' : 'fa-circle-xmark']),
                    "aria-hidden": "true"
                  }, null, 2 /* CLASS */),
                  _createElementVNode("span", null, _toDisplayString(_ctx.claimFeedback.message), 1 /* TEXT */)
                ], 2 /* CLASS */))
              : _createCommentVNode("v-if", true),
            (_ctx.claimedReward)
              ? (_openBlock(), _createElementBlock("article", _hoisted_15, [
                  _createElementVNode("span", _hoisted_16, [
                    (_ctx.claimedReward.badge?.image)
                      ? (_openBlock(), _createElementBlock("img", {
                          key: 0,
                          src: _ctx.claimedReward.badge.image,
                          alt: _ctx.claimedReward.badge.title,
                          decoding: "async"
                        }, null, 8 /* PROPS */, _hoisted_17))
                      : (_ctx.claimedReward.badge)
                        ? (_openBlock(), _createElementBlock("i", _hoisted_18))
                        : (_openBlock(), _createElementBlock("i", _hoisted_19))
                  ]),
                  _createElementVNode("div", null, [
                    _createElementVNode("small", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.014')), 1 /* TEXT */),
                    _createElementVNode("strong", null, _toDisplayString(_ctx.claimedReward.title), 1 /* TEXT */),
                    _createElementVNode("p", null, _toDisplayString(_ctx.claimedReward.description || _ctx.t('theme.useroptions.pages.badges.badges.015')), 1 /* TEXT */),
                    _createElementVNode("ul", _hoisted_20, [
                      (_ctx.claimedReward.badge)
                        ? (_openBlock(), _createElementBlock("li", _hoisted_21, [
                            _cache[3] || (_cache[3] = _createElementVNode("i", {
                              class: "fa-solid fa-award",
                              "aria-hidden": "true"
                            }, null, -1 /* CACHED */)),
                            _createTextVNode(" " + _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.016')) + _toDisplayString(_ctx.claimedReward.badge.title) + "»", 1 /* TEXT */)
                          ]))
                        : _createCommentVNode("v-if", true),
                      (_ctx.claimedReward.currency)
                        ? (_openBlock(), _createElementBlock("li", _hoisted_22, [
                            _cache[4] || (_cache[4] = _createElementVNode("i", {
                              class: "fa-solid fa-coins",
                              "aria-hidden": "true"
                            }, null, -1 /* CACHED */)),
                            _createTextVNode(" +" + _toDisplayString(_ctx.claimedReward.currency.amount) + " " + _toDisplayString(_ctx.claimedReward.currency.currencyName), 1 /* TEXT */)
                          ]))
                        : _createCommentVNode("v-if", true)
                    ])
                  ])
                ]))
              : _createCommentVNode("v-if", true)
          ], 2 /* CLASS */),
          _createElementVNode("label", _hoisted_23, [
            _cache[5] || (_cache[5] = _createElementVNode("i", {
              class: "fa-solid fa-magnifying-glass",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _withDirectives(_createElementVNode("input", {
              "onUpdate:modelValue": $event => ((_ctx.search) = $event),
              type: "search",
              placeholder: _ctx.t('theme.useroptions.pages.badges.badges.017'),
              autocomplete: "off"
            }, null, 8 /* PROPS */, _hoisted_24), [
              [_vModelText, _ctx.search]
            ]),
            _createElementVNode("span", null, _toDisplayString(_ctx.filteredBadges.length) + " " + _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.018')) + " " + _toDisplayString(_ctx.badges.length), 1 /* TEXT */)
          ]),
          (_ctx.filteredBadges.length)
            ? (_openBlock(), _createElementBlock("div", _hoisted_25, [
                _createElementVNode("table", _hoisted_26, [
                  _createElementVNode("thead", null, [
                    _createElementVNode("tr", null, [
                      _createElementVNode("th", _hoisted_27, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.019')), 1 /* TEXT */),
                      _createElementVNode("th", _hoisted_28, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.020')), 1 /* TEXT */),
                      _createElementVNode("th", _hoisted_29, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.021')), 1 /* TEXT */),
                      _createElementVNode("th", _hoisted_30, [
                        _createElementVNode("span", _hoisted_31, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.022')), 1 /* TEXT */)
                      ])
                    ])
                  ]),
                  _createElementVNode("tbody", null, [
                    (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.filteredBadges, (badge) => {
                      return (_openBlock(), _createElementBlock("tr", {
                        key: badge.databaseId || badge.id,
                        class: _normalizeClass({ 'is-clickable': badge.pageConfigured, 'is-unavailable': !badge.pageConfigured }),
                        tabindex: badge.pageConfigured ? 0 : undefined,
                        role: badge.pageConfigured ? 'link' : undefined,
                        "aria-label": badge.pageConfigured ? _ctx.t('theme.useroptions.pages.badges.badges.023', [badge.title]) : undefined,
                        onClick: $event => (_ctx.openBadge(badge)),
                        onKeydown: $event => (_ctx.handleRowKeydown($event, badge))
                      }, [
                        _createElementVNode("td", {
                          "data-label": _ctx.t('theme.useroptions.pages.badges.badges.019')
                        }, [
                          _createElementVNode("span", _hoisted_34, [
                            (badge.image)
                              ? (_openBlock(), _createElementBlock("img", {
                                  key: 0,
                                  src: badge.image,
                                  alt: badge.title,
                                  loading: "lazy",
                                  decoding: "async"
                                }, null, 8 /* PROPS */, _hoisted_35))
                              : (_openBlock(), _createElementBlock("i", _hoisted_36))
                          ])
                        ], 8 /* PROPS */, _hoisted_33),
                        _createElementVNode("td", {
                          "data-label": _ctx.t('theme.useroptions.pages.badges.badges.020')
                        }, [
                          _createElementVNode("strong", null, _toDisplayString(badge.title), 1 /* TEXT */),
                          (!badge.pageConfigured)
                            ? (_openBlock(), _createElementBlock("small", _hoisted_38, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.024')), 1 /* TEXT */))
                            : _createCommentVNode("v-if", true)
                        ], 8 /* PROPS */, _hoisted_37),
                        _createElementVNode("td", {
                          "data-label": _ctx.t('theme.useroptions.pages.badges.badges.021')
                        }, [
                          _createElementVNode("p", null, _toDisplayString(badge.description || _ctx.t('theme.useroptions.pages.badges.badges.025')), 1 /* TEXT */)
                        ], 8 /* PROPS */, _hoisted_39),
                        _createElementVNode("td", _hoisted_40, [
                          (badge.pageConfigured)
                            ? (_openBlock(), _createElementBlock("i", _hoisted_41))
                            : (_openBlock(), _createElementBlock("span", _hoisted_42, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.026')), 1 /* TEXT */))
                        ])
                      ], 42 /* CLASS, PROPS, NEED_HYDRATION */, _hoisted_32))
                    }), 128 /* KEYED_FRAGMENT */))
                  ])
                ])
              ]))
            : (_openBlock(), _createElementBlock("div", _hoisted_43, [
                _cache[6] || (_cache[6] = _createElementVNode("i", {
                  class: "fa-solid fa-magnifying-glass",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.027')), 1 /* TEXT */),
                _createElementVNode("p", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.028')), 1 /* TEXT */)
              ]))
        ]))
      : (_openBlock(), _createElementBlock("div", _hoisted_44, [
          _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.029')), 1 /* TEXT */),
          _createElementVNode("p", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badges.030')), 1 /* TEXT */)
        ]))
}
export const templateId = "badges"
export const sourceHash = "f6bd91383e3f4cc100f00b96972b09a3caf1fc85c084e44399a5c30e818a957f"
