<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
const visible = ref(false)
function update(): void { visible.value = window.scrollY > 480 }
function scrollTop(): void { window.scrollTo({ top: 0, behavior: 'smooth' }) }
onMounted(() => { update(); window.addEventListener('scroll', update, { passive: true }) })
onUnmounted(() => window.removeEventListener('scroll', update))
</script>
<template><Transition name="scroll-top"><button v-if="visible" id="button-up" class="scroll-top" type="button" aria-label="Наверх" @click="scrollTop">↑</button></Transition></template>
