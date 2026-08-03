<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { emoticonIndex, tokenizeEmoticons } from './catalog'
import type { EmoticonDefinition } from './types'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  text?: string | null
  tag?: string
}>(), {
  text: '',
  tag: 'span',
})

const index = ref<ReadonlyMap<string, EmoticonDefinition>>(new Map())
const parts = computed(() => tokenizeEmoticons(props.text ?? '', index.value))

onMounted(() => {
  void emoticonIndex().then((value) => { index.value = value }).catch(() => undefined)
})
</script>

<template>
  <component :is="tag" v-bind="$attrs">
    <template v-for="(part, indexValue) in parts" :key="indexValue">
      <img
        v-if="part.type === 'emoticon'"
        class="fox-emoticon"
        :src="part.value.url"
        :alt="part.value.shortcode"
        :title="part.value.shortcode"
        :width="part.value.width"
        :height="part.value.height"
        loading="lazy"
        decoding="async"
        draggable="false"
        :data-emoticon="part.value.name"
      >
      <template v-else>{{ part.value }}</template>
    </template>
  </component>
</template>
