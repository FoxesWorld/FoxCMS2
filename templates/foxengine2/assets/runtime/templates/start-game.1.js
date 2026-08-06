/* fox-runtime-template id=start-game sha256=872e6d6b665cbea0d882537280faf4eaa62a2bb3981bdfea9cc6eb347b5f2cc5 */
import { createElementVNode as _createElementVNode, openBlock as _openBlock, createElementBlock as _createElementBlock, createCommentVNode as _createCommentVNode, resolveDirective as _resolveDirective, withDirectives as _withDirectives, toDisplayString as _toDisplayString, createTextVNode as _createTextVNode } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = {
  key: 0,
  class: "content-skeleton"
}
const _hoisted_2 = ["onClickCapture", "innerHTML"]
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
    : (_ctx.page)
      ? _withDirectives((_openBlock(), _createElementBlock("div", {
          key: 1,
          class: "static-page-html start-page-runtime",
          onClickCapture: _ctx.handleAction,
          innerHTML: _ctx.hydratedHtml
        }, null, 40 /* PROPS, NEED_HYDRATION */, _hoisted_2)), [
          [_directive_emoticons]
        ])
      : (_ctx.error)
        ? (_openBlock(), _createElementBlock("div", _hoisted_3, [
            _createElementVNode("strong", null, _toDisplayString(_ctx.t('theme.useroptions.pages.startgame.001')), 1 /* TEXT */),
            _createElementVNode("p", null, [
              _createTextVNode(_toDisplayString(_ctx.t('theme.useroptions.pages.startgame.002')) + " ", 1 /* TEXT */),
              _cache[1] || (_cache[1] = _createElementVNode("code", null, "pages/content/start.html", -1 /* CACHED */)),
              _cache[2] || (_cache[2] = _createTextVNode(".", -1 /* CACHED */))
            ])
          ]))
        : _createCommentVNode("v-if", true)
}
export const templateId = "start-game"
export const sourceHash = "872e6d6b665cbea0d882537280faf4eaa62a2bb3981bdfea9cc6eb347b5f2cc5"
