<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, CheckCircle2, Package, Plus, XCircle } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import AdminDataTable from '@/components/admin/AdminDataTable.vue'
import type { TableColumn, TableRow } from '@/types'
import { adminApi } from '@/api/admin'
import type { AdminProduct } from '@/api/admin'

const { t } = useI18n()
const router = useRouter()

const loading = ref(true)
const products = ref<AdminProduct[]>([])
const totalCount = ref(0)
const showDeleted = ref(false)

const productMetrics = computed(() => {
  const active = products.value.filter((p) => p.is_active).length
  const lowStock = products.value.filter((p) => {
    const stock = p.variants?.reduce((sum, variant) => sum + variant.available_quantity, 0) ?? 0
    return p.is_active && stock > 0 && stock <= 5
  }).length
  return [
    { label: 'Total Products', value: totalCount.value, icon: Package, tone: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300', note: 'Across your catalog' },
    { label: 'Active Products', value: active, icon: CheckCircle2, tone: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300', note: 'Visible to customers' },
    { label: 'Inactive Products', value: Math.max(totalCount.value - active, 0), icon: XCircle, tone: 'bg-rose-50 text-rose-500 dark:bg-rose-500/15 dark:text-rose-300', note: 'Draft or unavailable' },
    { label: 'Low Stock', value: lowStock, icon: AlertTriangle, tone: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300', note: 'Needs attention' }
  ]
})

const columns = computed<TableColumn[]>(() => [
  { key: 'image', label: t('admin.products.column_image'), type: 'image', sortable: true },
  { key: 'title', label: '', sortable: true },
  { key: 'brand', label: t('admin.products.column_brand') },
  { key: 'sku', label: t('product.sku') },
  { key: 'price', label: t('admin.products.column_price'), type: 'currency', sortable: true },
  { key: 'stock', label: t('admin.products.column_stock'), type: 'number', sortable: true },
  { key: 'status', label: t('admin.products.column_status'), type: 'status' },
  { key: 'actions', label: '', type: 'actions' }
])

const rows = computed<TableRow[]>(() =>
  products.value.map((p) => ({
    id: p.id,
    image: p.cover_image ?? '',
    title: p.name,
    brand: p.brand?.name ?? '—',
    category: p.category?.name ?? '—',
    sku: p.sku,
    price: p.price,
    stock: p.variants?.reduce((sum, v) => sum + v.available_quantity, 0) ?? 0,
    status: showDeleted.value
      ? 'Deleted'
      : p.in_stock
        ? (p.variants?.some((v) => v.available_quantity <= 5) ? 'Low Stock' : 'In Stock')
        : 'Out of Stock'
  }))
)

const bulkActions = computed(() => [
  { label: t('admin.products.activate'), value: 'activate' },
  { label: t('admin.products.deactivate'), value: 'deactivate' },
  ...(showDeleted.value ? [] : [{ label: t('actions.delete'), value: 'delete' as string }])
])

const rowActions = computed(() =>
  showDeleted.value
    ? [{ label: t('admin.products.restore'), value: 'restore' }]
    : [
        { label: t('actions.edit'), value: 'edit' },
        { label: t('admin.products.duplicate'), value: 'duplicate' },
        { label: t('actions.delete'), value: 'delete' }
      ]
)

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

async function loadProducts() {
  loading.value = true
  try {
    const { data: resp } = await adminApi.listProducts({ deleted: showDeleted.value })
    products.value = resp.data
    totalCount.value = resp.meta.total
  } catch {
    showToast(t('admin.products.toast_load_error'))
  } finally {
    loading.value = false
  }
}

function toggleDeletedView() {
  showDeleted.value = !showDeleted.value
  void loadProducts()
}

function onRowAction(payload: { action: string; row: TableRow }) {
  if (payload.action === 'edit') {
    const id = Number(payload.row.id)
    if (id) {
      router.push({ name: 'admin-product-edit', params: { id } })
    }
  } else if (payload.action === 'duplicate') {
    showToast(t('admin.products.toast_duplicated', { name: String(payload.row.title) }))
  } else if (payload.action === 'restore') {
    const id = Number(payload.row.id)
    adminApi.restoreProduct(id).then(() => {
      products.value = products.value.filter((p) => p.id !== id)
      totalCount.value--
      showToast(t('admin.products.toast_restored', { name: String(payload.row.title) }))
    }).catch(() => {
      showToast(t('admin.products.toast_action_failed'))
    })
  } else if (payload.action === 'delete') {
    const id = Number(payload.row.id)
    adminApi.deleteProduct(id).then(() => {
      products.value = products.value.filter((p) => p.id !== id)
      totalCount.value--
      showToast(t('admin.products.toast_deleted', { name: String(payload.row.title) }))
    }).catch(() => {
      showToast(t('admin.products.toast_delete_error'))
    })
  }
}

function onBulkAction(payload: { action: string; ids: string[] }) {
  if (payload.action === 'delete') {
    Promise.all(payload.ids.map((id) => adminApi.deleteProduct(Number(id))))
      .then(() => {
        products.value = products.value.filter((p) => !payload.ids.includes(String(p.id)))
        totalCount.value -= payload.ids.length
        showToast(t('admin.products.toast_deleted_count', { count: payload.ids.length }))
      })
      .catch(() => {
        showToast(t('admin.products.toast_delete_error'))
      })
  } else if (payload.action === 'activate' || payload.action === 'deactivate') {
    const ids = payload.ids.map((id) => Number(id))
    adminApi.updateProductStatus(ids, payload.action === 'activate').then(() => {
      showToast(t('admin.products.toast_status_updated', { count: ids.length }))
      void loadProducts()
    }).catch(() => {
      showToast(t('admin.products.toast_action_failed'))
    })
  } else if (payload.action === 'export') {
    showToast(t('admin.products.toast_exported_csv', { count: payload.ids.length }))
  }
}

onMounted(loadProducts)
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-4 sm:space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div><h1 class="text-2xl font-bold tracking-tight text-ink sm:text-[27px]">{{ $t('admin.products.title') }}</h1><p class="mt-1 text-sm text-gray-500 dark:text-muted">Manage your products, add new items, and keep inventory up to date.</p></div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="btn-outline btn-sm"
          @click="toggleDeletedView"
        >
          {{ showDeleted ? $t('admin.products.show_active') : $t('admin.products.show_deleted') }}
        </button>
        <router-link :to="{ name: 'admin-product-create' }" class="btn-primary !px-4 !py-2">
          <Plus class="h-4 w-4" />
          Add New Product
        </router-link>
      </div>
    </div>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="metric in productMetrics" :key="metric.label" class="card p-4"><div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="metric.tone"><component :is="metric.icon" class="h-5 w-5" /></div><div><p class="text-xs font-medium text-gray-500 dark:text-muted">{{ metric.label }}</p><p class="mt-1 text-xl font-bold text-ink">{{ metric.value }}</p><p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ metric.note }}</p></div></div></article>
    </section>

    <section class="card overflow-hidden rounded-none p-0"><div class="border-b border-border-gray px-4 py-3"><p class="text-sm font-semibold text-ink">Product Catalog <span class="ml-1 text-xs font-normal text-gray-400">{{ totalCount }} products</span></p></div><AdminDataTable
        :columns="columns"
        :rows="rows"
        :loading="loading"
        :search-keys="['title', 'brand', 'sku']"
        :search-placeholder="$t('admin.products.search_placeholder')"
        :page-size="8"
        flat
        :bulk-actions="bulkActions"
        :row-actions="rowActions"
        @row-action="onRowAction"
        @bulk-action="onBulkAction"
      /></section>

    <transition name="fade">
      <div
        v-if="toast"
        class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover"
      >
        {{ toast }}
      </div>
    </transition>
  </div>
</template>
