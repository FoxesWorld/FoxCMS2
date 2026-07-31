<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import SkinSettingsPage from '@theme/userOptions/userOptions/SkinSettings.vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { toastFeedback } from '@/notifications/toasts'
import { bootstrapNumber, bootstrapString } from '@/domain/bootstrap'
import type { FeedbackMessage, SkinResource } from '@/contracts/user-pages'

interface ApiResponse extends FeedbackMessage {}
const router = useRouter()
const userUuid = bootstrapString(appBootstrap, 'uuid')
const group = bootstrapNumber(appBootstrap, 'user_group', 5)
const isGuest = group === 5 || userUuid === ''
const frontPreview = ref('')
const backPreview = ref('')
const loadingPreview = ref(false)
const selected = ref<Record<SkinResource, File | null>>({ skin: null, cloak: null })
const busy = ref<SkinResource | null>(null)
const feedback = ref<ApiResponse | null>(null)

async function refreshPreview(): Promise<void> {
  if (isGuest) return
  loadingPreview.value = true
  try {
    const [front, back] = await Promise.all([
      foxesApi.postText({ sysRequest: 'skinPreview', userUuid, side: 'front' }),
      foxesApi.postText({ sysRequest: 'skinPreview', userUuid, side: 'back' }),
    ])
    frontPreview.value = `data:image/png;base64,${front.trim()}`
    backPreview.value = `data:image/png;base64,${back.trim()}`
  } catch (error) {
    console.error('[FoxesCraft] Skin preview failed', error)
    feedback.value = { type: 'error', message: 'Не удалось построить предпросмотр.' }
  } finally {
    loadingPreview.value = false
  }
}

function selectFile(type: SkinResource, event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  feedback.value = null
  if (file && file.type !== 'image/png') {
    feedback.value = toastFeedback({ type: 'error', message: 'Для скина и плаща требуется PNG.' })
    input.value = ''
    selected.value[type] = null
    return
  }
  selected.value[type] = file
}

async function upload(type: SkinResource): Promise<void> {
  const file = selected.value[type]
  if (!file) return
  busy.value = type
  feedback.value = null
  try {
    const data = new FormData()
    data.set('0', file)
    data.set('sysRequest', 'uploadFile')
    data.set('type', type)
    data.set('userUuid', userUuid)
    feedback.value = await foxesApi.postFormData<ApiResponse>(data)
    selected.value[type] = null
    await refreshPreview()
  } catch (error) {
    console.error('[FoxesCraft] Skin upload failed', error)
    feedback.value = { type: 'error', message: 'Не удалось загрузить файл.' }
  } finally {
    busy.value = null
  }
}

async function remove(type: SkinResource): Promise<void> {
  busy.value = type
  feedback.value = null
  try {
    feedback.value = await foxesApi.post<ApiResponse>({ sysRequest: 'deleteFile', type, userUuid })
    await refreshPreview()
  } catch (error) {
    console.error('[FoxesCraft] Skin removal failed', error)
    feedback.value = { type: 'error', message: 'Не удалось удалить файл.' }
  } finally {
    busy.value = null
  }
}

function navigate(route: string): void { void router.push({ name: route }) }
onMounted(() => void refreshPreview())
</script>

<template>
  <SkinSettingsPage
    :is-guest="isGuest"
    :viewer-group="group"
    :front-preview="frontPreview"
    :back-preview="backPreview"
    :loading-preview="loadingPreview"
    :selected-skin-name="selected.skin?.name ?? ''"
    :selected-cloak-name="selected.cloak?.name ?? ''"
    :busy="busy"
    :feedback="feedback"
    @select="selectFile"
    @upload="upload"
    @remove="remove"
    @navigate="navigate"
  />
</template>
