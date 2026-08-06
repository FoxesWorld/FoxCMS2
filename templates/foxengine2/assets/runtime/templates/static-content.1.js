/* fox-runtime-template id=static-content sha256=301286afb85d408e19a65cec2572cbd21242e531708744fc0260ab48f6e4930f */
import { createElementVNode as _createElementVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, resolveComponent as _resolveComponent, createVNode as _createVNode, toDisplayString as _toDisplayString, createTextVNode as _createTextVNode, Fragment as _Fragment, normalizeClass as _normalizeClass, Teleport as _Teleport, createBlock as _createBlock } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = {
  key: 0,
  class: "content-skeleton"
}
const _hoisted_2 = {
  class: "rules-badge-claim",
  "aria-labelledby": "rules-badge-title"
}
const _hoisted_3 = {
  class: "rules-badge-claim__mark",
  "aria-hidden": "true"
}
const _hoisted_4 = ["src"]
const _hoisted_5 = {
  key: 1,
  class: "fa-solid fa-award"
}
const _hoisted_6 = { class: "rules-badge-claim__content" }
const _hoisted_7 = { class: "rules-badge-claim__eyebrow" }
const _hoisted_8 = { id: "rules-badge-title" }
const _hoisted_9 = { key: 0 }
const _hoisted_10 = { key: 1 }
const _hoisted_11 = { key: 2 }
const _hoisted_12 = ["disabled", "onClick"]
const _hoisted_13 = { key: 0 }
const _hoisted_14 = { key: 1 }
const _hoisted_15 = { key: 2 }
const _hoisted_16 = { key: 3 }
const _hoisted_17 = ["role"]
const _hoisted_18 = {
  key: 1,
  class: "rules-badge-claim__hint"
}
const _hoisted_19 = {
  key: 2,
  class: "system-message system-message--error"
}

export function render(_ctx, _cache) {
  const _component_StaticPage = _resolveComponent("StaticPage")

  return (_ctx.loading)
    ? (_openBlock(), _createElementBlock("div", _hoisted_1, [...(_cache[0] || (_cache[0] = [
        _createElementVNode("span", null, null, -1 /* CACHED */),
        _createElementVNode("span", null, null, -1 /* CACHED */),
        _createElementVNode("span", null, null, -1 /* CACHED */)
      ]))]))
    : (_ctx.page)
      ? (_openBlock(), _createElementBlock(_Fragment, { key: 1 }, [
          _createVNode(_component_StaticPage, { page: _ctx.page }, null, 8 /* PROPS */, ["page"]),
          (_ctx.pageId === 'rules')
            ? (_openBlock(), _createBlock(_Teleport, {
                key: 0,
                defer: "",
                to: ".static-content-page--rules"
              }, [
                _createElementVNode("section", _hoisted_2, [
                  _createElementVNode("div", _hoisted_3, [
                    (_ctx.rewardOfferIcon)
                      ? (_openBlock(), _createElementBlock("img", {
                          key: 0,
                          src: _ctx.rewardOfferIcon,
                          alt: ""
                        }, null, 8 /* PROPS */, _hoisted_4))
                      : (_openBlock(), _createElementBlock("i", _hoisted_5))
                  ]),
                  _createElementVNode("div", _hoisted_6, [
                    _createElementVNode("p", _hoisted_7, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.001')), 1 /* TEXT */),
                    _createElementVNode("h2", _hoisted_8, _toDisplayString(_ctx.rewardOffer?.reward.title || _ctx.t('theme.useroptions.content.staticcontent.002')), 1 /* TEXT */),
                    (_ctx.rewardOffer)
                      ? (_openBlock(), _createElementBlock("p", _hoisted_9, [
                          _createTextVNode(_toDisplayString(_ctx.rewardOffer.reward.description || _ctx.rewardOffer.reward.badge?.description || _ctx.t('theme.useroptions.content.staticcontent.003')) + " ", 1 /* TEXT */),
                          (_ctx.rewardOffer.reward.currency)
                            ? (_openBlock(), _createElementBlock(_Fragment, { key: 0 }, [
                                _createTextVNode(_toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.004')) + " " + _toDisplayString(_ctx.formatBalanceAmount(_ctx.rewardOffer.reward.currency.amount)) + " " + _toDisplayString(_ctx.rewardOffer.reward.currency.currencyName) + ". ", 1 /* TEXT */)
                              ], 64 /* STABLE_FRAGMENT */))
                            : _createCommentVNode("v-if", true)
                        ]))
                      : (_ctx.rewardOfferLoading)
                        ? (_openBlock(), _createElementBlock("p", _hoisted_10, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.005')), 1 /* TEXT */))
                        : (_openBlock(), _createElementBlock("p", _hoisted_11, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.006')), 1 /* TEXT */))
                  ]),
                  _createElementVNode("button", {
                    class: "button button--primary rules-badge-claim__button",
                    type: "button",
                    disabled: !_ctx.authenticated || _ctx.rewardOfferLoading || _ctx.rewardOfferClaiming || !_ctx.rewardOffer?.claimable,
                    onClick: $event => (_ctx.emit('claimReward'))
                  }, [
                    _createElementVNode("i", {
                      class: _normalizeClass(["fa-solid", _ctx.rewardOfferClaiming ? 'fa-spinner' : _ctx.rewardOffer?.acquired ? 'fa-circle-check' : 'fa-key']),
                      "aria-hidden": "true"
                    }, null, 2 /* CLASS */),
                    (_ctx.rewardOfferLoading)
                      ? (_openBlock(), _createElementBlock("span", _hoisted_13, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.007')), 1 /* TEXT */))
                      : (_ctx.rewardOfferClaiming)
                        ? (_openBlock(), _createElementBlock("span", _hoisted_14, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.008')), 1 /* TEXT */))
                        : (_ctx.rewardOffer?.acquired)
                          ? (_openBlock(), _createElementBlock("span", _hoisted_15, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.009')), 1 /* TEXT */))
                          : (_openBlock(), _createElementBlock("span", _hoisted_16, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.010')), 1 /* TEXT */))
                  ], 8 /* PROPS */, _hoisted_12),
                  (_ctx.rewardOfferFeedback)
                    ? (_openBlock(), _createElementBlock("p", {
                        key: 0,
                        class: _normalizeClass(["rules-badge-claim__feedback", `rules-badge-claim__feedback--${_ctx.rewardOfferFeedback.type}`]),
                        role: _ctx.rewardOfferFeedback.type === 'error' ? 'alert' : 'status',
                        "aria-live": "polite"
                      }, _toDisplayString(_ctx.rewardOfferFeedback.message), 11 /* TEXT, CLASS, PROPS */, _hoisted_17))
                    : (!_ctx.authenticated)
                      ? (_openBlock(), _createElementBlock("p", _hoisted_18, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.012')), 1 /* TEXT */))
                      : _createCommentVNode("v-if", true)
                ])
              ]))
            : _createCommentVNode("v-if", true)
        ], 64 /* STABLE_FRAGMENT */))
      : (_ctx.error)
        ? (_openBlock(), _createElementBlock("div", _hoisted_19, [
            _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.013')), 1 /* TEXT */),
            _createElementVNode("p", null, _toDisplayString(_ctx.t('theme.useroptions.content.staticcontent.014')), 1 /* TEXT */)
          ]))
        : _createCommentVNode("v-if", true)
}
export const templateId = "static-content"
export const sourceHash = "301286afb85d408e19a65cec2572cbd21242e531708744fc0260ab48f6e4930f"
