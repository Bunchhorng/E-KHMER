<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { History, Search, Warehouse } from 'lucide-vue-next'
import { RouterLink, useRoute } from 'vue-router'
import { sellerApi } from '@/api/seller'
import type { AdminInventoryItem, InventoryTransaction } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'
import { formatDateTime } from '@/utils/format'
import BasePagination from '@/components/BasePagination.vue'
import StatusTag from '@/components/StatusTag.vue'

const route = useRoute()
const shopId = computed(() => Number(route.params.id))
const inventory = ref<AdminInventoryItem[]>([])
const query = ref('')
const status = ref('')
const loading = ref(true)
const error = ref('')
const page = ref(1)
const pageCount = ref(1)
const total = ref(0)
const selected = ref<AdminInventoryItem | null>(null)
const quantity = ref<number | null>(null)
const ledger = ref<InventoryTransaction[]>([])
const ledgerLoading = ref(false)
const adjusting = ref(false)

async function load(target = page.value) {
  loading.value = true
  error.value = ''

  try {
    const { data } = await sellerApi.listInventory(shopId.value, {
      q: query.value || undefined,
      stock_status: status.value || undefined,
      page: target
    })
    inventory.value = data.data
    page.value = data.meta.current_page
    pageCount.value = data.meta.last_page
    total.value = data.meta.total

    if (selected.value && !inventory.value.some((item) => item.id === selected.value?.id)) {
      selected.value = null
      ledger.value = []
    }
  } catch (cause) {
    error.value = extractErrorMessage(cause, 'Could not load shop inventory.')
  } finally {
    loading.value = false
  }
}

async function loadLedger(inventoryId: number) {
  ledgerLoading.value = true

  try {
    const { data } = await sellerApi.listInventoryTransactions(shopId.value, inventoryId)
    ledger.value = data.data
  } catch (cause) {
    error.value = extractErrorMessage(cause, 'Could not load the inventory ledger.')
  } finally {
    ledgerLoading.value = false
  }
}

async function selectItem(item: AdminInventoryItem) {
  selected.value = item
  quantity.value = item.quantity
  await loadLedger(item.id)
}

async function adjustStock() {
  const item = selected.value
  const nextQuantity = quantity.value

  if (!item || nextQuantity === null || !Number.isInteger(nextQuantity) || nextQuantity < 0 || adjusting.value) return

  if (nextQuantity < item.reserved_quantity) {
    error.value = `On-hand stock cannot be less than the ${item.reserved_quantity} units currently reserved.`
    return
  }

  if (!window.confirm(`Set on-hand stock for ${item.sku ?? 'this variant'} to ${nextQuantity}?`)) return

  adjusting.value = true
  error.value = ''

  try {
    const { data } = await sellerApi.adjustInventory(shopId.value, item.id, nextQuantity)
    const index = inventory.value.findIndex((entry) => entry.id === item.id)
    if (index !== -1) inventory.value[index] = data.data
    selected.value = data.data
    quantity.value = data.data.quantity
    await loadLedger(item.id)
  } catch (cause) {
    error.value = extractErrorMessage(cause, 'Could not adjust stock.')
  } finally {
    adjusting.value = false
  }
}

function quantityText(transaction: InventoryTransaction): string {
  if (transaction.type === 'adjust') return transaction.quantity >= 0 ? `+${transaction.quantity}` : String(transaction.quantity)
  return transaction.type === 'release' || transaction.type === 'restock'
    ? `+${transaction.quantity}`
    : `-${transaction.quantity}`
}

watch(shopId, () => void load(1))
onMounted(() => void load())
</script>

<template>
  <div class="container-app mx-auto space-y-6 py-10">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <RouterLink :to="{ name: 'seller-shop-dashboard', params: { id: shopId } }" class="text-xs font-semibold text-primary">Seller Center</RouterLink>
        <h1 class="mt-1 text-3xl font-bold text-ink">Shop inventory</h1>
        <p class="mt-1 text-sm text-gray-500">Review stock, set on-hand quantities, and inspect the inventory ledger for this shop only.</p>
      </div>
    </header>

    <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>

    <section class="card overflow-hidden">
      <div class="flex flex-wrap gap-2 border-b border-border-gray p-4">
        <div class="relative min-w-56 flex-1 sm:flex-none">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input v-model="query" class="input h-9 w-full pl-9 text-sm" placeholder="Search inventory" @keyup.enter="load(1)" />
        </div>
        <select v-model="status" class="select h-9 text-sm" @change="load(1)">
          <option value="">All stock</option>
          <option value="in">In stock</option>
          <option value="low">Low stock</option>
          <option value="out">Out of stock</option>
        </select>
      </div>

      <div v-if="loading" class="p-10 text-center text-sm text-gray-500">Loading inventory…</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm">
          <thead class="border-b border-border-gray bg-canvas/60 text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="px-4 py-3">Product</th>
              <th class="px-4 py-3">SKU</th>
              <th class="px-4 py-3 text-right">Available</th>
              <th class="px-4 py-3 text-right">Reserved</th>
              <th class="px-4 py-3 text-right">On hand</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border-gray">
            <tr v-if="!inventory.length">
              <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                <Warehouse class="mx-auto mb-2 h-7 w-7 text-gray-300" />
                No inventory records in this shop.
              </td>
            </tr>
            <tr v-for="item in inventory" :key="item.id" class="hover:bg-canvas/50">
              <td class="px-4 py-3 font-semibold text-ink">{{ item.product_name }}</td>
              <td class="px-4 py-3 text-xs text-gray-500">{{ item.sku }}</td>
              <td class="px-4 py-3 text-right font-medium text-ink">{{ item.available_quantity }}</td>
              <td class="px-4 py-3 text-right text-gray-500">{{ item.reserved_quantity }}</td>
              <td class="px-4 py-3 text-right text-gray-500">{{ item.quantity }}</td>
              <td class="px-4 py-3"><StatusTag :status="item.is_out_of_stock ? 'out' : item.is_low_stock ? 'low' : 'active'" /></td>
              <td class="px-4 py-3 text-right"><button type="button" class="btn-outline btn-sm" @click="selectItem(item)">Manage</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!loading && inventory.length" class="border-t border-border-gray p-4">
        <BasePagination :page="page" :page-count="pageCount" :page-size="15" :total-items="total" @update:page="load" />
      </div>
    </section>

    <section v-if="selected" class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-gray p-4">
        <div>
          <h2 class="text-lg font-semibold text-ink">Inventory ledger</h2>
          <p class="text-sm text-gray-500">{{ selected.product_name }} · {{ selected.sku }}</p>
        </div>
        <div class="flex items-center gap-2">
          <input v-model.number="quantity" class="input h-9 w-28 text-sm" type="number" min="0" step="1" aria-label="On-hand stock quantity" />
          <button type="button" class="btn-primary btn-sm" :disabled="adjusting" @click="adjustStock">{{ adjusting ? 'Saving…' : 'Set stock' }}</button>
        </div>
      </div>

      <div v-if="ledgerLoading" class="p-8 text-center text-sm text-gray-500">Loading ledger…</div>
      <div v-else-if="!ledger.length" class="p-8 text-center text-sm text-gray-500">No inventory transactions yet.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
          <thead class="border-b border-border-gray bg-canvas/60 text-left text-xs uppercase text-gray-500">
            <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Quantity</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3">Note</th><th class="px-4 py-3">By</th></tr>
          </thead>
          <tbody class="divide-y divide-border-gray">
            <tr v-for="transaction in ledger" :key="transaction.id">
              <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ formatDateTime(transaction.created_at) }}</td>
              <td class="px-4 py-3"><span class="chip">{{ transaction.type }}</span></td>
              <td class="px-4 py-3 text-right font-mono">{{ quantityText(transaction) }}</td>
              <td class="px-4 py-3 text-right font-mono">{{ transaction.balance_after }}</td>
              <td class="px-4 py-3 text-gray-500">{{ transaction.note ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-500">{{ transaction.created_by?.name ?? 'System' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-else class="card p-8 text-center text-sm text-gray-500"><History class="mx-auto mb-2 h-7 w-7 text-gray-300" />Choose an inventory item to adjust stock or view its ledger.</div>
  </div>
</template>
