<script setup lang="ts">
import { t } from '@/i18n'

import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ProfileSettingsPage from '@theme/userOptions/userOptions/ProfileSettings.vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { bootstrapString } from '@/domain/bootstrap'
import type { FeedbackMessage, ProfileRecord, ProfileSettingsFormModel, SettingsTab, SkinResource } from '@/contracts/user-pages'
import { loadRuntimeUserOptions, runtimeProfileOptions } from '@/runtime/userOptions'

interface SettingsRecord extends ProfileRecord { email?: string; message?: string }
type Response = FeedbackMessage & { url?: string }

const router = useRouter()
const route = router.currentRoute
const viewerLogin = bootstrapString(appBootstrap, 'login')
const viewerUuid = bootstrapString(appBootstrap, 'uuid')
const canManageUsers = appBootstrap.frontend.capabilities.includes('admin.panel')
const viewerGroupTag = bootstrapString(appBootstrap, 'groupTag', 'guest')
const activeTab = ref<SettingsTab>('profile')
const loading = ref(true)
const error = ref('')
const submitting = ref(false)
const uploading = ref(false)
const feedback = ref<Response | null>(null)
const photoFeedback = ref<Response | null>(null)
const avatarFile = ref<File | null>(null)
const avatarPreview = ref('')
const targetUuid = ref('')
const accent = ref('#5bd08b')
const form = reactive<ProfileSettingsFormModel>({ login: '', realname: '', userStatus: '', land: '', email: '', currentPassword: '', newPassword: '', repeatPassword: '' })
const showSkinSettings = ref(true)
const minecraftFrontPreview = ref('')
const minecraftBackPreview = ref('')
const minecraftPreviewLoading = ref(false)
const minecraftBusy = ref<SkinResource | null>(null)
const minecraftFeedback = ref<Response | null>(null)
const minecraftSelected = ref<Record<SkinResource, File | null>>({ skin: null, cloak: null })
const minecraftInputVersion = ref<Record<SkinResource, number>>({ skin: 0, cloak: 0 })
let minecraftLoadedUuid = ''

function fail(message: string, tab?: SettingsTab): void {
  feedback.value = { type: 'error', message }
  if (tab) activeTab.value = tab
}

async function load(value?: string): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const identity = value || viewerLogin
    const user = await foxesApi.post<SettingsRecord>({ user_doaction: 'getUserSettings', ...(value ? { userUuid: identity } : { login: identity }) })
    if (!user.uuid || !user.login) { error.value = user.message || t('modules.usersettings.profilesettingsview.003'); return }
    targetUuid.value = user.uuid
    showSkinSettings.value = viewerUuid !== '' && user.uuid.replaceAll('-', '').toLowerCase() === viewerUuid.replaceAll('-', '').toLowerCase()
    minecraftFrontPreview.value = ''
    minecraftBackPreview.value = ''
    minecraftFeedback.value = null
    minecraftSelected.value = { skin: null, cloak: null }
    minecraftLoadedUuid = ''
    Object.assign(form, { login: user.login, realname: user.realname || '', userStatus: user.userStatus || '', land: user.land || '', email: user.email || '', currentPassword: '', newPassword: '', repeatPassword: '' })
    avatarPreview.value = user.profilePhoto || ''
    accent.value = /^#[0-9a-f]{6}$/i.test(user.colorScheme || '') ? user.colorScheme! : '#5bd08b'
    if (activeTab.value === 'appearance' && showSkinSettings.value) await refreshMinecraftPreview()
  } catch { error.value = t('modules.usersettings.profilesettingsview.004') }
  finally { loading.value = false }
}

function selectAvatar(file: File): void {
  photoFeedback.value = null
  if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024) {
    photoFeedback.value = { type: 'error', message: t('modules.usersettings.profilesettingsview.005') }
    avatarFile.value = null
    return
  }
  avatarFile.value = file
}

function clearAvatar(): void {
  avatarFile.value = null
  photoFeedback.value = null
}

async function uploadAvatar(): Promise<void> {
  if (!avatarFile.value || !targetUuid.value) return
  uploading.value = true
  photoFeedback.value = null
  try {
    const data = new FormData()
    data.set('user_doaction', 'updateProfilePhoto')
    data.set('userUuid', targetUuid.value)
    data.set('image', avatarFile.value)
    const response = await foxesApi.postFormData<Response>(data)
    photoFeedback.value = response
    if (response.type === 'success' && response.url) {
      avatarPreview.value = response.url
      avatarFile.value = null
    }
  } catch { photoFeedback.value = { type: 'error', message: t('modules.usersettings.profilesettingsview.006') } }
  finally { uploading.value = false }
}

async function refreshMinecraftPreview(force = false): Promise<void> {
  const userUuid = targetUuid.value || viewerUuid
  if (!showSkinSettings.value || userUuid === '') return
  const normalizedUuid = userUuid.replaceAll('-', '').toLowerCase()
  if (!force && minecraftLoadedUuid === normalizedUuid && minecraftFrontPreview.value && minecraftBackPreview.value) return
  minecraftPreviewLoading.value = true
  try {
    const [front, back] = await Promise.all([
      foxesApi.postText({ sysRequest: 'skinPreview', userUuid, side: 'front' }),
      foxesApi.postText({ sysRequest: 'skinPreview', userUuid, side: 'back' }),
    ])
    minecraftFrontPreview.value = `data:image/png;base64,${front.trim()}`
    minecraftBackPreview.value = `data:image/png;base64,${back.trim()}`
    minecraftLoadedUuid = normalizedUuid
  } catch (minecraftError) {
    console.error('[FoxesCraft] Minecraft identity preview failed', minecraftError)
    minecraftFeedback.value = { type: 'error', message: t('modules.usersettings.profilesettingsview.007') }
  } finally {
    minecraftPreviewLoading.value = false
  }
}

function selectMinecraftFile(type: SkinResource, event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  minecraftFeedback.value = null
  if (file && file.type !== 'image/png' && !file.name.toLowerCase().endsWith('.png')) {
    minecraftFeedback.value = { type: 'error', message: t('modules.usersettings.profilesettingsview.008') }
    input.value = ''
    minecraftSelected.value[type] = null
    return
  }
  minecraftSelected.value[type] = file
}

async function uploadMinecraftFile(type: SkinResource): Promise<void> {
  const file = minecraftSelected.value[type]
  const userUuid = targetUuid.value || viewerUuid
  if (!file || !showSkinSettings.value || userUuid === '') return
  minecraftBusy.value = type
  minecraftFeedback.value = null
  try {
    const data = new FormData()
    data.set('0', file)
    data.set('sysRequest', 'uploadFile')
    data.set('type', type)
    data.set('userUuid', userUuid)
    const response = await foxesApi.postFormData<Response>(data)
    minecraftFeedback.value = response
    if (response.type === 'success') {
      minecraftSelected.value[type] = null
      minecraftInputVersion.value[type]++
      await refreshMinecraftPreview(true)
    }
  } catch (minecraftError) {
    console.error('[FoxesCraft] Minecraft identity upload failed', minecraftError)
    minecraftFeedback.value = { type: 'error', message: t('modules.usersettings.profilesettingsview.009') }
  } finally {
    minecraftBusy.value = null
  }
}

async function removeMinecraftFile(type: SkinResource): Promise<void> {
  const userUuid = targetUuid.value || viewerUuid
  if (!showSkinSettings.value || userUuid === '') return
  minecraftBusy.value = type
  minecraftFeedback.value = null
  try {
    const response = await foxesApi.post<Response>({ sysRequest: 'deleteFile', type, userUuid })
    minecraftFeedback.value = response
    if (response.type === 'success') {
      minecraftSelected.value[type] = null
      minecraftInputVersion.value[type]++
      await refreshMinecraftPreview(true)
    }
  } catch (minecraftError) {
    console.error('[FoxesCraft] Minecraft identity removal failed', minecraftError)
    minecraftFeedback.value = { type: 'error', message: t('modules.usersettings.profilesettingsview.010') }
  } finally {
    minecraftBusy.value = null
  }
}

async function saveProfile(): Promise<void> {
  feedback.value = null
  if (!canManageUsers && !form.currentPassword) return fail(t('modules.usersettings.profilesettingsview.011'))
  const changesPassword = Boolean(form.newPassword || form.repeatPassword)
  if (changesPassword && form.newPassword !== form.repeatPassword) return fail(t('modules.usersettings.profilesettingsview.012'), 'security')
  if (changesPassword && form.newPassword.length < 10) return fail(t('modules.usersettings.profilesettingsview.013'), 'security')
  submitting.value = true
  try {
    const response = await foxesApi.post<Response>({
      user_doaction: 'EditUser', userUuid: targetUuid.value, login: form.login.trim(), realname: form.realname.trim(),
      userStatus: form.userStatus.trim(), land: form.land.trim(), email: form.email.trim(), colorScheme: accent.value,
      password: form.currentPassword, newPass: form.newPassword, repeatPass: form.repeatPassword,
    })
    feedback.value = response
    if (response.type === 'success') {
      form.currentPassword = form.newPassword = form.repeatPassword = ''
    }
  } catch { feedback.value = { type: 'error', message: t('modules.usersettings.profilesettingsview.014') } }
  finally { submitting.value = false }
}

function navigate(route: string): void {
  void router.push(route === 'profile' ? { name: route, params: { value: form.login } } : { name: route })
}

function normalizeTab(value: unknown): SettingsTab {
  const requested = typeof value === 'string' ? value : ''
  return runtimeProfileOptions.value.find((option) => option.id === requested)?.id
    ?? runtimeProfileOptions.value[0]?.id
    ?? 'profile'
}

function selectTab(tab: SettingsTab): void {
  const normalized = normalizeTab(tab)
  activeTab.value = normalized
  if (route.value.query.tab === normalized) return
  void router.replace({
    query: { ...route.value.query, tab: normalized },
    hash: route.value.hash,
  })
}

watch([() => route.value.query.tab, runtimeProfileOptions], ([value]) => {
  const normalized = normalizeTab(value)
  activeTab.value = normalized
  if (runtimeProfileOptions.value.length > 0 && value !== normalized) {
    void router.replace({ query: { ...route.value.query, tab: normalized }, hash: route.value.hash })
  }
}, { immediate: true })
watch(() => route.value.params.value as string | undefined, load, { immediate: true })
void loadRuntimeUserOptions().catch((runtimeError: unknown) => {
  console.error('[FoxesCraft] Runtime profile options failed to load', runtimeError)
})
watch(activeTab, (tab) => {
  if (tab === 'appearance' && showSkinSettings.value) void refreshMinecraftPreview()
})
</script>

<template>
  <div v-if="loading" class="content-skeleton" :aria-label="t('modules.usersettings.profilesettingsview.001')"><span /><span /><span /></div>
  <div v-else-if="error" class="system-message system-message--error"><strong>{{ t('modules.usersettings.profilesettingsview.002') }}</strong><p>{{ error }}</p></div>
  <ProfileSettingsPage
    v-else
    :can-manage-users="canManageUsers"
    :show-skin-settings="showSkinSettings"
    :viewer-group-tag="viewerGroupTag"
    :minecraft-uuid="targetUuid"
    :minecraft-front-preview="minecraftFrontPreview"
    :minecraft-back-preview="minecraftBackPreview"
    :minecraft-preview-loading="minecraftPreviewLoading"
    :minecraft-selected-skin-name="minecraftSelected.skin?.name ?? ''"
    :minecraft-selected-skin-size="minecraftSelected.skin?.size ?? 0"
    :minecraft-selected-cloak-name="minecraftSelected.cloak?.name ?? ''"
    :minecraft-selected-cloak-size="minecraftSelected.cloak?.size ?? 0"
    :minecraft-skin-input-version="minecraftInputVersion.skin"
    :minecraft-cloak-input-version="minecraftInputVersion.cloak"
    :minecraft-busy="minecraftBusy"
    :minecraft-feedback="minecraftFeedback"
    :active-tab="activeTab"
    :form="form"
    :avatar-preview="avatarPreview"
    :avatar-selected="avatarFile !== null"
    :uploading="uploading"
    :photo-feedback="photoFeedback"
    :feedback="feedback"
    :accent="accent"
    :submitting="submitting"
    @update:active-tab="selectTab"
    @update:accent="accent = $event"
    @submit="saveProfile"
    @select-avatar="selectAvatar"
    @clear-avatar="clearAvatar"
    @upload-avatar="uploadAvatar"
    @select-minecraft="selectMinecraftFile"
    @upload-minecraft="uploadMinecraftFile"
    @remove-minecraft="removeMinecraftFile"
    @refresh-minecraft="refreshMinecraftPreview(true)"
    @navigate="navigate"
  />
</template>
