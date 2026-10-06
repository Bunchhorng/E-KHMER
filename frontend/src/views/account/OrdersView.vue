<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ShoppingBag } from 'lucide-vue-next'
import { ordersApi } from '@/api/orders'
import EmptyState from '@/components/EmptyState.vue'
import StatusTag from '@/components/StatusTag.vue'
import { formatDate, formatPrice } from '@/utils/format'
import { onMounted, ref } from 'vue'
import { extractErrorMessage } from '@/api/errors'

interface OrderRow {
  id: string
  number: string
  placedAt: string
  total: number
  status: string
  itemsCount: number
}

const router = useRouter()
const { t } = useI18n()

const orders = ref<OrderRow[]>([])
const loading = ref(true)
const error = ref('')

function capitalizeStatus(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

async function loadOrders() {
  loading.value = true
  error.value = ''
  try {
    const res = await ordersApi.list()
    orders.value = (res.data.data ?? []).map((o) => ({
      id: o.order_number,
      number: o.order_number,
      placedAt: o.placed_at,
      total: o.total,
      status: capitalizeStatus(o.status),
      itemsCount: o.items_count
    }))
  } catch (requestError) {
    error.value = extractErrorMessage(requestError, 'Could not load your orders.')
  } finally {
    loading.value = false
  }
}

onMounted(loadOrders)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('nav.my_orders') }}</h1>
      <span v-if="!loading" class="chip w-fit">{{ $t('order.count_orders', { count: orders.length }) }}</span>
    </div>

    <div v-if="loading" class="card p-10 text-center">
      <p class="text-sm text-gray-500 dark:text-muted">{{ $t('common.loading') }}</p>
    </div>

    <div v-else-if="error" class="card p-10 text-center">
      <p class="text-sm text-red-600">{{ error }}</p>
      <button type="button" class="btn-secondary btn-sm mt-4" @click="loadOrders">{{ $t('actions.retry') }}</button>
    </div>

    <div v-else-if="orders.length" class="card overflow-x-auto p-0">
      <table class="w-full min-w-[720px] text-sm">
        <thead>
          <tr class="border-b border-border-gray dark:border-border-gray text-left text-xs uppercase tracking-wide text-gray-500 dark:text-muted">
            <th class="px-4 py-3 font-semibold">{{ $t('order.order') }}</th>
            <th class="px-4 py-3 font-semibold">{{ $t('order.placed') }}</th>
            <th class="px-4 py-3 font-semibold">{{ $t('order.items') }}</th>
            <th class="px-4 py-3 font-semibold">{{ $t('order.total') }}</th>
            <th class="px-4 py-3 font-semibold">{{ $t('order.status') }}</th>
            <th class="px-4 py-3 font-semibold">{{ $t('order.action') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border-gray dark:divide-border-gray">
          <tr v-for="o in orders" :key="o.id">
            <td class="px-4 py-3">
              <RouterLink
                :to="{ name: 'account-order-detail', params: { orderNumber: o.number } }"
                class="font-semibold text-primary hover:underline"
              >
                {{ o.number }}
              </RouterLink>
            </td>
            <td class="px-4 py-3 text-gray-600 dark:text-muted">{{ formatDate(o.placedAt) }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-muted">{{ o.itemsCount }} {{ t('order.items') }}</td>
            <td class="px-4 py-3 font-medium text-ink dark:text-ink">{{ formatPrice(o.total) }}</td>
            <td class="px-4 py-3">
              <StatusTag :status="o.status" />
            </td>
            <td class="px-4 py-3">
              <RouterLink
                :to="`/order/tracking/${o.number}`"
                class="btn-secondary btn-sm"
              >
                {{ $t('order.track') }}
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EmptyState
      v-else
      :title="$t('account.no_orders_title')"
      :description="$t('account.no_orders_description')"
      :cta-label="$t('account.start_shopping')"
      @cta="router.push('/shop')"
    >
      <template #icon>
        <ShoppingBag class="h-10 w-10 text-gray-300" />
      </template>
    </EmptyState>
  </div>
</template>
