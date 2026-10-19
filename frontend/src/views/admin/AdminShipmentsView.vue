<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { X, Truck, Save } from 'lucide-vue-next'
import AdminDataTable from '@/components/admin/AdminDataTable.vue'
import type { TableColumn, TableRow } from '@/types'
import { adminApi } from '@/api/admin'
import type { AdminShipment } from '@/api/admin'

const { t } = useI18n()

const loading = ref(true)
const correctionsEnabled = ref(false)
const shipments = ref<AdminShipment[]>([])
const totalCount = ref(0)
const filter = ref('all')
const editing = ref<AdminShipment | null>(null)
const saving = ref(false)

const statusFilters = ['all', 'pending', 'shipped', 'in_transit', 'delivered', 'returned']

const statusOptions = ['pending', 'shipped', 'in_transit', 'delivered', 'returned']

const columns = computed<TableColumn[]>(() => [
  { key: 'order_number', label: t('admin.shipments.order'), sortable: true },
  { key: 'customer', label: t('admin.shipments.customer') },
  { key: 'carrier', label: t('admin.shipments.carrier') },
  { key: 'tracking', label: t('admin.shipments.tracking') },
  { key: 'status', label: t('admin.shipments.status'), type: 'status', sortable: true },
  { key: 'created_at', label: t('admin.shipments.date'), type: 'date', sortable: true },
  { key: 'actions', label: '', type: 'actions' }
])

const filteredShipments = computed(() => {
  if (filter.value === 'all') return shipments.value
  return shipments.value.filter((s) => s.status === filter.value)
})

const rows = computed<TableRow[]>(() =>
  filteredShipments.value.map((s) => ({
    id: s.id,
    order_number: s.order_number ?? '—',
    customer: s.customer_name ?? '—',
    carrier: s.carrier ?? '—',
    tracking: s.tracking_number ?? '—',
    status: capitalize(s.status),
    created_at: s.created_at
  }))
)

const rowActions = computed(() => correctionsEnabled.value ? [{ label: t('admin.shipments.row.edit'), value: 'edit' }] : [])

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

async function loadShipments() {
  loading.value = true
  try {
    const { data: resp } = await adminApi.listShipments()
    shipments.value = resp.data
    totalCount.value = resp.meta.total
  } catch {
    showToast(t('admin.shipments.toast.load_error'))
  } finally {
    loading.value = false
  }
}

function onRowAction(payload: { action: string; row: TableRow }) {
  const shipment = shipments.value.find((s) => s.id === Number(payload.row.id))
  if (shipment) {
    editing.value = reactive({
      ...shipment,
      tracking_number: shipment.tracking_number ?? '',
      carrier: shipment.carrier ?? ''
    })
  }
}

async function saveShipment() {
  if (!correctionsEnabled.value) return
  if (!editing.value) return
  saving.value = true
  try {
    const { data: resp } = await adminApi.updateShipment(editing.value.id, {
      tracking_number: editing.value.tracking_number ?? undefined,
      carrier: editing.value.carrier ?? undefined,
      status: editing.value.status
    })
    const index = shipments.value.findIndex((s) => s.id === editing.value!.id)
    if (index >= 0) shipments.value[index] = resp.data
    editing.value = null
    showToast(t('admin.shipments.toast.saved'))
  } catch {
    showToast(t('admin.shipments.toast.save_error'))
  } finally {
    saving.value = false
  }
}

onMounted(() => loadShipments())
</script>

<template>
  <div class="space-y-6">
    <section class="card space-y-3 border-primary/20 p-4"><p class="text-sm text-muted">{{ t('admin.overview.seller_operations') }}</p><label class="flex items-center gap-2 text-sm font-medium"><input v-model="correctionsEnabled" type="checkbox" class="accent-primary" />{{ t('admin.overview.enable_corrections') }}</label></section>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-ink">{{ $t('admin.shipments.title') }}</h1>
        <span class="chip">{{ totalCount }} {{ $t('admin.shipments.total') }}</span>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="status in statusFilters"
        :key="status"
        type="button"
        :class="filter === status ? 'bg-primary text-white' : 'bg-surface text-gray-600 hover:bg-surface-hover dark:text-muted'"
        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
        @click="filter = status"
      >
        {{ $t(`admin.shipments.filter.${status}`) }}
      </button>
    </div>

    <AdminDataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :search-keys="['order_number', 'tracking', 'carrier']"
      :search-placeholder="t('admin.shipments.search_placeholder')"
      :page-size="10"
      :row-actions="rowActions"
      @row-action="onRowAction"
    />

    <div v-if="correctionsEnabled && editing" class="card p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-base font-semibold text-ink">
          <Truck class="h-5 w-5 text-primary" />
          {{ $t('admin.shipments.edit_title', { order: editing.order_number ?? '' }) }}
        </h2>
        <button class="btn-icon h-8 w-8" type="button" @click="editing = null">
          <X class="h-4 w-4" />
        </button>
      </div>

      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveShipment">
        <div>
          <label class="form-label">{{ $t('admin.shipments.status') }}</label>
          <select v-model="editing.status" class="select w-full">
            <option v-for="status in statusOptions" :key="status" :value="status">
              {{ capitalize(status) }}
            </option>
          </select>
        </div>
        <div>
          <label class="form-label">{{ $t('admin.shipments.carrier') }}</label>
          <input v-model="editing.carrier" type="text" class="input w-full" />
        </div>
        <div>
          <label class="form-label">{{ $t('admin.shipments.tracking') }}</label>
          <input v-model="editing.tracking_number" type="text" class="input w-full" />
        </div>
        <div class="flex items-end justify-end gap-2">
          <button type="button" class="btn-secondary btn-sm" @click="editing = null">
            {{ $t('actions.cancel') }}
          </button>
          <button type="submit" class="btn-primary btn-sm" :disabled="saving">
            <Save class="h-4 w-4" />
            {{ $t('actions.save') }}
          </button>
        </div>

        <div v-if="editing.address_snapshot" class="sm:col-span-2">
          <h3 class="mb-2 text-sm font-semibold text-ink">{{ $t('admin.shipments.shipping_address') }}</h3>
          <div class="rounded-xl bg-canvas p-4 text-sm text-gray-600 dark:text-muted">
            <p>{{ editing.address_snapshot.full_name ?? '—' }}</p>
            <p>{{ editing.address_snapshot.line1 ?? '' }} {{ editing.address_snapshot.line2 ?? '' }}</p>
            <p>
              {{ editing.address_snapshot.city ?? '' }} {{ editing.address_snapshot.state ?? '' }}
              {{ editing.address_snapshot.postal_code ?? '' }}
            </p>
            <p>{{ editing.address_snapshot.country ?? '' }}</p>
          </div>
        </div>
      </form>
    </div>

    <transition name="fade">
      <div v-if="toast" class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover">
        {{ toast }}
      </div>
    </transition>
  </div>
</template>