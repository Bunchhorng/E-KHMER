<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import { Bold, Italic, List, ListOrdered, Quote, Redo2, Underline, Undo2 } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sanitizeProductDescription } from '@/utils/productDescription'
import '@/styles/productDescription.css'

const props = withDefaults(defineProps<{ modelValue: string; placeholder?: string; label?: string; disabled?: boolean }>(), { placeholder: 'Write a detailed description...', label: '', disabled: false })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const { t } = useI18n()
const editor = ref<HTMLDivElement | null>(null)

function syncFromModel() {
  if (editor.value) {
    const safe = sanitizeProductDescription(props.modelValue)
    if (editor.value.innerHTML !== safe) editor.value.innerHTML = safe
  }
}
function update() { emit('update:modelValue', sanitizeProductDescription(editor.value?.innerHTML ?? '')) }
function run(command: string, value?: string) {
  if (props.disabled) return
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
      <select class="mr-2 max-w-[140px] bg-transparent pr-4 text-xs text-muted" :aria-label="t('seller.editor_style')" :disabled="disabled" @change="run('formatBlock', ($event.target as HTMLSelectElement).value)"><option value="p">{{ t('seller.editor_paragraph') }}</option><option value="h3">{{ t('seller.editor_heading') }}</option></select>
      <span class="mx-1 h-4 border-l border-border-gray"></span>
      <button v-for="action in [{ key: 'bold', command: 'bold', icon: Bold }, { key: 'italic', command: 'italic', icon: Italic }, { key: 'underline', command: 'underline', icon: Underline }, { key: 'bullets', command: 'insertUnorderedList', icon: List }, { key: 'numbered', command: 'insertOrderedList', icon: ListOrdered }]" :key="action.key" type="button" class="btn-icon h-7 w-7" :title="t('seller.editor_' + action.key)" :aria-label="t('seller.editor_' + action.key)" :disabled="disabled" @mousedown.prevent @click="run(action.command)"><component :is="action.icon" class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" :title="t('seller.editor_quote')" :aria-label="t('seller.editor_quote')" :disabled="disabled" @mousedown.prevent @click="run('formatBlock', 'blockquote')"><Quote class="h-3.5 w-3.5" /></button>
      <span class="mx-1 h-4 border-l border-border-gray"></span>
      <button type="button" class="btn-icon h-7 w-7" :title="t('seller.editor_undo')" :aria-label="t('seller.editor_undo')" :disabled="disabled" @mousedown.prevent @click="run('undo')"><Undo2 class="h-3.5 w-3.5" /></button>
      <button type="button" class="btn-icon h-7 w-7" :title="t('seller.editor_redo')" :aria-label="t('seller.editor_redo')" :disabled="disabled" @mousedown.prevent @click="run('redo')"><Redo2 class="h-3.5 w-3.5" /></button>
    </div>
    <div ref="editor" :contenteditable="!disabled" role="textbox" :aria-label="label || t('seller.description')" :aria-disabled="disabled" aria-multiline="true" class="product-description min-h-32 px-3 py-2 text-sm text-ink outline-none empty:before:pointer-events-none empty:before:text-gray-400 empty:before:content-[attr(data-placeholder)]" :data-placeholder="placeholder" @input="update" @paste="pastePlainText"></div>
  </div>
</template>
