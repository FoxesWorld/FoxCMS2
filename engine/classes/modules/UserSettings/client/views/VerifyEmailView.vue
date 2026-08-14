<script setup lang="ts">
import { t } from '@/i18n'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { foxesApi } from '@/api'
import { appBootstrap } from '@/app/context'
import { bootstrapString } from '@/domain/bootstrap'
import type { FeedbackMessage } from '@/contracts/user-pages'

const route = useRoute()
const router = useRouter()
const busy = ref(true)
const feedback = ref<FeedbackMessage | null>(null)
const loggedIn = bootstrapString(appBootstrap, 'uuid') !== ''
const login = bootstrapString(appBootstrap, 'login')

async function verify(): Promise<void> {
  const token = typeof route.query.token === 'string' ? route.query.token.trim() : ''
  if (!token) {
    busy.value = false
    feedback.value = { type: 'error', message: t('modules.usersettings.verifyemailview.004') }
    return
  }

  busy.value = true
  try {
    feedback.value = await foxesApi.post<FeedbackMessage>({
      user_doaction: 'verifyEmail',
      token,
    })
  } catch {
    feedback.value = { type: 'error', message: t('modules.usersettings.verifyemailview.005') }
  } finally {
    busy.value = false
  }
}

function continueToAccount(): void {
  if (loggedIn && login) {
    void router.push({ name: 'profile', params: { value: login } })
    return
  }
  void router.push({ name: 'auth' })
}

onMounted(() => void verify())
</script>

<template>
  <article class="content-surface settings-page email-verification-page">
    <header>
      <span class="eyebrow">{{ t('modules.usersettings.verifyemailview.001') }}</span>
      <h1>{{ t('modules.usersettings.verifyemailview.002') }}</h1>
      <p class="lead">{{ t('modules.usersettings.verifyemailview.003') }}</p>
    </header>

    <div v-if="busy" class="content-skeleton" :aria-label="t('modules.usersettings.verifyemailview.006')">
      <span /><span /><span />
    </div>
    <div
      v-else-if="feedback"
      class="system-message"
      :class="feedback.type === 'success' ? 'system-message--success' : 'system-message--error'"
      role="status"
    >
      <strong>{{ feedback.type === 'success' ? t('modules.usersettings.verifyemailview.007') : t('modules.usersettings.verifyemailview.008') }}</strong>
      <p>{{ feedback.message }}</p>
      <button class="button button--primary" type="button" @click="continueToAccount">
        {{ loggedIn ? t('modules.usersettings.verifyemailview.009') : t('modules.usersettings.verifyemailview.010') }}
      </button>
    </div>
  </article>
</template>
