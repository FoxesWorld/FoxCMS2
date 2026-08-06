<fox-page-template id="achievement-tree-node" schema="1" revision="1" updated-at="">
  <fox-template-body>
<li
    class="achievement-tree-node"
    :class="[`achievement-tree-node--${nodeTone}`, { 'has-children': node.children.length > 0 }]"
  >
    <article class="achievement-tree-node__card">
      <span class="achievement-tree-node__connector" aria-hidden="true" />
      <div class="achievement-tree-node__identity">
        <span class="achievement-tree-node__icon">
          <img :src="node.iconDataUrl" alt="" loading="lazy" decoding="async">
          <i
            :class="node.earnedCount > 0 ? 'fa-solid fa-check' : 'fa-solid fa-lock'"
            aria-hidden="true"
          />
        </span>
        <span class="achievement-tree-node__copy">
          <small>{{ node.category }} · {{ node.serverId }}</small>
          <strong>{{ node.title }}</strong>
          <span>{{ node.description || t('engine.views.achievements.033') }}</span>
        </span>
      </div>

      <div class="achievement-tree-node__meta">
        <span class="achievement-tree-node__points">+{{ node.points }}</span>
        <span class="achievement-tree-node__earned">
          <i class="fa-solid fa-users" aria-hidden="true" />
          {{ t('engine.views.achievements.055', [node.earnedCount]) }}
        </span>
      </div>

      <details v-if="node.players.length > 0" class="achievement-tree-node__players">
        <summary>
          <span>
            <i class="fa-solid fa-user-group" aria-hidden="true" />
            {{ t('engine.views.achievements.057') }}
          </span>
          <i class="fa-solid fa-chevron-down" aria-hidden="true" />
        </summary>
        <div class="achievement-tree-node__player-list">
          <RouterLink
            v-for="player in node.players"
            :key="`${node.serverId}:${node.achievementKey}:${player.uuid}`"
            class="achievement-tree-player"
            :to="playerRoute(player)"
          >
            <span class="achievement-tree-player__avatar">{{ playerInitial(player) }}</span>
            <span>
              <strong>{{ playerLabel(player) }}</strong>
              <small>{{ completedDate(player.completedAt) }}</small>
            </span>
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true" />
          </RouterLink>
          <p v-if="node.playersTruncated" class="achievement-tree-node__truncated">
            {{ t('engine.views.achievements.058', [Math.max(0, node.earnedCount - node.players.length)]) }}
          </p>
        </div>
      </details>
      <p v-else class="achievement-tree-node__nobody">
        <i class="fa-solid fa-circle" aria-hidden="true" />
        {{ t('engine.views.achievements.056') }}
      </p>
    </article>

    <ol v-if="node.children.length > 0" class="achievement-tree-node__children">
      <AchievementTreeNode
        v-for="child in node.children"
        :key="`${child.serverId}:${child.achievementKey}`"
        :node="child"
        :depth="depth + 1"
      />
    </ol>
  </li>
  </fox-template-body>
</fox-page-template>
