<fox-page-template id="start-game" schema="1" revision="1" updated-at="">
  <fox-template-body>
<div v-if="loading" class="content-skeleton"><span /><span /><span /></div>
  <div
    v-else-if="page"
    class="static-page-html start-page-runtime"
    v-emoticons
    @click.capture="handleAction"
    v-html="hydratedHtml"
  />
  <div v-else-if="error" class="system-message system-message--error">
    <strong>{{ t('theme.useroptions.pages.startgame.001') }}</strong>
    <p>{{ t('theme.useroptions.pages.startgame.002') }} <code>pages/content/start.html</code>.</p>
  </div>
  </fox-template-body>
</fox-page-template>
