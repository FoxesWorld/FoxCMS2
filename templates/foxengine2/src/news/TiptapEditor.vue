<script setup lang="ts">
import { t } from '@/i18n'

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import type { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { TableKit } from '@tiptap/extension-table'
import TextAlign from '@tiptap/extension-text-align'
import { CharacterCount, Placeholder } from '@tiptap/extensions'
import EmoticonPicker from '@engine/emoticons/EmoticonPicker.vue'

const props = withDefaults(defineProps<{
  modelValue?: string
  disabled?: boolean
  placeholder?: string
  maximumLength?: number
}>(), {
  modelValue: '',
  disabled: false,
  placeholder: t('theme.news.tiptapeditor.034'),
  maximumLength: 100_000,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const sourceMode = ref(false)
const sourceHtml = ref('')
const fullscreen = ref(false)
const revision = ref(0)
const sourceInput = ref<HTMLTextAreaElement | null>(null)

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3, 4] },
      link: {
        openOnClick: false,
        autolink: true,
        defaultProtocol: 'https',
        HTMLAttributes: {
          rel: 'noopener noreferrer nofollow',
          target: '_blank',
        },
      },
    }),
    TableKit.configure({
      table: {
        resizable: true,
        HTMLAttributes: { class: 'tiptap-table' },
      },
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
      alignments: ['left', 'center', 'right', 'justify'],
    }),
    Placeholder.configure({ placeholder: props.placeholder }),
    CharacterCount.configure({
      limit: props.maximumLength,
      mode: 'textSize',
      autoTrim: false,
    }),
  ],
  editorProps: {
    attributes: {
      class: 'tiptap-editor__content',
      'aria-label': props.placeholder,
    },
  },
  onUpdate: ({ editor: instance }) => {
    emit('update:modelValue', instance.getHTML())
    revision.value += 1
  },
  onSelectionUpdate: () => { revision.value += 1 },
  onTransaction: () => { revision.value += 1 },
})

watch(() => props.modelValue, (value) => {
  const instance = editor.value
  if (!instance || sourceMode.value) return
  if (instance.getHTML() !== (value || '')) {
    instance.commands.setContent(value || '', { emitUpdate: false })
  }
})

watch(() => props.disabled, (disabled) => {
  editor.value?.setEditable(!disabled)
})

watch(sourceHtml, (value) => {
  if (sourceMode.value) emit('update:modelValue', value)
})

const characterCount = computed(() => {
  revision.value
  if (sourceMode.value) return sourceHtml.value.length
  return editor.value?.storage.characterCount.characters() ?? 0
})

const currentBlock = computed(() => {
  revision.value
  const instance = editor.value
  if (!instance) return 'paragraph'
  if (instance.isActive('heading', { level: 2 })) return 'h2'
  if (instance.isActive('heading', { level: 3 })) return 'h3'
  if (instance.isActive('heading', { level: 4 })) return 'h4'
  return 'paragraph'
})

function withEditor(action: (instance: Editor) => boolean): void {
  const instance = editor.value
  if (!instance || props.disabled) return
  action(instance)
}

function isActive(name: string, attributes?: Record<string, unknown>): boolean {
  revision.value
  return editor.value?.isActive(name, attributes) ?? false
}

function isAligned(alignment: string): boolean {
  return isActive('paragraph', { textAlign: alignment })
    || isActive('heading', { textAlign: alignment })
}

function setBlock(event: Event): void {
  const value = (event.target as HTMLSelectElement).value
  withEditor((instance) => {
    const chain = instance.chain().focus()
    if (value === 'h2') return chain.setHeading({ level: 2 }).run()
    if (value === 'h3') return chain.setHeading({ level: 3 }).run()
    if (value === 'h4') return chain.setHeading({ level: 4 }).run()
    return chain.setParagraph().run()
  })
}

function editLink(): void {
  const instance = editor.value
  if (!instance || props.disabled) return

  const previous = String(instance.getAttributes('link').href || '')
  const href = window.prompt(t('theme.news.tiptapeditor.035'), previous || 'https://')
  if (href === null) return

  const normalized = href.trim()
  if (!normalized) {
    instance.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  instance.chain().focus().extendMarkRange('link').setLink({ href: normalized }).run()
}

function insertEmoticon(shortcode: string): void {
  if (!sourceMode.value) {
    editor.value?.chain().focus().insertContent(shortcode).run()
    return
  }
  const input = sourceInput.value
  const start = input?.selectionStart ?? sourceHtml.value.length
  const end = input?.selectionEnd ?? start
  sourceHtml.value = `${sourceHtml.value.slice(0, start)}${shortcode}${sourceHtml.value.slice(end)}`
  void nextTick(() => {
    if (!input) return
    input.focus()
    const cursor = start + shortcode.length
    input.setSelectionRange(cursor, cursor)
  })
}

function toggleSourceMode(): void {
  const instance = editor.value
  if (!instance || props.disabled) return

  if (!sourceMode.value) {
    sourceHtml.value = instance.getHTML()
    sourceMode.value = true
    return
  }

  instance.commands.setContent(sourceHtml.value || '', { emitUpdate: true })
  sourceMode.value = false
  instance.commands.focus()
}

function toggleFullscreen(): void {
  fullscreen.value = !fullscreen.value
  document.body.classList.toggle('tiptap-editor-lock-scroll', fullscreen.value)
}

onBeforeUnmount(() => {
  document.body.classList.remove('tiptap-editor-lock-scroll')
  editor.value?.destroy()
})
</script>

<template>
  <div
    class="tiptap-field"
    :class="{
      'tiptap-field--disabled': disabled,
      'tiptap-field--fullscreen': fullscreen,
    }"
  >
    <div v-if="editor" class="tiptap-toolbar" role="toolbar" :aria-label="t('theme.news.tiptapeditor.001')">
      <div class="tiptap-toolbar__group">
        <select
          class="tiptap-toolbar__select"
          :value="currentBlock"
          :disabled="disabled || sourceMode"
          :aria-label="t('theme.news.tiptapeditor.002')"
          @change="setBlock"
        >
          <option value="paragraph">{{ t('theme.news.tiptapeditor.003') }}</option>
          <option value="h2">{{ t('theme.news.tiptapeditor.004') }}</option>
          <option value="h3">{{ t('theme.news.tiptapeditor.005') }}</option>
          <option value="h4">{{ t('theme.news.tiptapeditor.006') }}</option>
        </select>
      </div>

      <div class="tiptap-toolbar__group">
        <button type="button" :title="t('theme.news.tiptapeditor.007')" :aria-label="t('theme.news.tiptapeditor.007')" :class="{ 'is-active': isActive('bold') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().toggleBold().run())"><i class="fa-solid fa-bold" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.008')" :aria-label="t('theme.news.tiptapeditor.008')" :class="{ 'is-active': isActive('italic') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().toggleItalic().run())"><i class="fa-solid fa-italic" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.009')" :aria-label="t('theme.news.tiptapeditor.009')" :class="{ 'is-active': isActive('underline') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().toggleUnderline().run())"><i class="fa-solid fa-underline" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.010')" :aria-label="t('theme.news.tiptapeditor.010')" :class="{ 'is-active': isActive('strike') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().toggleStrike().run())"><i class="fa-solid fa-strikethrough" /></button>
      </div>

      <div class="tiptap-toolbar__group">
        <button type="button" :title="t('theme.news.tiptapeditor.011')" :aria-label="t('theme.news.tiptapeditor.011')" :class="{ 'is-active': isActive('bulletList') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().toggleBulletList().run())"><i class="fa-solid fa-list-ul" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.012')" :aria-label="t('theme.news.tiptapeditor.012')" :class="{ 'is-active': isActive('orderedList') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().toggleOrderedList().run())"><i class="fa-solid fa-list-ol" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.013')" :aria-label="t('theme.news.tiptapeditor.013')" :class="{ 'is-active': isActive('blockquote') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().toggleBlockquote().run())"><i class="fa-solid fa-quote-left" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.014')" :aria-label="t('theme.news.tiptapeditor.014')" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().setHorizontalRule().run())"><i class="fa-solid fa-minus" /></button>
      </div>

      <div class="tiptap-toolbar__group">
        <button type="button" :title="t('theme.news.tiptapeditor.015')" :aria-label="t('theme.news.tiptapeditor.015')" :class="{ 'is-active': isAligned('left') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().setTextAlign('left').run())"><i class="fa-solid fa-align-left" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.016')" :aria-label="t('theme.news.tiptapeditor.016')" :class="{ 'is-active': isAligned('center') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().setTextAlign('center').run())"><i class="fa-solid fa-align-center" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.017')" :aria-label="t('theme.news.tiptapeditor.017')" :class="{ 'is-active': isAligned('right') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().setTextAlign('right').run())"><i class="fa-solid fa-align-right" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.018')" :aria-label="t('theme.news.tiptapeditor.018')" :class="{ 'is-active': isAligned('justify') }" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().setTextAlign('justify').run())"><i class="fa-solid fa-align-justify" /></button>
      </div>

      <div class="tiptap-toolbar__group">
        <button type="button" :title="t('theme.news.tiptapeditor.019')" :aria-label="t('theme.news.tiptapeditor.019')" :class="{ 'is-active': isActive('link') }" :disabled="disabled || sourceMode" @click="editLink"><i class="fa-solid fa-link" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.020')" :aria-label="t('theme.news.tiptapeditor.020')" :disabled="disabled || sourceMode || !isActive('link')" @click="withEditor((instance) => instance.chain().focus().unsetLink().run())"><i class="fa-solid fa-link-slash" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.021')" :aria-label="t('theme.news.tiptapeditor.021')" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run())"><i class="fa-solid fa-table-cells" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.022')" :aria-label="t('theme.news.tiptapeditor.022')" :disabled="disabled || sourceMode || !isActive('table')" @click="withEditor((instance) => instance.chain().focus().addRowAfter().run())"><i class="fa-solid fa-table-rows" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.023')" :aria-label="t('theme.news.tiptapeditor.023')" :disabled="disabled || sourceMode || !isActive('table')" @click="withEditor((instance) => instance.chain().focus().addColumnAfter().run())"><i class="fa-solid fa-table-columns" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.024')" :aria-label="t('theme.news.tiptapeditor.024')" :disabled="disabled || sourceMode || !isActive('table')" @click="withEditor((instance) => instance.chain().focus().deleteTable().run())"><i class="fa-solid fa-table-list" /></button>
      </div>

      <div class="tiptap-toolbar__group">
        <EmoticonPicker :disabled="disabled" @select="insertEmoticon" />
      </div>

      <div class="tiptap-toolbar__group tiptap-toolbar__group--end">
        <button type="button" :title="t('theme.news.tiptapeditor.025')" :aria-label="t('theme.news.tiptapeditor.025')" :disabled="disabled || sourceMode" @click="withEditor((instance) => instance.chain().focus().clearNodes().unsetAllMarks().run())"><i class="fa-solid fa-eraser" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.026')" :aria-label="t('theme.news.tiptapeditor.026')" :disabled="disabled || sourceMode || !editor.can().undo()" @click="withEditor((instance) => instance.chain().focus().undo().run())"><i class="fa-solid fa-rotate-left" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.027')" :aria-label="t('theme.news.tiptapeditor.027')" :disabled="disabled || sourceMode || !editor.can().redo()" @click="withEditor((instance) => instance.chain().focus().redo().run())"><i class="fa-solid fa-rotate-right" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.028')" :aria-label="t('theme.news.tiptapeditor.028')" :class="{ 'is-active': sourceMode }" :disabled="disabled" @click="toggleSourceMode"><i class="fa-solid fa-code" /></button>
        <button type="button" :title="t('theme.news.tiptapeditor.029')" :aria-label="t('theme.news.tiptapeditor.029')" :class="{ 'is-active': fullscreen }" @click="toggleFullscreen"><i :class="fullscreen ? 'fa-solid fa-compress' : 'fa-solid fa-expand'" /></button>
      </div>
    </div>

    <div class="tiptap-editor__surface">
      <EditorContent v-if="editor && !sourceMode" :editor="editor" />
      <textarea
        v-else-if="sourceMode"
        ref="sourceInput"
        v-model="sourceHtml"
        class="tiptap-editor__source"
        :maxlength="maximumLength"
        spellcheck="false"
        :aria-label="t('theme.news.tiptapeditor.030')"
      />
      <div v-else class="tiptap-editor__loading" aria-live="polite">
        <i class="fa-solid fa-spinner" aria-hidden="true" />
        <span>{{ t('theme.news.tiptapeditor.031') }}</span>
      </div>
    </div>

    <footer class="tiptap-editor__status">
      <span>{{ sourceMode ? t('theme.news.tiptapeditor.032') : t('theme.news.tiptapeditor.033') }}</span>
      <span>{{ characterCount.toLocaleString('ru-RU') }} / {{ maximumLength.toLocaleString('ru-RU') }}</span>
    </footer>
  </div>
</template>
