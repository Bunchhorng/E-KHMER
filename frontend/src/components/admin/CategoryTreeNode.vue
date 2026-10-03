<script setup lang="ts">
import { ChevronRight, FolderTree, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import type { AdminCategory } from '@/api/admin'

const props = withDefaults(
  defineProps<{
    node: AdminCategory
    depth?: number
    open?: Record<string, boolean>
  }>(),
  { depth: 0 }
)

const emit = defineEmits<{
  (e: 'toggle', id: number): void
  (e: 'delete', node: AdminCategory): void
}>()

// A level is open until the admin collapses it, so a freshly loaded deeper level
// is never hidden behind a parent nobody touched.
function isOpen(id: number): boolean {
  return props.open?.[String(id)] ?? true
}
</script>

<template>
  <div>
    <div
      class="flex items-center gap-3 px-4 py-3"
      :class="depth > 0 ? 'bg-canvas/40 py-2.5' : ''"
      :style="depth > 0 ? { paddingLeft: `${1 + depth * 3.5}rem` } : undefined"
    >
      <button
        v-if="node.children && node.children.length"
        class="btn-icon h-8 w-8 shrink-0"
        :class="{ 'rotate-90': isOpen(node.id) }"
        type="button"
        :title="$t('admin.categories.toggle')"
        @click="emit('toggle', node.id)"
      >
        <ChevronRight class="h-4 w-4" />
      </button>
      <span v-else class="h-8 w-8 shrink-0"></span>

      <img
        v-if="node.image"
        :src="node.image"
        :alt="node.name"
        class="h-8 w-8 shrink-0 rounded-lg object-cover"
      />
      <FolderTree v-else class="h-4 w-4 shrink-0 text-primary" />

      <span class="flex-1 text-sm font-medium text-ink">{{ node.name }}</span>

      <span v-if="!node.is_active" class="chip">{{ $t('status.draft') }}</span>
      <span class="chip">{{ node.products_count ?? 0 }}</span>

      <router-link
        class="btn-icon h-8 w-8"
        :title="$t('admin.categories.add_child')"
        :to="{ name: 'admin-category-create', query: { parent: node.id } }"
      >
        <Plus class="h-4 w-4" />
      </router-link>
      <router-link
        class="btn-icon h-8 w-8"
        :title="$t('actions.edit')"
        :to="{ name: 'admin-category-edit', params: { id: node.id } }"
      >
        <Pencil class="h-4 w-4" />
      </router-link>
      <button
        class="btn-icon h-8 w-8 hover:text-red-600"
        type="button"
        :title="$t('actions.delete')"
        @click="emit('delete', node)"
      >
        <Trash2 class="h-4 w-4" />
      </button>
    </div>

    <template v-if="node.children?.length && isOpen(node.id)">
      <CategoryTreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :open="open"
        @toggle="emit('toggle', $event)"
        @delete="emit('delete', $event)"
      />
    </template>
  </div>
</template>