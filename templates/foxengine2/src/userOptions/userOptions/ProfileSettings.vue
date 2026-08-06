<script setup lang="ts">
import { t } from '@/i18n'
import { computed, defineAsyncComponent, markRaw, toRefs } from 'vue'
import RuntimeTpl from '@engine/runtime/RuntimeTpl.vue'
import type { FeedbackMessage, ProfileSettingsFormModel, SettingsTab, SkinResource } from '@engine/contracts/user-pages'
import {
  loadRuntimeUserOptions,
  runtimeProfileOptions,
  runtimeUserOptionsState,
  type ProfileOptionComponent,
} from '@engine/runtime/userOptions'

const optionLoaders = {
  ProfileOption: () => import('./profile/options/ProfileOption.vue'),
  AppearanceOption: () => import('./profile/options/AppearanceOption.vue'),
  SecurityOption: () => import('./profile/options/SecurityOption.vue'),
} satisfies Record<ProfileOptionComponent, () => Promise<unknown>>

const ProfileOption = defineAsyncComponent(optionLoaders.ProfileOption)
const AppearanceOption = defineAsyncComponent(optionLoaders.AppearanceOption)
const SecurityOption = defineAsyncComponent(optionLoaders.SecurityOption)

const props = defineProps<{
  canManageUsers: boolean
  showSkinSettings: boolean
  viewerGroupTag: string
  minecraftUuid: string
  minecraftFrontPreview: string
  minecraftBackPreview: string
  minecraftPreviewLoading: boolean
  minecraftSelectedSkinName: string
  minecraftSelectedSkinSize: number
  minecraftSelectedCloakName: string
  minecraftSelectedCloakSize: number
  minecraftSkinInputVersion: number
  minecraftCloakInputVersion: number
  minecraftBusy: SkinResource | null
  minecraftFeedback: FeedbackMessage | null
  activeTab: SettingsTab
  form: ProfileSettingsFormModel
  avatarPreview: string
  avatarSelected: boolean
  uploading: boolean
  photoFeedback: FeedbackMessage | null
  feedback: FeedbackMessage | null
  accent: string
  submitting: boolean
}>()
const emit = defineEmits<{
  'update:activeTab': [value: SettingsTab]
  'update:accent': [value: string]
  submit: []
  selectAvatar: [file: File]
  clearAvatar: []
  uploadAvatar: []
  selectMinecraft: [type: SkinResource, event: Event]
  uploadMinecraft: [type: SkinResource]
  removeMinecraft: [type: SkinResource]
  refreshMinecraft: []
  navigate: [route: string]
}>()

const currentOption = computed(() => runtimeProfileOptions.value.find((option) => option.id === props.activeTab)
  ?? runtimeProfileOptions.value[0]
  ?? null)
const currentComponent = computed(() => currentOption.value?.component ?? null)

function preloadOption(tab: SettingsTab): void {
  const option = runtimeProfileOptions.value.find((entry) => entry.id === tab)
  if (option) void optionLoaders[option.component]()
}


const profileTemplate = computed(() => runtimeUserOptionsState.document?.templates.profileSettings ?? null)
const profileTemplateComponents = markRaw({ ProfileOption, AppearanceOption, SecurityOption })
const profileTemplateContext: Record<string, unknown> = {
  t,
  ...toRefs(props),
  emit,
  runtimeUserOptionsState,
  runtimeProfileOptions,
  currentOption,
  currentComponent,
  preloadOption,
}

void loadRuntimeUserOptions().catch((error: unknown) => {
  console.error('[FoxesCraft] Runtime profile options failed to load', error)
})
</script>

<template>
  <RuntimeTpl
    v-if="profileTemplate"
    :template-id="profileTemplate.id"
    :module-url="profileTemplate.moduleUrl"
    :revision="profileTemplate.revision"
    :context="profileTemplateContext"
    :components="profileTemplateComponents"
  />
  <div v-else class="runtime-panel-skeleton" aria-hidden="true"><span /><span /><span /></div>
</template>
