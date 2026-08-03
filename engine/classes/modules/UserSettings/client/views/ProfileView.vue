<script setup lang="ts">
import { t } from '@/i18n'

import { computed, ref, watch } from 'vue'
import ProfilePage from '@theme/userOptions/userOptions/Profile.vue'
import { appBootstrap } from '@/app/context'
import { foxesApi } from '@/api'
import { loadBadges, type BadgeDefinition } from '@/content/contentData'
import { bootstrapString, themeAsset } from '@/domain/bootstrap'
import { balanceCurrencyIconPath, formatBalanceAmount, normalizeBalanceMatrix } from '@/domain/userBalance'
import type { ProfileBadge, ProfileEntry, ProfileRecord } from '@/contracts/user-pages'

interface Props { value: string }
interface UserProfile extends ProfileRecord { error?: string; message?: string }
interface PhotoResponse { url?: string }
interface BadgeAssignment {
  badgeName: string
  acquiredAt?: number | string | null
  description?: string
}

const props = defineProps<Props>()
const loading = ref(true)
const error = ref('')
const profile = ref<UserProfile | null>(null)
const badgeRegistry = ref<readonly BadgeDefinition[]>([])
const viewerGroupTag = bootstrapString(appBootstrap, 'groupTag', 'guest')
const viewerUuid = bootstrapString(appBootstrap, 'uuid')
const canAdministerUsers = appBootstrap.frontend.capabilities.includes('admin.panel')
const photoDialogOpen = ref(false)
const photoUploading = ref(false)
const photoError = ref('')

function parseUnknown(value: unknown): unknown {
  if (typeof value !== 'string') return value
  try { return JSON.parse(value) } catch { return value }
}

function normalizeKey(value: string): string {
  return value.normalize('NFKC').toLocaleLowerCase('ru').replace(/[^a-zа-яё0-9]+/giu, '')
}

function formatDate(value?: string | number | null): string {
  if (!value) return t('modules.usersettings.profileview.001')
  const numeric = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value
  const date = new Date(typeof numeric === 'number' && numeric < 10_000_000_000 ? numeric * 1000 : numeric)
  return Number.isNaN(date.getTime())
    ? t('modules.usersettings.profileview.001')
    : new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'long', year: 'numeric' }).format(date)
}

function formatDuration(seconds: number): string {
  const safe = Math.max(0, Math.floor(seconds))
  const hours = Math.floor(safe / 3600)
  const minutes = Math.floor((safe % 3600) / 60)
  if (hours > 0) return t('modules.usersettings.profileview.002', [hours.toLocaleString('ru'), minutes])
  if (minutes > 0) return t('modules.usersettings.profileview.003', [minutes])
  return t('modules.usersettings.profileview.004', [safe])
}

function badgeAssignments(value: unknown): BadgeAssignment[] {
  const parsed = parseUnknown(value)
  const source = Array.isArray(parsed) ? parsed : parsed ? [parsed] : []
  return source.flatMap((item): BadgeAssignment[] => {
    if (typeof item === 'string' || typeof item === 'number') {
      const badgeName = String(item).trim()
      return badgeName ? [{ badgeName }] : []
    }
    if (!item || typeof item !== 'object') return []
    const record = item as Record<string, unknown>
    const directName = record.badgeName ?? record.id ?? record.name ?? record.title
    if (typeof directName === 'string' && directName.trim()) {
      return [{
        badgeName: directName.trim(),
        acquiredAt: record.acquiredDate as number | string | null | undefined
          ?? record.acquiredAt as number | string | null | undefined
          ?? record.date as number | string | null | undefined,
        description: typeof record.description === 'string' ? record.description.trim() : undefined,
      }]
    }
    return Object.entries(record).flatMap(([badgeName, acquiredAt]) => {
      if (!badgeName.trim()) return []
      return [{ badgeName, acquiredAt: acquiredAt as number | string | null }]
    })
  })
}

function serverEntries(value: unknown): ProfileEntry[] {
  const parsed = parseUnknown(value)
  let rows: Array<[string, unknown]> = []
  if (Array.isArray(parsed)) {
    rows = parsed.map((item, index) => {
      if (item && typeof item === 'object') {
        const record = item as Record<string, unknown>
        return [String(record.serverName ?? record.name ?? index + 1), record]
      }
      return [String(index + 1), item]
    })
  } else if (parsed && typeof parsed === 'object') {
    const record = parsed as Record<string, unknown>
    const servers = record.servers
    rows = servers && typeof servers === 'object' && !Array.isArray(servers)
      ? Object.entries(servers as Record<string, unknown>)
      : Object.entries(record)
  }

  return rows.map(([serverName, raw]) => {
    if (!raw || typeof raw !== 'object') return { label: serverName, value: String(raw ?? t('modules.usersettings.profileview.001')) }
    const entry = raw as Record<string, unknown>
    const totalTime = Number(entry.totalTime ?? entry.playTime ?? entry.seconds ?? 0)
    const lastPlayed = entry.lastPlayed as number | string | undefined
    return {
      label: serverName,
      value: Number.isFinite(totalTime) && totalTime > 0 ? formatDuration(totalTime) : t('modules.usersettings.profileview.005'),
      detail: lastPlayed ? t('modules.usersettings.profileview.006', [formatDate(lastPlayed)]) : undefined,
    }
  }).filter((entry) => entry.label !== 'version')
}

const balances = computed<ProfileEntry[]>(() => normalizeBalanceMatrix(profile.value?.balance).currencies.map((currency) => ({
  label: currency.name,
  value: `${formatBalanceAmount(currency.amount)} ${currency.symbol}`,
  detail: currency.primary ? t('modules.usersettings.profileview.007') : t('modules.usersettings.profileview.008'),
  icon: themeAsset(appBootstrap, balanceCurrencyIconPath(currency.code)),
  kind: currency.code,
})))
const servers = computed(() => serverEntries(profile.value?.serversOnline))
const badges = computed<ProfileBadge[]>(() => {
  const definitions = new Map<string, BadgeDefinition>()
  for (const definition of badgeRegistry.value) {
    const keys = [definition.id, definition.title]
    if (definition.image) {
      const filename = definition.image.split('/').pop()?.replace(/\.[^.]+$/, '')
      if (filename) keys.push(filename)
    }
    for (const key of keys) definitions.set(normalizeKey(key), definition)
  }

  const seen = new Set<string>()
  return badgeAssignments(profile.value?.badges).flatMap((assignment) => {
    const definition = definitions.get(normalizeKey(assignment.badgeName))
    const id = definition?.id ?? assignment.badgeName.trim().toLocaleLowerCase('ru')
    const identity = `${normalizeKey(id)}:${String(assignment.acquiredAt ?? '')}`
    if (seen.has(identity)) return []
    seen.add(identity)
    return [{
      id,
      title: definition?.title ?? assignment.badgeName,
      description: assignment.description || definition?.description || t('modules.usersettings.profileview.009'),
      image: definition?.image ?? null,
      acquiredAt: assignment.acquiredAt,
      acquiredLabel: assignment.acquiredAt ? formatDate(assignment.acquiredAt) : undefined,
    }]
  })
})
const accent = computed(() => /^#[0-9a-f]{3,8}$/i.test(profile.value?.colorScheme ?? '') ? profile.value?.colorScheme ?? '#5bd08b' : '#5bd08b')
const registration = computed(() => formatDate(profile.value?.reg_date))
const lastActivity = computed(() => formatDate(profile.value?.last_date))
const isOwner = computed(() => viewerUuid !== '' && viewerUuid.replaceAll('-', '').toLowerCase() === (profile.value?.uuid ?? '').replaceAll('-', '').toLowerCase())
const canEditUser = canAdministerUsers
const canEditPhoto = computed(() => Boolean(profile.value?.uuid) && (isOwner.value || canEditUser))

function openPhotoDialog(): void {
  if (!canEditPhoto.value || !profile.value) return
  photoError.value = ''
  photoDialogOpen.value = true
}

function closePhotoDialog(): void {
  if (photoUploading.value) return
  photoDialogOpen.value = false
  photoError.value = ''
}

async function uploadPhoto(file: File): Promise<void> {
  if (!profile.value?.uuid || !canEditPhoto.value) return
  photoUploading.value = true
  photoError.value = ''
  try {
    const data = new FormData()
    data.set('user_doaction', 'updateProfilePhoto')
    data.set('userUuid', profile.value.uuid)
    data.set('image', file)
    const response = await foxesApi.postFormData<PhotoResponse>(data)
    if (response.url) {
      profile.value = { ...profile.value, profilePhoto: response.url }
      photoDialogOpen.value = false
    }
  } catch (requestError) {
    console.error('[FoxesCraft] Profile photo upload failed', requestError)
    photoError.value = t('modules.usersettings.profileview.010')
  } finally {
    photoUploading.value = false
  }
}

async function loadProfile(value: string): Promise<void> {
  const login = decodeURIComponent(value).trim()
  loading.value = true
  error.value = ''
  profile.value = null
  if (!/^[\p{L}\p{N}_.-]{1,64}$/u.test(login)) {
    error.value = t('modules.usersettings.profileview.011')
    loading.value = false
    return
  }
  try {
    const [response, definitions] = await Promise.all([
      foxesApi.post<UserProfile>({ user_doaction: 'getUserData', login }),
      loadBadges().catch((requestError) => {
        console.warn('[FoxesCraft] Badge registry unavailable', requestError)
        return [] as readonly BadgeDefinition[]
      }),
    ])
    badgeRegistry.value = definitions
    if (response.error || !response.login) error.value = response.error || response.message || t('modules.usersettings.profileview.012')
    else profile.value = response
  } catch (requestError) {
    console.error('[FoxesCraft] Profile request failed', requestError)
    error.value = t('modules.usersettings.profileview.013')
  } finally {
    loading.value = false
  }
}

watch(() => props.value, (value) => {
  photoDialogOpen.value = false
  photoError.value = ''
  void loadProfile(value)
}, { immediate: true })
</script>

<template>
  <ProfilePage
    :loading="loading"
    :error="error"
    :profile="profile"
    :viewer-group-tag="viewerGroupTag"
    :is-owner="isOwner"
    :can-edit-photo="canEditPhoto"
    :can-edit-user="canEditUser"
    :photo-dialog-open="photoDialogOpen"
    :photo-uploading="photoUploading"
    :photo-error="photoError"
    :accent="accent"
    :registration="registration"
    :last-activity="lastActivity"
    :balances="balances"
    :badges="badges"
    :servers="servers"
    @edit-photo="openPhotoDialog"
    @close-photo="closePhotoDialog"
    @upload-photo="uploadPhoto"
  />
</template>
