<script setup lang="ts">
import { t } from '@/i18n'

import CodeMirror from 'codemirror'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/material-darker.css'
import 'codemirror/theme/eclipse.css'
import 'codemirror/addon/dialog/dialog.css'
import 'codemirror/addon/fold/foldgutter.css'
import 'codemirror/addon/hint/show-hint.css'
import 'codemirror/addon/display/fullscreen.css'

import 'codemirror/mode/xml/xml'
import 'codemirror/mode/javascript/javascript'
import 'codemirror/mode/css/css'
import 'codemirror/mode/htmlmixed/htmlmixed'
import 'codemirror/mode/sql/sql'
import 'codemirror/mode/markdown/markdown'

import 'codemirror/addon/edit/closebrackets'
import 'codemirror/addon/edit/closetag'
import 'codemirror/addon/edit/matchbrackets'
import 'codemirror/addon/edit/matchtags'
import 'codemirror/addon/selection/active-line'
import 'codemirror/addon/comment/comment'
import 'codemirror/addon/dialog/dialog'
import 'codemirror/addon/search/searchcursor'
import 'codemirror/addon/search/search'
import 'codemirror/addon/fold/foldcode'
import 'codemirror/addon/fold/foldgutter'
import 'codemirror/addon/fold/brace-fold'
import 'codemirror/addon/fold/xml-fold'
import 'codemirror/addon/fold/comment-fold'
import 'codemirror/addon/hint/show-hint'
import 'codemirror/addon/hint/html-hint'
import 'codemirror/addon/hint/javascript-hint'
import 'codemirror/addon/hint/css-hint'
import 'codemirror/addon/hint/sql-hint'
import 'codemirror/addon/display/fullscreen'

export type CodeEditorLanguage =
  | 'html'
  | 'xml'
  | 'json'
  | 'css'
  | 'javascript'
  | 'typescript'
  | 'sql'
  | 'markdown'
  | 'plaintext'

const props = withDefaults(defineProps<{
  modelValue: string
  language?: CodeEditorLanguage
  disabled?: boolean
  ariaLabel?: string
  minHeight?: string
}>(), {
  language: 'plaintext',
  disabled: false,
  ariaLabel: t('theme.foxengine.editor.codeeditor.003'),
  minHeight: '280px',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: []
}>()

const editorVersion = CodeMirror.version
const languageLabel = computed(() => props.language === 'plaintext' ? 'Plain text' : props.language.toUpperCase())

const host = ref<HTMLDivElement | null>(null)
let editor: CodeMirror.Editor | null = null
let themeObserver: MutationObserver | null = null
let applyingExternalValue = false

function isDarkTheme(): boolean {
  return document.documentElement.dataset.theme === 'dark'
}

function editorTheme(): string {
  return isDarkTheme() ? 'material-darker' : 'eclipse'
}

function editorMode(language: CodeEditorLanguage): CodeMirror.EditorConfiguration['mode'] {
  switch (language) {
    case 'html': return 'htmlmixed'
    case 'xml': return 'xml'
    case 'json': return { name: 'javascript', json: true }
    case 'css': return 'css'
    case 'javascript': return 'javascript'
    case 'typescript': return { name: 'javascript', typescript: true }
    case 'sql': return 'text/x-mysql'
    case 'markdown': return 'markdown'
    default: return null
  }
}

function updateAccessibility(): void {
  if (!editor) return
  const input = editor.getInputField()
  input.setAttribute('aria-label', props.ariaLabel)
  input.setAttribute('spellcheck', 'false')
  input.setAttribute('autocapitalize', 'off')
  input.setAttribute('autocomplete', 'off')
}

function updateSize(): void {
  if (!editor) return
  editor.setSize('100%', props.minHeight)
  editor.refresh()
}

function onEditorChange(instance: CodeMirror.Editor): void {
  if (!applyingExternalValue) emit('update:modelValue', instance.getValue())
}

function onEditorBlur(): void {
  emit('blur')
}

function foldAtCursor(instance: CodeMirror.Editor): void {
  const foldable = instance as CodeMirror.Editor & {
    foldCode(position: CodeMirror.Position): void
  }
  foldable.foldCode(instance.getCursor())
}

function toggleFullscreen(instance: CodeMirror.Editor): void {
  instance.setOption('fullScreen', !instance.getOption('fullScreen'))
}

function exitFullscreen(instance: CodeMirror.Editor): void | typeof CodeMirror.Pass {
  if (!instance.getOption('fullScreen')) return CodeMirror.Pass
  instance.setOption('fullScreen', false)
}

onMounted(() => {
  if (!host.value) return

  editor = CodeMirror(host.value, {
    value: props.modelValue,
    mode: editorMode(props.language),
    theme: editorTheme(),
    readOnly: props.disabled ? 'nocursor' : false,
    lineNumbers: true,
    lineWrapping: true,
    styleActiveLine: true,
    matchBrackets: true,
    autoCloseBrackets: true,
    autoCloseTags: true,
    matchTags: { bothTags: true },
    foldGutter: true,
    gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
    indentUnit: 2,
    tabSize: 2,
    indentWithTabs: false,
    smartIndent: true,
    electricChars: true,
    showCursorWhenSelecting: true,
    cursorScrollMargin: 8,
    viewportMargin: 20,
    extraKeys: {
      'Ctrl-Space': 'autocomplete',
      'Ctrl-Q': foldAtCursor,
      'Ctrl-/': 'toggleComment',
      'Cmd-/': 'toggleComment',
      'F11': toggleFullscreen,
      'Esc': exitFullscreen,
    },
  })

  editor.on('change', onEditorChange)
  editor.on('blur', onEditorBlur)
  updateAccessibility()
  updateSize()

  themeObserver = new MutationObserver(() => {
    editor?.setOption('theme', editorTheme())
    editor?.refresh()
  })
  themeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme'],
  })
})

watch(() => props.modelValue, (value) => {
  if (!editor || value === editor.getValue()) return

  const cursor = editor.getCursor()
  const scroll = editor.getScrollInfo()
  applyingExternalValue = true
  editor.setValue(value)
  const lastLine = Math.max(0, editor.lineCount() - 1)
  editor.setCursor({
    line: Math.min(cursor.line, lastLine),
    ch: cursor.ch,
  })
  editor.scrollTo(scroll.left, scroll.top)
  applyingExternalValue = false
})

watch(() => props.language, (language) => {
  editor?.setOption('mode', editorMode(language))
  editor?.refresh()
})

watch(() => props.disabled, (disabled) => {
  editor?.setOption('readOnly', disabled ? 'nocursor' : false)
})

watch(() => props.ariaLabel, updateAccessibility)
watch(() => props.minHeight, updateSize)

onBeforeUnmount(() => {
  themeObserver?.disconnect()
  themeObserver = null

  if (editor) {
    editor.off('change', onEditorChange)
    editor.off('blur', onEditorBlur)
    editor.getWrapperElement().remove()
  }
  editor = null
})
</script>

<template>
  <div
    class="code-editor-shell"
    :class="{ 'code-editor-shell--disabled': disabled }"
    :data-codemirror-version="editorVersion"
    :data-language="language"
  >
    <header class="code-editor-toolbar">
      <span><i aria-hidden="true" />{{ t('theme.foxengine.editor.codeeditor.001') }} <b>{{ editorVersion }}</b></span>
      <small>{{ languageLabel }} {{ t('theme.foxengine.editor.codeeditor.002') }}</small>
    </header>
    <div ref="host" class="code-editor code-editor--codemirror5" />
  </div>
</template>

<style scoped>
.code-editor-shell {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-input);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.code-editor-shell:focus-within {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-accent) 18%, transparent);
}
.code-editor-shell--disabled {
  cursor: not-allowed;
  opacity: .72;
}
.code-editor-toolbar {
  min-height: 38px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 7px 11px;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text-muted);
  background: var(--color-surface-soft);
  font-family: var(--font-mono);
  font-size: .72rem;
}
.code-editor-toolbar span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--color-text);
  white-space: nowrap;
}
.code-editor-toolbar i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-success);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-success) 17%, transparent);
}
.code-editor-toolbar b { color: var(--color-accent-bright); }
.code-editor-toolbar small {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.code-editor {
  min-width: 0;
  overflow: hidden;
  background: var(--color-input);
}
.code-editor :deep(.CodeMirror) {
  width: 100%;
  height: 100%;
  color: var(--color-text);
  background: var(--color-input);
  font-family: var(--font-mono);
  font-size: 13px;
  line-height: 1.58;
}
.code-editor :deep(.CodeMirror-scroll) {
  min-height: inherit;
}
.code-editor :deep(.CodeMirror-gutters) {
  border-right-color: var(--color-border);
}
.code-editor :deep(.CodeMirror-linenumber) {
  color: var(--color-text-muted);
}
.code-editor :deep(.CodeMirror-selected) {
  background: var(--color-accent-soft) !important;
}
.code-editor :deep(.CodeMirror-focused .CodeMirror-selected) {
  background: color-mix(in srgb, var(--color-accent) 28%, transparent) !important;
}
.code-editor :deep(.CodeMirror-cursor) {
  border-left-color: var(--color-accent-bright);
}
.code-editor :deep(.CodeMirror-activeline-background) {
  background: var(--color-surface-hover);
}
.code-editor :deep(.CodeMirror-matchingbracket),
.code-editor :deep(.CodeMirror-matchingtag) {
  color: var(--color-success) !important;
  background: color-mix(in srgb, var(--color-success) 16%, transparent);
  outline: 1px solid color-mix(in srgb, var(--color-success) 40%, transparent);
}
.code-editor :deep(.CodeMirror-nonmatchingbracket) {
  color: var(--color-danger) !important;
}
.code-editor :deep(.CodeMirror-foldmarker) {
  color: var(--color-accent-bright);
  text-shadow: none;
}
@media (max-width: 700px) {
  .code-editor-toolbar { align-items: flex-start; flex-direction: column; gap: 3px; }
  .code-editor-toolbar small { width: 100%; }
}
:global(.CodeMirror-fullscreen) {
  z-index: 1000;
}
</style>
