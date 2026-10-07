<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import { X } from 'lucide-vue-next'

type ModalSize = 'sm' | 'md' | 'lg' | 'xl'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    size?: ModalSize
    closeOnBackdrop?: boolean
  }>(),
  { title: '', size: 'md', closeOnBackdrop: true }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
}>()
const dialog = ref<HTMLElement | null>(null)
const titleId = useId()
let previousFocus: HTMLElement | null = null

const sizeClasses: Record<ModalSize, string> = {
  sm: 'sm:max-w-sm',
  md: 'lg:max-w-lg',
  lg: 'xl:max-w-xl',
  xl: '2xl:max-w-6xl'
}

function close(): void {
  emit('update:modelValue', false)
  emit('close')
}

function handleBackdropClick(): void {
  if (props.closeOnBackdrop) close()
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') close()
  if (event.key !== 'Tab' || !dialog.value) return
  const controls = [...dialog.value.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex="0"]')].filter(element => element.getClientRects().length > 0)
  const first = controls[0]
  const last = controls[controls.length - 1]
  if (!first) { event.preventDefault(); dialog.value.focus(); return }
  if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) { event.preventDefault(); last?.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
      window.addEventListener('keydown', onKeydown)
      await nextTick()
      if (props.modelValue) dialog.value?.focus()
    } else {
      window.removeEventListener('keydown', onKeydown)
      if (previousFocus?.isConnected) previousFocus.focus()
    }
  },
  { immediate: true }
)

onBeforeUnmount(() => { window.removeEventListener('keydown', onKeydown); if (previousFocus?.isConnected) previousFocus.focus() })
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 animate-fade-in bg-black/40" @click="handleBackdropClick"></div>
      <div class="relative flex min-h-full items-center justify-center p-4">
        <div
          ref="dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          tabindex="-1"
          class="relative mx-4 my-8 flex max-h-[85vh] w-full flex-col rounded-xl bg-white shadow-popover dark:bg-surface"
          :class="sizeClasses[size]"
        >
          <header class="flex shrink-0 items-center justify-between border-b border-border-gray px-6 py-4">
            <h3 :id="titleId" class="text-lg font-semibold text-ink">{{ title }}</h3>
            <button type="button" class="btn-icon" :aria-label="$t('actions.close')" @click="close">
              <X :size="20" />
            </button>
          </header>
          <div class="overflow-y-auto px-6 py-5">
            <slot />
          </div>
          <footer v-if="$slots.footer" class="shrink-0 border-t border-border-gray px-6 py-4">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </div>
  </Teleport>
</template>
