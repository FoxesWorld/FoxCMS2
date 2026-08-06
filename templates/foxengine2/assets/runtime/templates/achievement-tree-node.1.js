/* fox-runtime-template id=achievement-tree-node sha256=c1a86e83dc037273cce27179f79df96a6e9f7ddb600ca74e30b6b1530a101708 */
import { createElementVNode as _createElementVNode, normalizeClass as _normalizeClass, toDisplayString as _toDisplayString, createTextVNode as _createTextVNode, renderList as _renderList, Fragment as _Fragment, openBlock as _openBlock, createElementBlock as _createElementBlock, resolveComponent as _resolveComponent, withCtx as _withCtx, createBlock as _createBlock, createCommentVNode as _createCommentVNode } from "/templates/foxengine2/assets/runtime/vue-runtime.js"

const _hoisted_1 = { class: "achievement-tree-node__card" }
const _hoisted_2 = { class: "achievement-tree-node__identity" }
const _hoisted_3 = { class: "achievement-tree-node__icon" }
const _hoisted_4 = ["src"]
const _hoisted_5 = { class: "achievement-tree-node__copy" }
const _hoisted_6 = { class: "achievement-tree-node__meta" }
const _hoisted_7 = { class: "achievement-tree-node__points" }
const _hoisted_8 = { class: "achievement-tree-node__earned" }
const _hoisted_9 = {
  key: 0,
  class: "achievement-tree-node__players"
}
const _hoisted_10 = { class: "achievement-tree-node__player-list" }
const _hoisted_11 = { class: "achievement-tree-player__avatar" }
const _hoisted_12 = {
  key: 0,
  class: "achievement-tree-node__truncated"
}
const _hoisted_13 = {
  key: 1,
  class: "achievement-tree-node__nobody"
}
const _hoisted_14 = {
  key: 0,
  class: "achievement-tree-node__children"
}

export function render(_ctx, _cache) {
  const _component_RouterLink = _resolveComponent("RouterLink")
  const _component_AchievementTreeNode = _resolveComponent("AchievementTreeNode")

  return (_openBlock(), _createElementBlock("li", {
    class: _normalizeClass(["achievement-tree-node", [`achievement-tree-node--${_ctx.nodeTone}`, { 'has-children': _ctx.node.children.length > 0 }]])
  }, [
    _createElementVNode("article", _hoisted_1, [
      _cache[5] || (_cache[5] = _createElementVNode("span", {
        class: "achievement-tree-node__connector",
        "aria-hidden": "true"
      }, null, -1 /* CACHED */)),
      _createElementVNode("div", _hoisted_2, [
        _createElementVNode("span", _hoisted_3, [
          _createElementVNode("img", {
            src: _ctx.node.iconDataUrl,
            alt: "",
            loading: "lazy",
            decoding: "async"
          }, null, 8 /* PROPS */, _hoisted_4),
          _createElementVNode("i", {
            class: _normalizeClass(_ctx.node.earnedCount > 0 ? 'fa-solid fa-check' : 'fa-solid fa-lock'),
            "aria-hidden": "true"
          }, null, 2 /* CLASS */)
        ]),
        _createElementVNode("span", _hoisted_5, [
          _createElementVNode("small", null, _toDisplayString(_ctx.node.category) + " · " + _toDisplayString(_ctx.node.serverId), 1 /* TEXT */),
          _createElementVNode("strong", null, _toDisplayString(_ctx.node.title), 1 /* TEXT */),
          _createElementVNode("span", null, _toDisplayString(_ctx.node.description || _ctx.t('engine.views.achievements.033')), 1 /* TEXT */)
        ])
      ]),
      _createElementVNode("div", _hoisted_6, [
        _createElementVNode("span", _hoisted_7, "+" + _toDisplayString(_ctx.node.points), 1 /* TEXT */),
        _createElementVNode("span", _hoisted_8, [
          _cache[0] || (_cache[0] = _createElementVNode("i", {
            class: "fa-solid fa-users",
            "aria-hidden": "true"
          }, null, -1 /* CACHED */)),
          _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.055', [_ctx.node.earnedCount])), 1 /* TEXT */)
        ])
      ]),
      (_ctx.node.players.length > 0)
        ? (_openBlock(), _createElementBlock("details", _hoisted_9, [
            _createElementVNode("summary", null, [
              _createElementVNode("span", null, [
                _cache[1] || (_cache[1] = _createElementVNode("i", {
                  class: "fa-solid fa-user-group",
                  "aria-hidden": "true"
                }, null, -1 /* CACHED */)),
                _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.057')), 1 /* TEXT */)
              ]),
              _cache[2] || (_cache[2] = _createElementVNode("i", {
                class: "fa-solid fa-chevron-down",
                "aria-hidden": "true"
              }, null, -1 /* CACHED */))
            ]),
            _createElementVNode("div", _hoisted_10, [
              (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.node.players, (player) => {
                return (_openBlock(), _createBlock(_component_RouterLink, {
                  key: `${_ctx.node.serverId}:${_ctx.node.achievementKey}:${player.uuid}`,
                  class: "achievement-tree-player",
                  to: _ctx.playerRoute(player)
                }, {
                  default: _withCtx(() => [
                    _createElementVNode("span", _hoisted_11, _toDisplayString(_ctx.playerInitial(player)), 1 /* TEXT */),
                    _createElementVNode("span", null, [
                      _createElementVNode("strong", null, _toDisplayString(_ctx.playerLabel(player)), 1 /* TEXT */),
                      _createElementVNode("small", null, _toDisplayString(_ctx.completedDate(player.completedAt)), 1 /* TEXT */)
                    ]),
                    _cache[3] || (_cache[3] = _createElementVNode("i", {
                      class: "fa-solid fa-arrow-up-right-from-square",
                      "aria-hidden": "true"
                    }, null, -1 /* CACHED */))
                  ]),
                  _: 2 /* DYNAMIC */
                }, 1032 /* PROPS, DYNAMIC_SLOTS */, ["to"]))
              }), 128 /* KEYED_FRAGMENT */)),
              (_ctx.node.playersTruncated)
                ? (_openBlock(), _createElementBlock("p", _hoisted_12, _toDisplayString(_ctx.t('engine.views.achievements.058', [Math.max(0, _ctx.node.earnedCount - _ctx.node.players.length)])), 1 /* TEXT */))
                : _createCommentVNode("v-if", true)
            ])
          ]))
        : (_openBlock(), _createElementBlock("p", _hoisted_13, [
            _cache[4] || (_cache[4] = _createElementVNode("i", {
              class: "fa-solid fa-circle",
              "aria-hidden": "true"
            }, null, -1 /* CACHED */)),
            _createTextVNode(" " + _toDisplayString(_ctx.t('engine.views.achievements.056')), 1 /* TEXT */)
          ]))
    ]),
    (_ctx.node.children.length > 0)
      ? (_openBlock(), _createElementBlock("ol", _hoisted_14, [
          (_openBlock(true), _createElementBlock(_Fragment, null, _renderList(_ctx.node.children, (child) => {
            return (_openBlock(), _createBlock(_component_AchievementTreeNode, {
              key: `${child.serverId}:${child.achievementKey}`,
              node: child,
              depth: _ctx.depth + 1
            }, null, 8 /* PROPS */, ["node", "depth"]))
          }), 128 /* KEYED_FRAGMENT */))
        ]))
      : _createCommentVNode("v-if", true)
  ], 2 /* CLASS */))
}
export const templateId = "achievement-tree-node"
export const sourceHash = "c1a86e83dc037273cce27179f79df96a6e9f7ddb600ca74e30b6b1530a101708"
