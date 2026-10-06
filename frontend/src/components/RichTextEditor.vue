<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import { Bold, Heading3, Italic, List, ListOrdered, Quote, Redo2, Underline, Undo2 } from 'lucide-vue-next'

const props = withDefaults(defineProps<{ modelValue: string; placeholder?: string }>(), { placeholder: 'Write a detailed description...' })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const editor = ref<HTMLDivElement | null>(null)

function syncFromModel() {
  if (editor.value && editor.value.innerHTML !== props.modelValue) editor.value.innerHTML = props.modelValue
}
function update() { emit('update:modelValue', editor.value?.innerHTML ?? '') }
function run(command: string, value?: string) {
  editor.value?.focus()
  document.execCommand(command, false, value)
  update()
}
function pastePlainText(event: ClipboardEvent) {
  event.preventDefault()
  const text = event.clipboardData?.getData('text/plain') ?? ''
  document.execCommand('insertText', false, text)
  update()
}

watch(() => props.modelValue, () => nextTick(syncFromModel))
onMounted(syncFromModel)
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-border-gray bg-surface focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
    <div class="flex flex-wrap items-center gap-0.5 border-b border-border-gray bg-canvas/60 px-2 py-1.5">
      <button type="button" class="btn-icon h-7 w-7" title="Bold" @click="run('bold')"><Bold class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" title="Italic" @click="run('italic')"><Italic class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" title="Underline" @click="run('underline')"><Underline class="h-3.5 w-3.5" /></button>
      <span class="mx-1 h-4 border-l border-border-gray"></span>
      <button type="button" class="btn-icon h-7 w-7" title="Heading" @click="run('formatBlock', 'h3')"><Heading3 class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" title="Bulleted list" @click="run('insertUnorderedList')"><List class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" title="Numbered list" @click="run('insertOrderedList')"><ListOrdered class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" title="Quote" @click="run('formatBlock', 'blockquote')"><Quote class="h-3.5 w-3.5" /></button>
      <span class="mx-1 h-4 border-l border-border-gray"></span>
      <button type="button" class="btn-icon h-7 w-7" title="Undo" @click="run('undo')"><Undo2 class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" title="Redo" @click="run('redo')"><Redo2 class="h-3.5 w-3.5" /></button>
    </div>
    <div ref="editor" contenteditable="true" role="textbox" aria-multiline="true" class="min-h-32 px-3 py-2 text-sm text-ink outline-none empty:before:pointer-events-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]" :data-placeholder="placeholder" @input="update" @paste="pastePlainText"></div>
  </div>
</template>
