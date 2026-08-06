<fox-page-template id="badge" schema="1" revision="1" updated-at="">
  <fox-template-body>
<div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <div
    v-else-if="badge"
    class="badge-runtime-page"
    :data-badge-route="badge.id"
    v-emoticons
    v-html="badge.html"
  />
  <div v-else-if="error" class="system-message system-message--error">
    <strong>{{ t('theme.useroptions.pages.badges.badge.001') }}</strong>
    <p>{{ t('theme.useroptions.pages.badges.badge.002') }}</p>
  </div>
  </fox-template-body>
</fox-page-template>
