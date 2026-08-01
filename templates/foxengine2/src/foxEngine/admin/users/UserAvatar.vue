<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  src?: string | null
  name?: string | null
  login?: string | null
  size?: 'small' | 'medium' | 'large'
}>(), {
  src: '',
  name: '',
  login: '',
  size: 'medium',
})

const failed = ref(false)
const normalizedSource = computed(() => String(props.src ?? '').trim())
const hasImage = computed(() => normalizedSource.value !== '' && !failed.value)
const displayName = computed(() => String(props.name || props.login || 'Пользователь').trim())
const initial = computed(() => Array.from(displayName.value)[0]?.toLocaleUpperCase('ru') ?? 'П')

watch(normalizedSource, () => { failed.value = false })
</script>

<template>
  <span
    class="admin-user-avatar"
    :class="`admin-user-avatar--${size}`"
    role="img"
    :aria-label="`Аватар пользователя ${displayName}`"
  >
    <img v-if="hasImage" :src="normalizedSource" :alt="displayName" @error="failed = true">
    <span v-else aria-hidden="true">{{ initial }}</span>
  </span>
</template>
