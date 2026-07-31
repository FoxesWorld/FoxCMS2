<script setup lang="ts">
import { computed, onUnmounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import ProfileSettingsPage from '@theme/userOptions/userOptions/ProfileSettings.vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { toastFeedback } from '@/notifications/toasts'
import { bootstrapNumber, bootstrapString, themeAsset } from '@/domain/bootstrap'
import type { FeedbackMessage, ProfileSettingsFormModel, SettingsTab } from '@/contracts/user-pages'

interface ApiResponse extends FeedbackMessage { type: string; message: string; url?: string }
const router = useRouter()
const login = bootstrapString(appBootstrap, 'login')
const group = bootstrapNumber(appBootstrap, 'user_group', 5)
const isGuest = group === 5 || login === '' || login === 'anonymous'
const isAdmin = group === 1
const activeTab = ref<SettingsTab>('profile')
const submitting = ref(false)
const uploading = ref(false)
const feedback = ref<ApiResponse | null>(null)
const photoFeedback = ref<ApiResponse | null>(null)
const avatarFile = ref<File | null>(null)
let objectUrl = ''
const avatarPreview = ref(bootstrapString(appBootstrap, 'profilePhoto', themeAsset(appBootstrap, 'img/no-photo.jpg')))
const configuredAccent = bootstrapString(appBootstrap, 'colorScheme')
const accent = ref(/^#[0-9a-f]{6}$/i.test(configuredAccent) ? configuredAccent : '#5bd08b')
const form = reactive<ProfileSettingsFormModel>({
  login,
  realname: bootstrapString(appBootstrap, 'realname'),
  userStatus: bootstrapString(appBootstrap, 'userStatus'),
  land: bootstrapString(appBootstrap, 'land'),
  email: bootstrapString(appBootstrap, 'email'),
  currentPassword: '',
  newPassword: '',
  repeatPassword: '',
})
const hasPasswordChange = computed(() => form.newPassword !== '' || form.repeatPassword !== '')

function selectAvatar(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  photoFeedback.value = null
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024) {
    photoFeedback.value = toastFeedback({ type: 'error', message: 'Нужен JPEG, PNG, WebP или GIF размером до 5 МБ.' })
    input.value = ''
    return
  }
  avatarFile.value = file
  if (objectUrl) URL.revokeObjectURL(objectUrl)
  objectUrl = URL.createObjectURL(file)
  avatarPreview.value = objectUrl
}

async function uploadAvatar(): Promise<void> {
  if (!avatarFile.value) return
  uploading.value = true
  photoFeedback.value = null
  try {
    const data = new FormData()
    data.set('user_doaction', 'updateProfilePhoto')
    data.set('image', avatarFile.value)
    const response = await foxesApi.postFormData<ApiResponse>(data)
    photoFeedback.value = response
    if (response.type === 'success' && response.url) {
      avatarPreview.value = response.url
      avatarFile.value = null
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = '' }
    }
  } catch (error) {
    console.error('[FoxesCraft] Avatar upload failed', error)
    photoFeedback.value = { type: 'error', message: 'Не удалось загрузить фото.' }
  } finally {
    uploading.value = false
  }
}

async function saveProfile(): Promise<void> {
  feedback.value = null
  if (!isAdmin && !form.currentPassword) {
    feedback.value = toastFeedback({ type: 'error', message: 'Для сохранения нужен текущий пароль.' })
    return
  }
  if (hasPasswordChange.value && form.newPassword !== form.repeatPassword) {
    feedback.value = toastFeedback({ type: 'error', message: 'Новые пароли не совпадают.' })
    activeTab.value = 'security'
    return
  }
  if (hasPasswordChange.value && form.newPassword.length < 10) {
    feedback.value = toastFeedback({ type: 'error', message: 'Новый пароль должен содержать минимум 10 символов.' })
    activeTab.value = 'security'
    return
  }

  submitting.value = true
  try {
    const response = await foxesApi.post<ApiResponse>({
      user_doaction: 'EditUser',
      login: form.login.trim(),
      user_group: group,
      realname: form.realname.trim(),
      userStatus: form.userStatus.trim(),
      land: form.land.trim(),
      email: form.email.trim(),
      colorScheme: accent.value,
      password: form.currentPassword,
      newPass: form.newPassword,
      repeatPass: form.repeatPassword,
      refreshPage: false,
    })
    feedback.value = response
    if (response.type === 'success') {
      form.currentPassword = ''
      form.newPassword = ''
      form.repeatPassword = ''
    }
  } catch (error) {
    console.error('[FoxesCraft] Profile update failed', error)
    feedback.value = { type: 'error', message: 'Не удалось сохранить профиль.' }
  } finally {
    submitting.value = false
  }
}

function navigate(route: string): void {
  if (route === 'profile') {
    void router.push({ name: 'profile', params: { value: form.login } })
    return
  }
  void router.push({ name: route })
}

onUnmounted(() => { if (objectUrl) URL.revokeObjectURL(objectUrl) })
</script>

<template>
  <ProfileSettingsPage
    :is-guest="isGuest"
    :viewer-group="group"
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
