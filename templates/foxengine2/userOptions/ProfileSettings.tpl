<fox-user-options-template id="profile-settings" schema="1" revision="4" updated-at="2026-08-14T03:36:00Z">
  <fox-profile-options>
    <fox-profile-option id="profile" component="ProfileOption" label="i18n:theme.runtime.profile.option.profile.label" description="i18n:theme.runtime.profile.option.profile.description" icon="fa-user" order="10" enabled="true" />
    <fox-profile-option id="appearance" component="AppearanceOption" label="i18n:theme.runtime.profile.option.appearance.label" description="i18n:theme.runtime.profile.option.appearance.description" icon="fa-palette" order="20" enabled="true" />
    <fox-profile-option id="security" component="SecurityOption" label="i18n:theme.runtime.profile.option.security.label" description="i18n:theme.runtime.profile.option.security.description" icon="fa-shield-halved" order="30" enabled="true" />
  </fox-profile-options>
  <fox-template-body>
<article class="content-surface settings-page" :style="{ '--settings-accent': accent }">
    <header>
      <span class="eyebrow">{{ t('theme.useroptions.useroptions.profilesettings.001') }}</span>
      <h1>{{ t('theme.useroptions.useroptions.profilesettings.002') }}</h1>
      <p class="lead">{{ t('theme.useroptions.useroptions.profilesettings.003') }}</p>
    </header>

    <div v-if="runtimeUserOptionsState.loading && !runtimeUserOptionsState.loaded" class="runtime-panel-skeleton" aria-hidden="true">
      <span /><span /><span />
    </div>
    <div v-else-if="runtimeUserOptionsState.error" class="system-message system-message--error" role="alert">
      <strong>{{ t('theme.useroptions.useroptions.profilesettings.011') }}</strong>
      <p>{{ t('theme.useroptions.useroptions.profilesettings.013') }}</p>
    </div>
    <template v-else>
      <nav class="settings-tabs" :aria-label="t('theme.useroptions.useroptions.profilesettings.004')">
        <button
          v-for="option in runtimeProfileOptions"
          :key="option.id"
          class="button button--primary"
          type="button"
          :class="{ active: activeTab === option.id }"
          :title="option.description || undefined"
          @pointerenter="preloadOption(option.id)"
          @focus="preloadOption(option.id)"
          @click="emit('update:activeTab', option.id)"
        >
          <i class="fa-solid" :class="option.icon" aria-hidden="true" />
          <span>{{ option.label }}</span>
        </button>
      </nav>
      <form class="settings-form" @submit.prevent="emit('submit')">
        <Suspense timeout="0">
          <template #default>
            <ProfileOption
              v-if="currentComponent === 'ProfileOption'"
              :form="form"
            />
            <AppearanceOption
              v-else-if="currentComponent === 'AppearanceOption'"
              :key="avatarPreview"
              :form="form"
              :avatar-preview="avatarPreview"
              :avatar-selected="avatarSelected"
              :uploading="uploading"
              :photo-feedback="photoFeedback"
              :accent="accent"
              :show-skin-settings="showSkinSettings"
              :viewer-group-tag="viewerGroupTag"
              :minecraft-uuid="minecraftUuid"
              :minecraft-front-preview="minecraftFrontPreview"
              :minecraft-back-preview="minecraftBackPreview"
              :minecraft-preview-loading="minecraftPreviewLoading"
              :minecraft-selected-skin-name="minecraftSelectedSkinName"
              :minecraft-selected-skin-size="minecraftSelectedSkinSize"
              :minecraft-selected-cloak-name="minecraftSelectedCloakName"
              :minecraft-selected-cloak-size="minecraftSelectedCloakSize"
              :minecraft-skin-input-version="minecraftSkinInputVersion"
              :minecraft-cloak-input-version="minecraftCloakInputVersion"
              :minecraft-busy="minecraftBusy"
              :minecraft-feedback="minecraftFeedback"
              @select-avatar="emit('selectAvatar', $event)"
              @clear-avatar="emit('clearAvatar')"
              @upload-avatar="emit('uploadAvatar')"
              @select-minecraft="(type, event) => emit('selectMinecraft', type, event)"
              @upload-minecraft="emit('uploadMinecraft', $event)"
              @remove-minecraft="emit('removeMinecraft', $event)"
              @refresh-minecraft="emit('refreshMinecraft')"
              @update:accent="emit('update:accent', $event)"
            />
            <SecurityOption v-else-if="currentComponent === 'SecurityOption'" :form="form" :require-current-password="!canManageUsers" />
            <div v-else class="system-message system-message--error">
              <strong>{{ t('theme.useroptions.useroptions.profilesettings.012') }}</strong>
            </div>
          </template>
          <template #fallback>
            <div class="runtime-panel-skeleton" aria-hidden="true">
              <span /><span /><span />
            </div>
          </template>
        </Suspense>
        <p v-if="feedback" class="form-feedback" :class="{ 'form-feedback--success': feedback.type === 'success' }">{{ feedback.message }}</p>
        <div class="settings-actions">
          <button class="button button--ghost" type="button" @click="emit('navigate', 'profile')">{{ t('theme.useroptions.useroptions.profilesettings.008') }}</button>
          <button class="button button--primary button--large" type="submit" :disabled="submitting">{{ submitting ? t('theme.useroptions.useroptions.profilesettings.009') : t('theme.useroptions.useroptions.profilesettings.010') }}</button>
        </div>
      </form>
    </template>
  </article>
  </fox-template-body>
</fox-user-options-template>
