<script setup lang="ts">
import { t } from '@/i18n'

const props = defineProps<{ selectedName: string; selectedSize: number; busy: boolean; locked: boolean }>()
const emit = defineEmits<{ select: [event: Event]; upload: []; remove: [] }>()

function formatBytes(value: number): string {
  if (value < 1024) return t('theme.useroptions.useroptions.profile.options.cloakoption.010', [value])
  return t('theme.useroptions.useroptions.profile.options.cloakoption.011', [(value / 1024).toFixed(value >= 10240 ? 0 : 1)])
}
</script>

<template>
  <section class="minecraft-resource-card minecraft-resource-card--cloak">
    <header>
      <span class="minecraft-resource-card__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true" /></span>
      <div><strong>{{ t('theme.useroptions.useroptions.profile.options.cloakoption.001') }}</strong><small>{{ t('theme.useroptions.useroptions.profile.options.cloakoption.002') }}</small></div>
    </header>
    <ul>
      <li>{{ t('theme.useroptions.useroptions.profile.options.cloakoption.003') }}</li>
      <li>{{ t('theme.useroptions.useroptions.profile.options.cloakoption.004') }}</li>
      <li>{{ t('theme.useroptions.useroptions.profile.options.cloakoption.005') }}</li>
    </ul>
    <label class="file-button minecraft-resource-card__picker" :class="{ 'has-file': selectedName }">
      <input type="file" accept="image/png" :disabled="busy || locked" @change="emit('select', $event)">
      <i class="fa-solid fa-folder-open" aria-hidden="true" />
      <span>{{ selectedName || t('theme.useroptions.useroptions.profile.options.cloakoption.006') }}</span>
      <small v-if="selectedName">{{ formatBytes(selectedSize) }}</small>
    </label>
    <div class="minecraft-resource-card__actions">
      <button class="button button--primary" type="button" :disabled="!selectedName || busy || locked" @click="emit('upload')">
        {{ busy ? t('theme.useroptions.useroptions.profile.options.cloakoption.007') : t('theme.useroptions.useroptions.profile.options.cloakoption.008') }}
      </button>
      <button class="button button--ghost" type="button" :disabled="busy || locked" @click="emit('remove')">{{ t('theme.useroptions.useroptions.profile.options.cloakoption.009') }}</button>
    </div>
  </section>
</template>
