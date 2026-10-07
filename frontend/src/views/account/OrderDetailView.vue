<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft, FileText, Package, PackageCheck, Printer } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { ordersApi } from '@/api/orders'
import type { ApiOrder } from '@/api/checkout'
import StatusTag from '@/components/StatusTag.vue'
import ShopDeliveryTracker from '@/components/ShopDeliveryTracker.vue'
import AssetImage from '@/components/AssetImage.vue'
import { formatDateTime, formatPrice } from '@/utils/format'
import { openPrintWindow, printBlob } from '@/utils/download'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const order = ref<ApiOrder | null>(null)
const loading = ref(true)

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function renderAddress(address: Record<string, string> | null): string[] {
  if (!address) return [t('account.order_detail.no_address')]
  const lines = [address.full_name, address.address_line1, address.address_line2]
    .filter(Boolean) as string[]
  const city = [address.city, address.state, address.postal_code].filter(Boolean).join(', ')
  if (city) lines.push(city)
  if (address.country) lines.push(address.country)
  if (address.phone) lines.push(address.phone)
  return lines.length ? lines : [t('account.order_detail.no_address')]
}

const printingReceipt = ref(false)

async function printReceipt() {
  const orderNumber = route.params.orderNumber as string
  if (!orderNumber || printingReceipt.value) return
  const printWindow = openPrintWindow()
  if (!printWindow) return

  printingReceipt.value = true
  try {
    const response = await ordersApi.receipt(orderNumber)
    printBlob(response.data, printWindow)
  } catch {
    printWindow.close()
  } finally {
    printingReceipt.value = false
  }
}

onMounted(async () => {
  const orderNumber = route.params.orderNumber as string
  if (!orderNumber) {
    loading.value = false
    router.replace({ name: 'account-orders' })
    return
  }
  try {
    const { data } = await ordersApi.get(orderNumber)
    order.value = data.data
  } catch {
    order.value = null
    router.replace({ name: 'account-orders' })
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="card p-10 text-center">
    <p class="text-sm text-gray-500 dark:text-muted">{{ $t('common.loading') }}</p>
  </div>

  <template v-else-if="order">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <RouterLink
          :to="{ name: 'account-orders' }"
          class="btn-icon"
          :title="$t('account.order_detail.back')"
        >
          <ArrowLeft class="h-5 w-5" />
        </RouterLink>
        <div>
          <h1 class="text-2xl font-bold text-ink dark:text-ink">
            {{ $t('order.track_title', { number: order.order_number }) }}
          </h1>
          <p class="mt-0.5 text-sm text-gray-500 dark:text-muted">
            {{ $t('order.placed', { date: formatDateTime(order.placed_at ?? '') }) }}
          </p>
        </div>
      </div>
      <StatusTag :status="order.status" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <RouterLink :to="`/order/tracking/${order.order_number}`" class="card flex items-center gap-4 p-5 transition hover:bg-canvas/40">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
          <Package class="h-6 w-6" />
        </div>
        <div class="flex-1">
          <p class="font-semibold text-ink dark:text-ink">{{ $t('order.order_progress') }}</p>
          <p class="text-sm text-gray-500 dark:text-muted">{{ $t('account.order_detail.view_tracking') }}</p>
        </div>
        <span class="text-primary">→</span>
      </RouterLink>
      <button type="button" class="btn-secondary" :disabled="printingReceipt" @click="printReceipt">
        <Printer v-if="!printingReceipt" class="h-4 w-4" />
        <FileText v-else class="h-4 w-4 animate-pulse" />
        {{ $t('account.order_detail.receipt') }}
      </button>
    </div>

    <ShopDeliveryTracker v-if="order.shop_orders?.length" class="mt-6" :allocations="order.shop_orders" :platform-shipment="order.shipment" />

    <div class="card mt-6 overflow-hidden p-0">
      <div class="border-b border-border-gray p-4">
        <h2 class="text-base font-semibold text-ink dark:text-ink">{{ $t('order.items') }}</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead>
            <tr class="border-b border-border-gray bg-canvas/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-muted">
              <th class="px-4 py-3">{{ $t('account.order_detail.item') }}</th>
              <th class="px-4 py-3">{{ $t('product.sku') }}</th>
              <th class="px-4 py-3 text-right">{{ $t('account.order_detail.unit_price') }}</th>
              <th class="px-4 py-3 text-right">{{ $t('order.qty') }}</th>
              <th class="px-4 py-3 text-right">{{ $t('order.total') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border-gray dark:divide-border-gray">
            <tr v-for="item in order.items" :key="item.id">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <AssetImage
                    :src="item.image_path"
                    :alt="item.product_name"
                    class="h-12 w-10 rounded-lg border border-border-gray"
                  />
                  <div class="min-w-0">
                    <p class="font-medium text-ink dark:text-ink">{{ item.product_name }}</p>
                    <p v-if="item.variant_label" class="text-xs text-gray-500 dark:text-muted">
                      {{ item.variant_label }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-muted">{{ item.sku ?? '—' }}</td>
              <td class="px-4 py-3 text-right text-gray-600 dark:text-muted">{{ formatPrice(item.unit_price) }}</td>
              <td class="px-4 py-3 text-right text-gray-600 dark:text-muted">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right font-semibold text-ink dark:text-ink">{{ formatPrice(item.line_total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex flex-col gap-1.5 border-t border-border-gray bg-canvas/40 p-4 text-sm sm:items-end">
        <div class="flex w-full justify-between sm:w-72">
          <span class="text-gray-500">{{ $t('admin.order_detail.subtotal') }}</span>
          <span class="text-ink">{{ formatPrice(order.subtotal) }}</span>
        </div>
        <div v-if="order.discount_amount > 0" class="flex w-full justify-between sm:w-72">
          <span class="text-gray-500">{{ $t('admin.order_detail.discount') }}</span>
          <span class="text-red-600">-{{ formatPrice(order.discount_amount) }}</span>
        </div>
        <div class="flex w-full justify-between sm:w-72">
          <span class="text-gray-500">{{ $t('admin.order_detail.shipping') }}</span>
          <span class="text-ink">{{ formatPrice(order.shipping_amount) }}</span>
        </div>
        <div v-if="order.tax_amount > 0" class="flex w-full justify-between sm:w-72">
          <span class="text-gray-500">{{ $t('admin.order_detail.tax') }}</span>
          <span class="text-ink">{{ formatPrice(order.tax_amount) }}</span>
        </div>
        <div class="flex w-full justify-between border-t border-border-gray pt-2 sm:w-72">
          <span class="font-semibold text-ink">{{ $t('order.total') }}</span>
          <span class="font-bold text-ink">{{ formatPrice(order.total) }}</span>
        </div>
      </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <div class="card p-5">
        <h2 class="text-sm font-semibold text-ink dark:text-ink">{{ $t('account.order_detail.shipping_address') }}</h2>
        <div class="mt-3 space-y-0.5 text-sm text-gray-600 dark:text-muted">
          <p v-for="(line, i) in renderAddress(order.shipping_address)" :key="i">{{ line }}</p>
        </div>
      </div>

      <div class="card p-5">
        <h2 class="text-sm font-semibold text-ink dark:text-ink">{{ $t('account.order_detail.payment') }}</h2>
        <div v-if="order.payment" class="mt-3 space-y-2 text-sm">
          <div class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('order.payment_method') }}</span>
            <span class="font-medium text-ink dark:text-ink">{{ capitalize(order.payment.method) }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('order.status') }}</span>
            <span class="text-ink dark:text-ink">
              {{ capitalize(order.payment.status) }}
            </span>
          </div>
          <div v-if="order.payment.amount" class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('account.order_detail.paid_amount') }}</span>
            <span class="font-medium text-ink dark:text-ink">{{ formatPrice(order.payment.amount) }}</span>
          </div>
          <div v-if="order.payment.paid_at" class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('account.order_detail.paid_at') }}</span>
            <span class="text-ink dark:text-ink">{{ formatDateTime(order.payment.paid_at) }}</span>
          </div>
        </div>
        <p v-else class="mt-3 text-sm text-gray-500">{{ $t('account.order_detail.no_payment') }}</p>
      </div>

      <div class="card p-5 md:col-span-2">
        <h2 class="text-sm font-semibold text-ink dark:text-ink">{{ $t('account.order_detail.shipment') }}</h2>
        <div v-if="order.shipment" class="mt-3 space-y-2 text-sm">
          <div class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('account.order_detail.shipment_status') }}</span>
            <span class="text-ink dark:text-ink">{{ capitalize(order.shipment.status) }}</span>
          </div>
          <div v-if="order.shipment.carrier" class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('account.order_detail.carrier') }}</span>
            <span class="text-ink dark:text-ink">{{ order.shipment.carrier }}</span>
          </div>
          <div v-if="order.shipment.tracking_number" class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('account.order_detail.tracking_number') }}</span>
            <span class="font-mono text-xs text-ink dark:text-ink">{{ order.shipment.tracking_number }}</span>
          </div>
          <div v-if="order.shipment.shipped_at" class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('account.order_detail.shipped_at') }}</span>
            <span class="text-ink dark:text-ink">{{ formatDateTime(order.shipment.shipped_at) }}</span>
          </div>
          <div v-if="order.shipment.delivered_at" class="flex justify-between gap-3">
            <span class="text-gray-500">{{ $t('account.order_detail.delivered_at') }}</span>
            <span class="text-ink dark:text-ink">{{ formatDateTime(order.shipment.delivered_at) }}</span>
          </div>
        </div>
        <div v-else class="mt-3 flex items-center gap-3 text-sm text-gray-500">
          <PackageCheck class="h-5 w-5 text-gray-400" />
          {{ $t('account.order_detail.no_shipment') }}
        </div>
      </div>
    </div>
  </template>
</template>
