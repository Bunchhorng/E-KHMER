<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { PackageSearch } from 'lucide-vue-next'
import BasePagination from '@/components/BasePagination.vue'
import StatusTag from '@/components/StatusTag.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import DataTableSkeleton from '@/components/DataTableSkeleton.vue'
import { adminApi } from '@/api/admin'
import type { AdminInventoryItem, InventoryTransaction } from '@/api/admin'
import { formatDateTime } from '@/utils/format'
import { extractErrorMessage } from '@/api/errors'

const { t } = useI18n()
const route = useRoute()

const pageSize = 15
const ledgerPageSize = 20

const loading = ref(true)
const items = ref<AdminInventoryItem[]>([])
const totalCount = ref(0)
const page = ref(1)
const pageCount = ref(1)

const search = ref(typeof route.query.q === 'string' ? route.query.q : '')
const initialStockStatus = typeof route.query.stock_status === 'string' ? route.query.stock_status : 'all'
const stockStatus = ref(['all', 'in', 'low', 'out'].includes(initialStockStatus) ? initialStockStatus : 'all')
const selectedId = ref<number | null>(null)

const ledgerLoading = ref(false)
const ledger = ref<InventoryTransaction[]>([])
const ledgerTotal = ref(0)
const ledgerPage = ref(1)
const ledgerPageCount = ref(1)
const ledgerType = ref('all')
const adjustmentQuantity = ref<number | null>(null)
const adjusting = ref(false)

let searchTimer: ReturnType<typeof setTimeout> | undefined

function statusOf(item: AdminInventoryItem): string {
  if (item.available_quantity <= 0) return 'out of stock'
  if (item.is_low_stock) return 'low stock'
  return 'in stock'
}

const badgeVariantMap = { reserve: 'info', release: 'neutral', deduct: 'danger', adjust: 'warning' } as const
type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

function badgeVariant(type: string): BadgeVariant {
  return badgeVariantMap[type as keyof typeof badgeVariantMap] ?? 'neutral'
}

function quantityText(txn: InventoryTransaction): string {
  if (txn.type === 'adjust') {
    return txn.quantity >= 0 ? `+${txn.quantity}` : `${txn.quantity}`
  }
  return txn.type === 'release' ? `+${txn.quantity}` : `-${txn.quantity}`
}

function quantityClass(txn: InventoryTransaction): string {
  if (txn.type === 'reserve' || txn.type === 'deduct') return 'text-red-600'
  if (txn.type === 'release') return 'text-emerald-600'
  return txn.quantity >= 0 ? 'text-emerald-600' : 'text-red-600'
}

const selectedItem = computed(() => items.value.find((i) => i.id === selectedId.value) ?? null)

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

async function loadInventory(params: { q?: string; stock_status?: string; page?: number } = {}) {
  loading.value = true
  try {
    const { data: resp } = await adminApi.listInventory(params)
    items.value = resp.data
    totalCount.value = resp.meta.total
    page.value = resp.meta.current_page
    pageCount.value = resp.meta.last_page
    if (!items.value.some((i) => i.id === selectedId.value)) {
      selectedId.value = null
      ledger.value = []
      ledgerTotal.value = 0
    }
  } catch {
    showToast(t('admin.inventory.toast_load_error'))
  } finally {
    loading.value = false
  }
}

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadInventory({ q: search.value.trim() || undefined, stock_status: stockStatus.value, page: 1 })
  }, 300)
}

function onStatusChange() {
  page.value = 1
  loadInventory({ q: search.value.trim() || undefined, stock_status: stockStatus.value, page: 1 })
}

function onPageChange(p: number) {
  loadInventory({ q: search.value.trim() || undefined, stock_status: stockStatus.value, page: p })
}

async function loadLedger(inventoryId: number, params: { type?: string; page?: number } = {}) {
  ledgerLoading.value = true
  try {
    const { data: resp } = await adminApi.listInventoryTransactions(inventoryId, params)
    ledger.value = resp.data
    ledgerTotal.value = resp.meta.total
    ledgerPage.value = resp.meta.current_page
    ledgerPageCount.value = resp.meta.last_page
  } catch {
    showToast(t('admin.inventory.toast_ledger_error'))
  } finally {
    ledgerLoading.value = false
  }
}

function selectItem(item: AdminInventoryItem) {
  selectedId.value = item.id
  adjustmentQuantity.value = item.quantity
  ledgerType.value = 'all'
  ledgerPage.value = 1
  loadLedger(item.id, { page: 1 })
}

async function adjustStock() {
  const item = selectedItem.value
  if (!item || adjusting.value || adjustmentQuantity.value === null || !Number.isInteger(adjustmentQuantity.value)) return

  if (adjustmentQuantity.value < item.reserved_quantity) {
    showToast(`Quantity cannot be lower than the ${item.reserved_quantity} units currently reserved.`)
    return
  }

  if (!window.confirm(`Set on-hand stock for ${item.variant?.sku ?? 'this variant'} to ${adjustmentQuantity.value}?`)) return

  adjusting.value = true
  try {
    const { data } = await adminApi.adjustInventory(item.id, adjustmentQuantity.value)
    const index = items.value.findIndex((entry) => entry.id === item.id)
    if (index >= 0) items.value[index] = data.data
    adjustmentQuantity.value = data.data.quantity
    await loadLedger(item.id, { page: 1 })
    showToast('Stock adjusted and recorded in the inventory ledger.')
  } catch (error) {
    showToast(extractErrorMessage(error, 'Could not adjust stock.'))
  } finally {
    adjusting.value = false
  }
}

function onLedgerTypeChange() {
  if (selectedId.value === null) return
  ledgerPage.value = 1
  loadLedger(selectedId.value, { type: ledgerType.value === 'all' ? undefined : ledgerType.value, page: 1 })
}

function onLedgerPageChange(p: number) {
  if (selectedId.value === null) return
  loadLedger(selectedId.value, { type: ledgerType.value === 'all' ? undefined : ledgerType.value, page: p })
}

onMounted(() => loadInventory({ q: search.value.trim() || undefined, stock_status: stockStatus.value }))
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-bold text-ink">{{ $t('admin.inventory.title') }}</h1>
      <span class="chip">{{ $t('admin.inventory.total_count', { count: totalCount }) }}</span>
    </div>

    <div class="card overflow-hidden">
      <div class="flex flex-col gap-3 border-b border-border-gray p-4 sm:flex-row sm:items-center">
        <input
          type="text"
          v-model="search"
          class="input sm:w-72"
          :placeholder="t('admin.inventory.search_placeholder')"
          @input="onSearchInput"
        />
        <select v-model="stockStatus" class="select h-10 w-48" @change="onStatusChange">
          <option value="all">{{ $t('admin.inventory.show_all') }}</option>
          <option value="in">{{ $t('admin.inventory.status_in') }}</option>
          <option value="low">{{ $t('admin.inventory.status_low') }}</option>
          <option value="out">{{ $t('admin.inventory.status_out') }}</option>
        </select>
      </div>

      <DataTableSkeleton v-if="loading" :rows="8" :columns="8" />

      <template v-else-if="items.length">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-border-gray bg-canvas/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <th class="px-4 py-3">{{ $t('admin.inventory.product') }}</th>
                <th class="px-4 py-3">{{ $t('admin.inventory.sku') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.inventory.available') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.inventory.reserved') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.inventory.on_hand') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.inventory.sold') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.inventory.threshold') }}</th>
                <th class="px-4 py-3">{{ $t('admin.inventory.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in items"
                :key="item.id"
                class="cursor-pointer border-b border-border-gray transition-colors hover:bg-canvas"
                :class="selectedId === item.id ? 'bg-primary/5' : ''"
                @click="selectItem(item)"
              >
                <td class="px-4 py-3">
                  <div class="text-sm font-medium text-ink">{{ item.product?.name ?? '—' }}</div>
                  <div class="text-xs text-gray-500">{{ item.variant_label ?? item.variant?.name ?? '' }}</div>
                </td>
                <td class="px-4 py-3 font-mono text-sm text-gray-600">{{ item.variant?.sku ?? '—' }}</td>
                <td class="px-4 py-3 text-right text-sm font-semibold text-ink">{{ item.available_quantity }}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-600">{{ item.reserved_quantity }}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-600">{{ item.quantity }}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-600">{{ item.sold_count }}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-600">{{ item.low_stock_threshold }}</td>
                <td class="px-4 py-3"><StatusTag :status="statusOf(item)" /></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end border-t border-border-gray p-3">
          <BasePagination :page="page" :page-count="pageCount" :total-items="totalCount" :page-size="pageSize" @update:page="onPageChange" />
        </div>
      </template>

      <EmptyState
        v-else
        :title="t('admin.inventory.no_detail')"
        :description="t('admin.inventory.select_stock')"
      >
        <template #icon>
          <PackageSearch class="h-10 w-10 text-gray-300 dark:text-gray-500" />
        </template>
      </EmptyState>
    </div>

    <div class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-gray p-4">
        <div>
          <h2 class="text-base font-semibold text-ink">{{ $t('admin.inventory.ledger_title') }}</h2>
          <p v-if="selectedItem" class="mt-0.5 text-sm text-gray-500">
            {{ selectedItem.product?.name }} · {{ selectedItem.variant?.sku ?? '' }}
          </p>
        </div>
        <div v-if="selectedItem" class="flex flex-wrap items-center gap-2">
          <label class="sr-only" for="inventory-quantity">New on-hand quantity</label>
          <input id="inventory-quantity" v-model.number="adjustmentQuantity" type="number" min="0" step="1" class="input h-10 w-36" aria-label="New on-hand quantity" />
          <button type="button" class="btn-primary btn-sm" :disabled="adjusting" @click="adjustStock">
            {{ adjusting ? 'Adjusting…' : 'Adjust stock' }}
          </button>
          <select v-model="ledgerType" class="select h-10 w-44" @change="onLedgerTypeChange">
            <option value="all">{{ $t('admin.inventory.all_types') }}</option>
            <option value="reserve">{{ $t('admin.inventory.type_reserve') }}</option>
            <option value="release">{{ $t('admin.inventory.type_release') }}</option>
            <option value="deduct">{{ $t('admin.inventory.type_deduct') }}</option>
            <option value="adjust">{{ $t('admin.inventory.type_adjust') }}</option>
          </select>
        </div>
      </div>

      <DataTableSkeleton v-if="ledgerLoading" :rows="5" :columns="7" />

      <template v-else-if="selectedItem && ledger.length">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-border-gray bg-canvas/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <th class="px-4 py-3">{{ $t('admin.inventory.date') }}</th>
                <th class="px-4 py-3">{{ $t('admin.inventory.type') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.inventory.quantity') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.inventory.balance') }}</th>
                <th class="px-4 py-3">{{ $t('admin.inventory.reference') }}</th>
                <th class="px-4 py-3">{{ $t('admin.inventory.note') }}</th>
                <th class="px-4 py-3">{{ $t('admin.inventory.by') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="txn in ledger" :key="txn.id" class="border-b border-border-gray">
                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ formatDateTime(txn.created_at) }}</td>
                <td class="px-4 py-3">
                  <BaseBadge :variant="badgeVariant(txn.type)">
                    {{ $t(`admin.inventory.type_${txn.type}`) }}
                  </BaseBadge>
                </td>
                <td class="px-4 py-3 text-right font-mono text-sm font-semibold" :class="quantityClass(txn)">
                  {{ quantityText(txn) }}
                </td>
                <td class="px-4 py-3 text-right font-mono text-sm text-gray-600">{{ txn.balance_after }}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ txn.reference ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ txn.note ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ txn.created_by?.name ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end border-t border-border-gray p-3">
          <BasePagination
            :page="ledgerPage"
            :page-count="ledgerPageCount"
            :total-items="ledgerTotal"
            :page-size="ledgerPageSize"
            @update:page="onLedgerPageChange"
          />
        </div>
      </template>

      <div v-else class="p-10 text-center text-sm text-gray-500">
        {{ selectedItem ? $t('admin.inventory.ledger_empty') : $t('admin.inventory.select_stock') }}
      </div>
    </div>

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
