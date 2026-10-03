<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FolderTree, Plus } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { AdminCategory } from '@/api/admin'
import BaseModal from '@/components/BaseModal.vue'
import EmptyState from '@/components/EmptyState.vue'
import CategoryTreeNode from '@/components/admin/CategoryTreeNode.vue'

const { t } = useI18n()

const loading = ref(true)
const loadFailed = ref(false)

const tree = ref<AdminCategory[]>([])

const open = ref<Record<string, boolean>>({})

const pendingDelete = ref<AdminCategory | null>(null)
const deleting = ref(false)

function countNodes(list: AdminCategory[]): number {
  return list.reduce((total, node) => total + 1 + countNodes(node.children ?? []), 0)
}

const totalCategories = computed(() =>
  t('admin.categories.total_count', { count: countNodes(tree.value) })
)

function toggleOpen(id: number) {
  const key = String(id)
  // Absent means "open by default", so the first click has to collapse it.
  open.value[key] = !(open.value[key] ?? true)
}

function removeById(list: AdminCategory[], id: number): boolean {
  for (let i = 0; i < list.length; i++) {
    if (list[i].id === id) {
      list.splice(i, 1)
      return true
    }
    const children = list[i].children
    if (children && removeById(children, id)) return true
  }
  return false
}

async function loadCategories() {
  loading.value = true
  loadFailed.value = false
  try {
    const { data: resp } = await adminApi.listCategories()
    tree.value = resp.data
  } catch {
    loadFailed.value = true
    showToast(t('admin.categories.toast_load_error'))
  } finally {
    loading.value = false
  }
}

function requestDelete(node: AdminCategory) {
  pendingDelete.value = node
}

async function confirmDelete() {
  const node = pendingDelete.value
  if (!node || deleting.value) return

  deleting.value = true
  try {
    await adminApi.deleteCategory(node.id)
    removeById(tree.value, node.id)
    showToast(t('admin.categories.toast_deleted', { name: node.name }))
    pendingDelete.value = null
  } catch (error) {
    // The API explains a refusal ("move its 3 products first", "delete its
    // sub-categories first"), so show that instead of a generic failure.
    showToast(apiMessage(error) ?? t('admin.categories.toast_delete_error'))
  } finally {
    deleting.value = false
  }
}

function apiMessage(error: unknown): string | null {
  const data = (error as { response?: { data?: { data?: { message?: string }; message?: string } } })
    ?.response?.data

  return data?.data?.message ?? data?.message ?? null
}

const toast = ref('')
const toastTone = ref<'success' | 'error'>('success')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string, tone: 'success' | 'error' = 'success') {
  toast.value = msg
  toastTone.value = tone
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 3500)
}

onMounted(loadCategories)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-ink">{{ $t('admin.categories.title') }}</h1>
        <span v-if="!loading" class="chip">{{ totalCategories }}</span>
      </div>
      <router-link :to="{ name: 'admin-category-create' }" class="btn-primary btn-sm w-fit">
        <Plus class="h-4 w-4" />
        {{ $t('admin.categories.add_root') }}
      </router-link>
    </div>

    <div v-if="loading" class="card divide-y divide-border-gray">
      <div v-for="i in 5" :key="i" class="flex items-center gap-3 px-4 py-3">
        <div class="h-8 w-8 animate-pulse rounded-lg bg-canvas"></div>
        <div class="h-3 flex-1 animate-pulse rounded bg-canvas"></div>
      </div>
    </div>

    <EmptyState
      v-else-if="loadFailed"
      :title="$t('admin.categories.toast_load_error')"
      :description="$t('admin.categories.empty_retry')"
      :cta-label="$t('actions.retry')"
      @cta="loadCategories"
    >
      <template #icon>
        <FolderTree :size="28" />
      </template>
    </EmptyState>

    <EmptyState
      v-else-if="!tree.length"
      :title="$t('admin.categories.empty_title')"
      :description="$t('admin.categories.empty_description')"
      :cta-label="$t('admin.categories.add_root')"
      @cta="$router.push({ name: 'admin-category-create' })"
    >
      <template #icon>
        <FolderTree :size="28" />
      </template>
    </EmptyState>

    <div v-else class="card divide-y divide-border-gray overflow-hidden">
      <CategoryTreeNode
        v-for="root in tree"
        :key="root.id"
        :node="root"
        :open="open"
        @toggle="toggleOpen"
        @delete="requestDelete"
      />
    </div>

    <BaseModal
      :model-value="pendingDelete !== null"
      size="sm"
      :title="$t('admin.categories.delete_title')"
      @update:model-value="pendingDelete = null"
    >
      <p class="text-sm text-gray-600 dark:text-muted">
        {{ $t('admin.categories.delete_confirm', { name: pendingDelete?.name ?? '' }) }}
      </p>
      <p v-if="(pendingDelete?.products_count ?? 0) > 0" class="mt-3 text-sm text-amber-600">
        {{
          $t('admin.categories.delete_blocked_products', {
            count: pendingDelete?.products_count ?? 0,
          })
        }}
      </p>

      <template #footer>
        <div class="flex justify-end gap-2">
          <button class="btn-secondary btn-sm" type="button" @click="pendingDelete = null">
            {{ $t('actions.cancel') }}
          </button>
          <button
            class="btn-danger btn-sm"
            type="button"
            :disabled="deleting"
            @click="confirmDelete"
          >
            {{ $t('actions.delete') }}
          </button>
        </div>
      </template>
    </BaseModal>

    <transition name="fade">
      <div
        v-if="toast"
        class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover"
        :class="toastTone === 'error' ? 'text-red-600' : ''"
      >
        {{ toast }}
      </div>
    </transition>
  </div>
</template>