<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import { Eye, EyeOff, MoreVertical, Pencil, Trash2 } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

defineProps<{ name: string; active: boolean; disabled: boolean; editTo: RouteLocationRaw }>()
defineEmits<{ preview: []; toggle: []; delete: [] }>()
const { t } = useI18n()
const open = ref(false)
const trigger = ref<HTMLButtonElement | null>(null)
const menu = ref<HTMLElement | null>(null)
const position = ref({ top: '0px', left: '0px' })
function close(restoreFocus = false) { open.value = false; if (restoreFocus) trigger.value?.focus({ preventScroll: true }) }
function updatePosition() {
  const bounds = trigger.value?.getBoundingClientRect()
  if (!bounds || bounds.right < 0 || bounds.left > window.innerWidth || bounds.bottom < 0 || bounds.top > window.innerHeight) return false
  position.value = {
    left: `${Math.max(12, Math.min(bounds.right - 210, window.innerWidth - 222))}px`,
    top: `${bounds.bottom + 124 > window.innerHeight ? bounds.top - 116 : bounds.bottom + 6}px`
  }
  return true
}
async function toggleMenu() {
  if (open.value) return close(true)
  if (!updatePosition()) return
  open.value = true
  await nextTick()
  menu.value?.querySelector<HTMLButtonElement>('button')?.focus({ preventScroll: true })
}
function outside(event: PointerEvent) {
  if (!menu.value?.contains(event.target as Node) && !trigger.value?.contains(event.target as Node)) close()
}
function keyboard(event: KeyboardEvent) {
  if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true); return }
  if (event.key === 'Tab') { close(true); return }
  if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  const items = [...menu.value?.querySelectorAll<HTMLButtonElement>('button') ?? []]
  const current = items.indexOf(document.activeElement as HTMLButtonElement)
  items[event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus({ preventScroll: true })
}
function repositionMenu(event: Event) {
  if (open.value && !menu.value?.contains(event.target as Node) && !updatePosition()) close()
}
onMounted(() => { document.addEventListener('pointerdown', outside); window.addEventListener('resize', repositionMenu); window.addEventListener('scroll', repositionMenu, true) })
onBeforeUnmount(() => { document.removeEventListener('pointerdown', outside); window.removeEventListener('resize', repositionMenu); window.removeEventListener('scroll', repositionMenu, true) })
</script>
<template>
  <div class="product-actions flex items-center gap-2">
    <RouterLink :to="editTo" class="btn-secondary btn-sm"><Pencil :size="16" />{{ t('actions.edit') }}</RouterLink>
    <button type="button" class="btn-secondary btn-sm" :aria-label="t('seller.preview_product', { name })" @click="$emit('preview')"><Eye :size="17" />{{ t('seller.preview') }}</button>
    <button ref="trigger" type="button" class="btn-secondary product-more" :disabled="disabled" :aria-label="t('seller.product_actions', { name })" aria-haspopup="menu" :aria-expanded="open" @click="toggleMenu" @keydown.down.prevent="toggleMenu"><MoreVertical :size="18" /></button>
    <Teleport to="body">
      <div v-if="open" ref="menu" role="menu" :aria-label="t('seller.product_actions', { name })" class="product-action-menu" :style="position" @keydown="keyboard">
        <button type="button" role="menuitem" :aria-label="t(active ? 'seller.hide' : 'seller.show') + ': ' + name" @click="close(true); $emit('toggle')"><component :is="active ? EyeOff : Eye" :size="17" />{{ t(active ? 'seller.hide' : 'seller.show') }}</button>
        <button type="button" role="menuitem" class="text-red-600 dark:text-red-400" @click="close(); $emit('delete')"><Trash2 :size="17" />{{ t('actions.delete') }}</button>
      </div>
    </Teleport>
  </div>
</template>
<style scoped>
.product-actions .btn-secondary { min-height: 42px; padding: 8px 12px; border-radius: 10px; gap: 8px; font-size: 13px; white-space: nowrap; box-shadow: none; }
.product-actions .product-more { width: 40px; padding-inline: 0; }
.product-action-menu { position: fixed; z-index: 60; width: 210px; padding: 6px; border: 1px solid rgb(var(--color-border)); border-radius: 12px; background: rgb(var(--color-surface)); color: rgb(var(--color-ink)); box-shadow: 0 8px 30px rgb(17 24 39 / 15%); }
.product-action-menu button { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 46px; padding: 10px; border-radius: 8px; text-align: left; font-size: 14px; }
.product-action-menu button:hover, .product-action-menu button:focus-visible { background: rgb(var(--color-canvas)); outline: 2px solid rgb(var(--color-primary) / 30%); outline-offset: -2px; }
</style>
