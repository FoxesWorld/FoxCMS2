<script setup lang="ts">
import { onUnmounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ProfileSettingsPage from '@theme/userOptions/userOptions/ProfileSettings.vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { bootstrapString } from '@/domain/bootstrap'
import type { FeedbackMessage, ProfileRecord, ProfileSettingsFormModel, SettingsTab } from '@/contracts/user-pages'

interface SettingsRecord extends ProfileRecord { email?: string; message?: string }
type Response = FeedbackMessage & { url?: string }

const router = useRouter()
const route = router.currentRoute
const viewerLogin = bootstrapString(appBootstrap, 'login')
const viewerUuid = bootstrapString(appBootstrap, 'uuid')
const canManageUsers = appBootstrap.frontend.capabilities.includes('admin.panel')
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
let objectUrl = ''
const form = reactive<ProfileSettingsFormModel>({ login: '', realname: '', userStatus: '', land: '', email: '', currentPassword: '', newPassword: '', repeatPassword: '' })
const showSkinSettings = ref(true)

function revoke(): void { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = '' }
function fail(message: string, tab?: SettingsTab): void {
  feedback.value = { type: 'error', message }
  if (tab) activeTab.value = tab
}

async function load(value?: string): Promise<void> {
  loading.value = true
  error.value = ''
  revoke()
  try {
    const identity = value || viewerLogin
    const user = await foxesApi.post<SettingsRecord>({ user_doaction: 'getUserSettings', ...(value ? { userUuid: identity } : { login: identity }) })
    if (!user.uuid || !user.login) { error.value = user.message || 'Пользователь не найден.'; return }
    targetUuid.value = user.uuid
    showSkinSettings.value = viewerUuid !== '' && user.uuid.replaceAll('-', '').toLowerCase() === viewerUuid.replaceAll('-', '').toLowerCase()
    Object.assign(form, { login: user.login, realname: user.realname || '', userStatus: user.userStatus || '', land: user.land || '', email: user.email || '', currentPassword: '', newPassword: '', repeatPassword: '' })
    avatarPreview.value = user.profilePhoto || ''
    accent.value = /^#[0-9a-f]{6}$/i.test(user.colorScheme || '') ? user.colorScheme! : '#5bd08b'
  } catch { error.value = 'Не удалось загрузить настройки профиля.' }
  finally { loading.value = false }
}

function selectAvatar(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  photoFeedback.value = null
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024) {
    photoFeedback.value = { type: 'error', message: 'Нужен JPEG, PNG, WebP или GIF размером до 5 МБ.' }
    input.value = ''
    return
  }
  avatarFile.value = file
  revoke()
  objectUrl = URL.createObjectURL(file)
  avatarPreview.value = objectUrl
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
      revoke()
    }
  } catch { photoFeedback.value = { type: 'error', message: 'Не удалось загрузить фото.' } }
  finally { uploading.value = false }
}

async function saveProfile(): Promise<void> {
  feedback.value = null
  if (!canManageUsers && !form.currentPassword) return fail('Для сохранения нужен текущий пароль.')
  const changesPassword = Boolean(form.newPassword || form.repeatPassword)
  if (changesPassword && form.newPassword !== form.repeatPassword) return fail('Новые пароли не совпадают.', 'security')
  if (changesPassword && form.newPassword.length < 10) return fail('Новый пароль должен содержать минимум 10 символов.', 'security')
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
  } catch { feedback.value = { type: 'error', message: 'Не удалось сохранить профиль.' } }
  finally { submitting.value = false }
}

function navigate(route: string): void {
  void router.push(route === 'profile' ? { name: route, params: { value: form.login } } : { name: route })
}

watch(() => route.value.params.value as string | undefined, load, { immediate: true })
onUnmounted(revoke)
</script>

<template>
  <div v-if="loading" class="content-skeleton" aria-label="Загрузка настроек"><span /><span /><span /></div>
  <div v-else-if="error" class="system-message system-message--error"><strong>Настройки недоступны</strong><p>{{ error }}</p></div>
  <ProfileSettingsPage
    v-else
    :can-manage-users="canManageUsers"
    :show-skin-settings="showSkinSettings"
    :active-tab="activeTab"
    :form="form"
    :avatar-preview="avatarPreview"
    :avatar-selected="avatarFile !== null"
    :uploading="uploading"
    :photo-feedback="photoFeedback"
    :feedback="feedback"
    :accent="accent"
    :submitting="submitting"
    @update:active-tab="activeTab = $event"
    @update:accent="accent = $event"
    @submit="saveProfile"
    @select-avatar="selectAvatar"
    @upload-avatar="uploadAvatar"
    @navigate="navigate"
  />
</template>
