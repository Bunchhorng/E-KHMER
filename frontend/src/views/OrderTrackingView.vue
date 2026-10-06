<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Package, Check } from 'lucide-vue-next'
import type { OrderStatus, Order, OrderItem, TrackingEvent } from '@/types'
import { ordersApi } from '@/api/orders'
import type { ApiOrder } from '@/api/checkout'
import { useAuthStore } from '@/stores/auth'
import StatusTag from '@/components/StatusTag.vue'
import EmptyState from '@/components/EmptyState.vue'
import { formatDate, formatDateTime, formatPrice } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const order = ref<Order | null>(null)
const loading = ref(true)

const FLOW: OrderStatus[] = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered']

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
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

  const status = capitalize(raw.status) as OrderStatus

  const trackingEvents: TrackingEvent[] = (raw.tracking_events ?? []).map((e) => ({
    status: capitalize(e.status) as OrderStatus,
    at: e.at
  }))

  // The API currently supplies an actual delivery timestamp, not a carrier ETA.
  // Do not manufacture a date from the order timestamp: that misleads customers.
  const estimatedDelivery = raw.shipment?.delivered_at ?? ''

  return {
    id: raw.order_number,
    number: raw.order_number,
    items,
    subtotal: raw.subtotal,
    discount: raw.discount_amount,
    shipping: raw.shipping_amount,
    tax: raw.tax_amount,
    total: raw.total,
    status,
    placedAt: raw.placed_at ?? '',
    estimatedDelivery,
    trackingEvents,
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

const currentIndex = computed(() =>
  order.value ? FLOW.indexOf(order.value.status) : -1
)

function timestampFor(status: OrderStatus): string | null {
  if (!order.value) return null
  const event = order.value.trackingEvents.find((e) => e.status === status)
  return event ? formatDateTime(event.at) : null
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

  <div v-else-if="order" class="container-app mx-auto max-w-3xl py-10">
    <div class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('order.track_title', { number: order.number }) }}</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-muted dark:text-gray-500">
            <template v-if="order.estimatedDelivery">{{ $t('order.track_subtitle', { placed: formatDate(order.placedAt), arrives: formatDate(order.estimatedDelivery) }) }}</template>
            <template v-else>Placed {{ formatDate(order.placedAt) }} · Delivery date will appear when the carrier confirms it.</template>
          </p>
        </div>
        <StatusTag :status="order.status" />
      </div>
    </div>

    <div v-if="order.status === 'Delivered'" class="mt-6 flex items-center gap-2 rounded-xl bg-emerald-100 p-4 text-sm font-medium text-emerald-700">
      <Check class="h-5 w-5" />
      {{ $t('order.delivered_on', { date: formatDate(order.estimatedDelivery) }) }}
    </div>

    <div class="card mt-6 p-6">
      <h2 class="mb-6 text-lg font-bold text-ink dark:text-ink">{{ $t('order.order_progress') }}</h2>

      <div class="hidden sm:flex sm:items-start">
        <template v-for="(status, i) in FLOW" :key="status">
          <div class="flex flex-col items-center">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full font-semibold"
              :class="
                i < currentIndex
                  ? 'bg-success text-white'
                  : i === currentIndex
                    ? 'bg-primary text-white'
                    : 'bg-gray-200 dark:bg-surface-hover text-gray-500 dark:text-muted'
              "
            >
              <Check v-if="i < currentIndex" class="h-5 w-5" />
              <span v-else>{{ i + 1 }}</span>
            </div>
            <p class="mt-2 text-xs font-semibold" :class="i === currentIndex ? 'text-primary' : i < currentIndex ? 'text-ink' : 'text-gray-400 dark:text-gray-500'">
              {{ $t('status.' + status.toLowerCase()) }}
            </p>
            <p class="mt-1 text-center text-[10px] text-gray-500 dark:text-muted dark:text-gray-500">
              {{ timestampFor(status) ?? '' }}
            </p>
          </div>
          <div
            v-if="i < FLOW.length - 1"
            class="mx-2 mt-5 h-0.5 flex-1"
            :class="i < currentIndex ? 'bg-success' : 'bg-gray-200'"
          ></div>
        </template>
      </div>

      <div class="sm:hidden">
        <div v-for="(status, i) in FLOW" :key="status" class="flex gap-4">
          <div class="flex flex-col items-center">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full font-semibold"
              :class="
                i < currentIndex
                  ? 'bg-success text-white'
                  : i === currentIndex
                    ? 'bg-primary text-white'
                    : 'bg-gray-200 dark:bg-surface-hover text-gray-500 dark:text-muted'
              "
            >
              <Check v-if="i < currentIndex" class="h-5 w-5" />
              <span v-else>{{ i + 1 }}</span>
            </div>
            <div
              v-if="i < FLOW.length - 1"
              class="mt-1 w-0.5 flex-1"
              :class="i < currentIndex ? 'bg-success' : 'bg-gray-200 dark:bg-surface-hover'"
            ></div>
          </div>
          <div class="pb-8">
            <p class="text-sm font-semibold" :class="i === currentIndex ? 'text-primary' : i < currentIndex ? 'text-ink' : 'text-gray-400 dark:text-gray-500'">
              {{ $t('status.' + status.toLowerCase()) }}
            </p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-muted dark:text-gray-500">
              {{ timestampFor(status) ?? '' }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="order.estimatedDelivery" class="card mt-6 flex items-center gap-4 p-6">
      <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
        <Package class="h-6 w-6" />
      </div>
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ order.status === 'Delivered' ? 'Delivered' : $t('order.estimated_delivery') }}</p>
        <p class="font-semibold text-ink dark:text-ink">{{ formatDate(order.estimatedDelivery) }}</p>
      </div>
    </div>

    <div class="card mt-6 p-6">
      <h2 class="mb-4 text-lg font-bold text-ink dark:text-ink">{{ $t('order.items') }}</h2>
      <div class="divide-y divide-border-gray dark:divide-border-gray">
        <div v-for="item in order.items" :key="item.id" class="flex items-center gap-4 py-4">
          <img :src="item.image" :alt="item.title" class="h-16 w-14 rounded-lg object-cover" />
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-ink dark:text-ink">{{ item.title }}</p>
            <p class="text-xs text-gray-500 dark:text-muted dark:text-gray-500">
              {{ item.brand }} · {{ item.variant?.attributes ? item.variant.attributes.map((a) => a.value).join(', ') : '' }}
            </p>
            <p class="text-xs text-gray-500 dark:text-muted dark:text-gray-500">{{ $t('order.qty', { qty: item.quantity }) }}</p>
          </div>
          <p class="shrink-0 font-medium text-ink dark:text-ink">{{ formatPrice(item.unitPrice * item.quantity) }}</p>
        </div>
      </div>
    </div>
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
