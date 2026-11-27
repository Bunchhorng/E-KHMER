<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  AlertTriangle,
  Check,
  CheckCircle2,
  Clock,
  Copy,
  FileText,
  MapPin,
  Printer,
  ShoppingBag,
  Store,
  Truck,
  XCircle,
} from 'lucide-vue-next'
import { ordersApi } from '@/api/orders'
import type { ApiOrder, ApiShopOrder } from '@/api/checkout'
import { useAuthStore } from '@/stores/auth'
import AssetImage from '@/components/AssetImage.vue'
import CustomerPaymentStatus from '@/components/CustomerPaymentStatus.vue'
import EmptyState from '@/components/EmptyState.vue'
import StatusTag from '@/components/StatusTag.vue'
import { formatDateTime, formatPrice } from '@/utils/format'
import { openPrintWindow, printBlob } from '@/utils/download'

interface DisplaySellerGroup {
  key: string
  shopName: string
  shopOrderNumber: string | null
  status: string | null
  paymentStatus: string | null
  shipment: ApiShopOrder['shipment'] | ApiOrder['shipment'] | null
  items: ApiOrder['items']
  subtotal: number | null
  shippingAmount: number | null
  total: number | null
}

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()

const order = ref<ApiOrder | null>(null)
const loading = ref(true)
const printingReceipt = ref(false)
const receiptError = ref('')
const copiedOrderNumber = ref(false)
let copyTimer: number | undefined

const orderCurrency = computed(() => order.value?.currency || 'USD')
const isCancelled = computed(() => ['cancelled', 'refunded'].includes(order.value?.status ?? ''))
const isPaid = computed(() => order.value?.payment_status === 'paid')
const isPendingBakong = computed(
  () => order.value?.payment?.method === 'bakong' && !isPaid.value && !isCancelled.value
)

const headerState = computed(() => {
  if (isCancelled.value) {
    return {
      title: t('order.cancelled_title'),
      tone: 'border-danger/40 bg-danger/10 text-danger',
      icon: XCircle,
    }
  }
  if (isPendingBakong.value) {
    return {
      title: t('order.pending_title'),
      tone: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-300',
      icon: Clock,
    }
  }
  return {
    title: t('order.confirmed_title'),
    tone: 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-success/40 dark:bg-success/10 dark:text-success',
    icon: CheckCircle2,
  }
})

const paymentMethodLabel = computed(() => {
  const method = order.value?.payment?.method ?? 'cod'
  const map: Record<string, string> = {
    cod: t('order.method_cod'),
    bakong: t('order.method_bakong'),
    card: t('order.method_card'),
    bank: t('order.method_bank'),
    gateway: t('order.method_gateway'),
  }
  return map[method] ?? method
})

function formatPaymentStatusLabel(status: string | null | undefined, method?: string | null): string {
  if (isCancelled.value) return t('order.payment_status_cancelled')
  switch (status) {
    case 'paid':
      return t('order.payment_status_paid')
    case 'partial':
      return t('order.payment_status_partial')
    case 'partially_refunded':
      return t('order.payment_status_partially_refunded')
    case 'refunded':
      return t('order.payment_status_refunded')
    case 'failed':
      return t('order.payment_status_failed')
    default:
      return method === 'cod' ? t('order.payment_status_unpaid_cod') : t('order.payment_status_unpaid')
  }
}

const paymentStatusBadgeClass = computed(() => {
  if (isCancelled.value || order.value?.payment_status === 'failed') {
    return 'border-danger/40 bg-danger/10 text-danger'
  }
  if (isPaid.value) {
    return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-success/40 dark:bg-success/10 dark:text-success'
  }
  return 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-300'
})

const sellerGroups = computed<DisplaySellerGroup[]>(() => {
  const current = order.value
  if (!current) return []

  if (current.shop_orders?.length) {
    return current.shop_orders.map((shopOrder) => ({
      key: `shop-order-${shopOrder.id}`,
      shopName: shopOrder.shop?.name || 'E-KHMER',
      shopOrderNumber: shopOrder.shop_order_number,
      status: shopOrder.status,
      paymentStatus: shopOrder.payment_status,
      shipment: shopOrder.shipment ?? null,
      items: shopOrder.items?.length ? shopOrder.items : current.items.filter((i) => i.shop_id === shopOrder.shop?.id),
      subtotal: shopOrder.subtotal,
      shippingAmount: shopOrder.shipping_amount,
      total: shopOrder.total,
    }))
  }

  const byShop = new Map<string, DisplaySellerGroup>()
  for (const item of current.items) {
    const shopId = item.shop?.id ?? item.shop_id ?? 0
    const shopName = item.shop?.name || 'E-KHMER'
    const key = `shop-${shopId}`
    const existing = byShop.get(key)
    if (existing) {
      existing.items.push(item)
    } else {
      byShop.set(key, {
        key,
        shopName,
        shopOrderNumber: null,
        status: null,
        paymentStatus: null,
        shipment: current.shipment ?? null,
        items: [item],
        subtotal: null,
        shippingAmount: null,
        total: null,
      })
    }
  }
  return [...byShop.values()]
})

const shippingAddressLines = computed(() => {
  const addr = order.value?.shipping_address || order.value?.billing_address
  if (!addr) return []
  const cityLine = [addr.city, addr.state, addr.postal_code].filter(Boolean).join(', ')
  return [
    addr.full_name || order.value?.customer_name,
    addr.address_line1 || addr.address_line,
    addr.address_line2,
    cityLine,
    addr.country,
    addr.phone || order.value?.phone,
  ].filter(Boolean) as string[]
})

async function copyOrderNumber(): Promise<void> {
  if (!order.value?.order_number) return
  try {
    await navigator.clipboard.writeText(order.value.order_number)
    copiedOrderNumber.value = true
    window.clearTimeout(copyTimer)
    copyTimer = window.setTimeout(() => {
      copiedOrderNumber.value = false
    }, 2500)
  } catch {
    /* Clipboard API unavailable in restricted contexts */
  }
}

async function printReceipt(): Promise<void> {
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

onMounted(async () => {
  const orderNumber = route.params.orderId as string
  if (!orderNumber) {
    loading.value = false
    return
  }
  try {
    const request = authStore.isAuthenticated ? ordersApi.get(orderNumber) : ordersApi.getGuest(orderNumber)
    const { data } = await request
    order.value = data.data
  } catch {
    order.value = null
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-5xl py-6 pb-[max(2.5rem,env(safe-area-inset-bottom))] sm:py-8 lg:pb-10">
    <div v-if="loading" class="space-y-4">
      <div class="h-28 animate-pulse border border-border-gray bg-surface-muted"></div>
      <div class="grid gap-4 sm:grid-cols-4">
        <div v-for="n in 4" :key="n" class="h-20 animate-pulse border border-border-gray bg-surface-muted"></div>
      </div>
    </div>

    <div v-else-if="order" class="space-y-6">
      <section class="card rounded-none p-5 text-center sm:p-6">
        <div
          class="mx-auto flex h-14 w-14 items-center justify-center rounded-none border"
          :class="headerState.tone"
        >
          <component :is="headerState.icon" :size="28" aria-hidden="true" />
        </div>

        <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-ink sm:text-3xl">
          {{ headerState.title }}
        </h1>

        <p class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-text-secondary">
          {{ $t('order.thank_you', { name: order.customer_name ?? authStore.user?.name ?? 'Customer' }) }}
        </p>

        <div
          v-if="isPendingBakong"
          role="status"
          class="mx-auto mt-4 flex max-w-2xl flex-wrap items-center justify-between gap-3 rounded-none border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-left text-xs text-amber-900 dark:text-amber-200 sm:text-sm"
        >
          <div class="flex items-start gap-2">
            <AlertTriangle :size="16" class="mt-0.5 shrink-0" aria-hidden="true" />
            <span>{{ $t('order.pending_bakong_notice') }}</span>
          </div>
          <RouterLink
            :to="{ name: 'bakong-payment', params: { orderNumber: order.order_number } }"
            class="btn-primary btn-sm shrink-0 rounded-none"
          >
            {{ $t('order.complete_bakong_payment') }}
          </RouterLink>
        </div>

        <div
          v-else-if="order.payment_status === 'failed'"
          role="alert"
          class="mx-auto mt-4 max-w-2xl rounded-none border border-danger/40 bg-danger/10 px-4 py-3 text-left text-xs font-medium text-danger sm:text-sm"
        >
          {{ $t('order.failed_payment_notice') }}
        </div>
      </section>

      <CustomerPaymentStatus v-if="order.payment?.method === 'cod'" :order="order" />

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card rounded-none p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-muted">{{ $t('order.order_number') }}</p>
          <div class="mt-1.5 flex items-center justify-between gap-2">
            <p class="truncate font-mono text-sm font-bold text-ink">{{ order.order_number }}</p>
            <button
              type="button"
              class="inline-flex min-h-8 shrink-0 items-center gap-1 rounded-none border border-border-gray bg-surface-muted px-2 py-1 text-[11px] font-semibold text-ink transition-colors hover:border-border-strong hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus print:hidden"
              :aria-label="$t('order.copy_order_number')"
              @click="copyOrderNumber"
            >
              <Check v-if="copiedOrderNumber" :size="12" class="text-emerald-700 dark:text-success" aria-hidden="true" />
              <Copy v-else :size="12" aria-hidden="true" />
              <span>{{ copiedOrderNumber ? $t('order.copied') : 'Copy' }}</span>
            </button>
          </div>
        </div>

        <div class="card rounded-none p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-muted">{{ $t('order.order_date') }}</p>
          <p class="mt-1.5 text-sm font-semibold text-ink">
            {{ order.placed_at ? formatDateTime(order.placed_at) : '—' }}
          </p>
        </div>

        <div class="card rounded-none p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-muted">{{ $t('order.order_status') }}</p>
          <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <StatusTag :status="order.status" />
            <span
              class="inline-flex items-center rounded-none border px-2 py-0.5 text-[11px] font-semibold"
              :class="paymentStatusBadgeClass"
            >
              {{ formatPaymentStatusLabel(order.payment_status, order.payment?.method) }}
            </span>
          </div>
        </div>

        <div class="card rounded-none p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-muted">{{ $t('order.payment_method') }}</p>
          <p class="mt-1.5 text-sm font-semibold text-ink">{{ paymentMethodLabel }}</p>
          <p class="mt-0.5 text-xs font-bold text-primary">{{ formatPrice(order.total, orderCurrency) }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(19rem,1fr)] lg:items-start">
        <div class="min-w-0 space-y-4">
          <section
            v-for="group in sellerGroups"
            :key="group.key"
            class="card overflow-hidden rounded-none"
          >
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-border-gray bg-surface-muted/60 px-4 py-3">
              <div class="flex min-w-0 items-center gap-2">
                <Store :size="15" class="shrink-0 text-info" aria-hidden="true" />
                <h2 class="truncate text-sm font-bold text-ink">{{ group.shopName }}</h2>
                <span v-if="group.shopOrderNumber" class="font-mono text-[11px] text-muted">{{ group.shopOrderNumber }}</span>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <StatusTag v-if="group.status" :status="group.status" />
                <span
                  v-if="sellerGroups.length > 1 && group.paymentStatus"
                  class="border border-border-gray bg-surface px-2 py-0.5 text-[11px] font-medium text-text-secondary"
                >
                  {{ formatPaymentStatusLabel(group.paymentStatus, order.payment?.method) }}
                </span>
              </div>
            </header>

            <div class="divide-y divide-border-gray px-4">
              <div v-for="item in group.items" :key="item.id" class="flex items-start gap-3 py-3.5">
                <AssetImage
                  :src="item.image_path"
                  :alt="item.product_name"
                  class="h-16 w-16 shrink-0 rounded-none border border-border-gray"
                />
                <div class="min-w-0 flex-1">
                  <p class="line-clamp-2 break-words text-sm font-semibold text-ink">{{ item.product_name }}</p>
                  <p v-if="item.variant_label" class="mt-0.5 text-xs text-text-secondary">{{ item.variant_label }}</p>
                  <p class="mt-1 text-xs text-muted">
                    {{ formatPrice(item.unit_price, orderCurrency) }} × {{ item.quantity }}
                    <span v-if="item.sku"> · SKU: {{ item.sku }}</span>
                  </p>
                </div>
                <p class="shrink-0 whitespace-nowrap text-sm font-bold text-ink">
                  {{ formatPrice(item.line_total, orderCurrency) }}
                </p>
              </div>
            </div>

            <div class="border-t border-border-gray bg-surface-muted/30 px-4 py-3 text-xs">
              <div v-if="group.shipment && (group.shipment.carrier || group.shipment.tracking_number)" class="flex flex-wrap items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 font-semibold text-ink">
                  <Truck :size="14" class="text-info" aria-hidden="true" />
                  {{ $t('order.delivery_tracking') }}:
                  <span class="font-normal text-text-secondary">
                    {{ [group.shipment.carrier, group.shipment.tracking_number].filter(Boolean).join(' · ') }}
                  </span>
                </span>
                <StatusTag :status="group.shipment.status" />
              </div>
              <p v-else class="inline-flex items-center gap-1.5 text-muted">
                <Truck :size="14" class="shrink-0" aria-hidden="true" />
                <span>{{ $t('order.tracking_pending') }}</span>
              </p>
            </div>
          </section>
        </div>

        <section class="card space-y-4 rounded-none p-5">
          <div class="flex items-center justify-between border-b border-border-gray pb-3">
            <h2 class="text-base font-bold text-ink">{{ $t('checkout.order_summary') }}</h2>
            <StatusTag :status="order.status" />
          </div>

          <div class="space-y-2.5 text-sm">
            <div class="flex justify-between gap-3">
              <span class="text-text-secondary">{{ $t('checkout.subtotal') }}</span>
              <span class="font-medium text-ink">{{ formatPrice(order.subtotal, orderCurrency) }}</span>
            </div>
            <div v-if="order.discount_amount > 0" class="flex justify-between gap-3">
              <span class="text-text-secondary">
                {{ $t('checkout.discount') }}<span v-if="order.coupon_code"> ({{ order.coupon_code }})</span>
              </span>
              <span class="font-medium text-danger">−{{ formatPrice(order.discount_amount, orderCurrency) }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-text-secondary">{{ $t('checkout.shipping') }}</span>
              <span class="font-medium text-ink">{{ formatPrice(order.shipping_amount, orderCurrency) }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-text-secondary">{{ $t('checkout.tax') }}</span>
              <span class="font-medium text-ink">{{ formatPrice(order.tax_amount, orderCurrency) }}</span>
            </div>
            <div class="flex items-baseline justify-between gap-3 border-t border-border-gray pt-3">
              <span class="font-bold text-ink">{{ $t('checkout.total') }}</span>
              <span class="text-xl font-extrabold text-ink">{{ formatPrice(order.total, orderCurrency) }}</span>
            </div>
          </div>

          <div v-if="shippingAddressLines.length" class="border-t border-border-gray pt-3.5">
            <p class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted">
              <MapPin :size="12" class="text-info" aria-hidden="true" />
              <span>{{ $t('order.shipping_address') }}</span>
            </p>
            <div class="mt-1.5 space-y-0.5 text-xs leading-relaxed text-text-secondary">
              <p v-for="(line, index) in shippingAddressLines" :key="index" :class="{ 'font-semibold text-ink': index === 0 }">
                {{ line }}
              </p>
            </div>
          </div>

          <div v-if="order.email" class="border-t border-border-gray pt-3 text-xs text-muted">
            Contact email: {{ order.email }}
          </div>
        </section>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center print:hidden">
        <RouterLink :to="`/order/tracking/${order.order_number}`" class="btn-primary min-h-11 rounded-none">
          {{ $t('order.track_my_order') }}
        </RouterLink>
        <button
          v-if="authStore.isAuthenticated"
          type="button"
          class="btn-secondary min-h-11 rounded-none"
          :disabled="printingReceipt"
          @click="printReceipt"
        >
          <Printer v-if="!printingReceipt" :size="16" aria-hidden="true" />
          <FileText v-else :size="16" class="animate-pulse" aria-hidden="true" />
          {{ $t('order.receipt') }}
        </button>
        <RouterLink to="/shop" class="btn-secondary min-h-11 rounded-none">
          <ShoppingBag :size="16" aria-hidden="true" />
          {{ $t('actions.continue_shopping') }}
        </RouterLink>
      </div>

      <p v-if="!authStore.isAuthenticated" class="text-center text-xs text-muted sm:text-sm print:hidden">
        Keep your order number and use this browser to track it. If your browser session is lost,
        <RouterLink to="/support" class="font-semibold text-primary hover:underline">contact support</RouterLink>.
      </p>
      <p v-if="receiptError" role="alert" class="text-center text-xs font-medium text-danger print:hidden">
        {{ receiptError }}
      </p>
    </div>

    <EmptyState
      v-else
      :title="$t('order.not_found_title')"
      :description="$t('order.not_found_description')"
      :cta-label="$t('order.go_to_shop')"
      @cta="router.push('/shop')"
    />
  </div>
</template>
