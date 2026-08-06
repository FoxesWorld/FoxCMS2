/* fox-runtime-template id=badge sha256=2f471ca3fb41a8c157498c01ef86f01cd9808038c35f5d987c11156baf5f7c12 */
import { createElementVNode as _createElementVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, resolveDirective as _resolveDirective, withDirectives as _withDirectives, toDisplayString as _toDisplayString } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = {
  key: 0,
  class: "content-skeleton"
}
const _hoisted_2 = ["data-badge-route", "innerHTML"]
const _hoisted_3 = {
  key: 2,
  class: "system-message system-message--error"
}

export function render(_ctx, _cache) {
  const _directive_emoticons = _resolveDirective("emoticons")

  return (_ctx.loading)
    ? (_openBlock(), _createElementBlock("div", _hoisted_1, [...(_cache[0] || (_cache[0] = [
        _createElementVNode("span", null, null, -1 /* CACHED */),
        _createElementVNode("span", null, null, -1 /* CACHED */),
        _createElementVNode("span", null, null, -1 /* CACHED */)
      ]))]))
    : (_ctx.badge)
      ? _withDirectives((_openBlock(), _createElementBlock("div", {
          key: 1,
          class: "badge-runtime-page",
          "data-badge-route": _ctx.badge.id,
          innerHTML: _ctx.badge.html
        }, null, 8 /* PROPS */, _hoisted_2)), [
          [_directive_emoticons]
        ])
      : (_ctx.error)
        ? (_openBlock(), _createElementBlock("div", _hoisted_3, [
            _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badge.001')), 1 /* TEXT */),
            _createElementVNode("p", null, _toDisplayString(_ctx.t('theme.useroptions.pages.badges.badge.002')), 1 /* TEXT */)
          ]))
        : _createCommentVNode("v-if", true)
}
export const templateId = "badge"
export const sourceHash = "2f471ca3fb41a8c157498c01ef86f01cd9808038c35f5d987c11156baf5f7c12"
