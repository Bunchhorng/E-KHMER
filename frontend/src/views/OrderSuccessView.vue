<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircle2, FileText, Printer } from 'lucide-vue-next'
import { ordersApi } from '@/api/orders'
import type { ApiOrder } from '@/api/checkout'
import { useAuthStore } from '@/stores/auth'
import StatusTag from '@/components/StatusTag.vue'
import EmptyState from '@/components/EmptyState.vue'
import { formatPrice, formatDate } from '@/utils/format'
import { openPrintWindow, printBlob } from '@/utils/download'
import type { Order, OrderItem, OrderStatus } from '@/types'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const order = ref<Order | null>(null)
const loading = ref(true)
const printingReceipt = ref(false)
const receiptError = ref('')

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

async function printReceipt() {
  const orderNumber = route.params.orderId as string
  if (!orderNumber || printingReceipt.value) return
  const printWindow = openPrintWindow()
  if (!printWindow) {
    receiptError.value = 'Your browser blocked the print preview. Please allow popups and try again.'
    return
  }

  printingReceipt.value = true
  receiptError.value = ''
  try {
    const response = await ordersApi.receipt(orderNumber)
    printBlob(response.data, printWindow)
  } catch {
    printWindow.close()
    receiptError.value = 'The receipt could not be prepared for printing. Please try again.'
  } finally {
    printingReceipt.value = false
  }
}

function mapOrderFromApi(raw: ApiOrder): Order {
  const items: OrderItem[] = raw.items.map((item) => ({
    id: String(item.id),
    productId: String(item.product_id),
    title: item.product_name,
    brand: '',
    image: item.image_path ?? '',
    unitPrice: item.unit_price,
    quantity: item.quantity,
    variant: item.variant_label
      ? { variantId: String(item.product_variant_id), attributes: [], sku: item.sku }
      : null
  }))

  return {
    id: raw.order_number,
    number: raw.order_number,
    items,
    subtotal: raw.subtotal,
    discount: raw.discount_amount,
    shipping: raw.shipping_amount,
    tax: raw.tax_amount,
    total: raw.total,
    status: capitalize(raw.status) as OrderStatus,
    placedAt: raw.placed_at ?? '',
    estimatedDelivery: raw.shipment?.delivered_at ?? '',
    trackingEvents: [{ status: capitalize(raw.status) as OrderStatus, at: raw.placed_at ?? '' }],
    shippingAddress: {
      id: '0',
      label: 'Shipping',
      fullName: raw.customer_name ?? '',
      line1: '',
      line2: '',
      city: '',
      state: '',
      postalCode: '',
      country: '',
      phone: raw.phone ?? '',
      isDefault: false
    },
    paymentMethod: (raw.payment?.method ?? 'cod') as import('@/types').PaymentMethod
  }
}

const paymentLabels: Record<string, string> = {
  cod: 'Cash on Delivery',
  card: 'Credit / Debit Card',
  bank: 'Bank Transfer',
  gateway: 'Online Gateway'
}

const lineRows = computed(() => {
  if (!order.value) return []
  return [
    { labelKey: 'checkout.subtotal', value: order.value.subtotal },
    { labelKey: 'checkout.discount', value: -order.value.discount },
    { labelKey: 'checkout.tax', value: order.value.tax },
    { labelKey: 'checkout.shipping', value: order.value.shipping },
    { labelKey: 'checkout.total', value: order.value.total }
  ]
})

function displayPrice(value: number): string {
  return value < 0 ? `−${formatPrice(Math.abs(value))}` : formatPrice(value)
}

onMounted(async () => {
  const orderNumber = route.params.orderId as string
  if (!orderNumber) {
    loading.value = false
    return
  }
  try {
    const request = authStore.isAuthenticated
      ? ordersApi.get(orderNumber)
      : ordersApi.getGuest(orderNumber)
    const { data } = await request
    order.value = mapOrderFromApi(data.data)
  } catch {
    order.value = null
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="container-app mx-auto max-w-md py-16 text-center text-sm text-gray-500">
    Loading order details...
  </div>

  <div v-else-if="order" class="container-app mx-auto max-w-2xl py-16 text-center">
    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
      <CheckCircle2 :size="40" />
    </div>

    <h1 class="mt-6 text-3xl font-extrabold text-ink">{{ $t('order.confirmed_title') }}</h1>

    <p class="mt-3 text-gray-600">
      {{ $t('order.thank_you', { name: authStore.user?.name ?? 'Customer' }) }}
    </p>

    <div class="mt-8 grid gap-4 text-left sm:grid-cols-3">
      <div class="card p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $t('order.order_number') }}</p>
        <p class="mt-1 font-semibold text-ink">{{ order.number }}</p>
      </div>
      <div class="card p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ order.estimatedDelivery ? 'Delivered' : 'Delivery update' }}</p>
        <p class="mt-1 font-semibold text-ink">{{ order.estimatedDelivery ? formatDate(order.estimatedDelivery) : 'Available once the carrier confirms shipment' }}</p>
      </div>
      <div class="card p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $t('order.payment_method') }}</p>
        <p class="mt-1 font-semibold text-ink">{{ paymentLabels[order.paymentMethod] ?? order.paymentMethod }}</p>
      </div>
    </div>

    <div class="card mx-auto mt-8 max-w-md p-6 text-left">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="font-bold text-ink">{{ $t('checkout.order_summary') }}</h2>
        <StatusTag :status="order.status" />
      </div>

      <div class="space-y-2 text-sm">
        <div v-for="row in lineRows" :key="row.labelKey" class="flex justify-between">
          <span class="text-gray-600">{{ $t(row.labelKey) }}</span>
          <span
            :class="row.labelKey === 'checkout.total' ? 'text-base font-bold text-ink' : row.value < 0 ? 'font-medium text-red-500' : 'font-medium text-ink'"
          >
            {{ displayPrice(row.value) }}
          </span>
        </div>
      </div>

      <div class="my-4 border-t border-border-gray"></div>

      <div class="space-y-3">
        <div v-for="item in order.items" :key="item.id" class="flex items-center gap-3">
          <img :src="item.image" :alt="item.title" class="h-14 w-12 rounded-lg object-cover" />
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold text-ink">{{ item.title }}</p>
            <p class="text-xs text-gray-500">Qty {{ item.quantity }}</p>
          </div>
          <p class="shrink-0 text-sm font-medium text-ink">{{ formatPrice(item.unitPrice * item.quantity) }}</p>
        </div>
      </div>
    </div>

    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
      <RouterLink :to="`/order/tracking/${order.id}`" class="btn-primary">
        {{ $t('order.track_my_order') }}
      </RouterLink>
      <button type="button" class="btn-secondary" :disabled="printingReceipt" @click="printReceipt">
        <Printer v-if="!printingReceipt" class="h-4 w-4" />
        <FileText v-else class="h-4 w-4 animate-pulse" />
        {{ $t('order.receipt') }}
      </button>
      <RouterLink to="/shop" class="btn-secondary">
        {{ $t('actions.continue_shopping') }}
      </RouterLink>
    </div>

    <p class="mt-6 text-xs text-gray-500">
      {{ $t('order.email_sent', { email: authStore.user?.email ?? '' }) }}
    </p>
    <p v-if="receiptError" class="mt-3 text-xs font-medium text-red-600">{{ receiptError }}</p>
  </div>

  <div v-else class="container-app mx-auto max-w-md py-16">
    <EmptyState
      :title="$t('order.not_found_title')"
      :description="$t('order.not_found_description')"
      :cta-label="$t('order.go_to_shop')"
      @cta="router.push('/shop')"
    />
  </div>
</template>
