<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, ChevronRight, PackageCheck, FileText, Printer } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { ApiOrder } from '@/api/checkout'
import StatusTag from '@/components/StatusTag.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import { formatDateTime, formatPrice } from '@/utils/format'
import { openPrintWindow, printBlob } from '@/utils/download'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const order = ref<ApiOrder | null>(null)
const loading = ref(true)
const transitioning = ref(false)

const transitionMap: Record<string, string[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['processing', 'cancelled', 'refunded'],
  processing: ['shipped', 'cancelled'],
  shipped: ['delivered', 'refunded'],
  delivered: ['refunded']
}

const allowedTransitions = computed<string[]>(() =>
  order.value ? (transitionMap[order.value.status] ?? []) : []
)

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function renderAddress(address: Record<string, string> | null): string[] {
  if (!address) return [t('admin.order_detail.no_address')]
  const lines = [address.full_name, address.address_line1, address.address_line2]
    .filter(Boolean) as string[]
  const city = [address.city, address.state, address.postal_code].filter(Boolean).join(', ')
  if (city) lines.push(city)
  if (address.country) lines.push(address.country)
  if (address.phone) lines.push(address.phone)
  return lines.length ? lines : [t('admin.order_detail.no_address')]
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

async function loadOrder() {
  const id = Number(route.params.id)
  if (!id) {
    router.replace({ name: 'admin-orders' })
    return
  }
  loading.value = true
  try {
    const { data } = await adminApi.getOrder(id)
    order.value = data.data
  } catch {
    showToast(t('admin.order_detail.toast_load_error'))
    router.replace({ name: 'admin-orders' })
  } finally {
    loading.value = false
  }
}

async function transitionTo(status: string) {
  if (!order.value) return
  transitioning.value = true
  try {
    const { data } = await adminApi.transitionOrder(Number(route.params.id), status)
    order.value = data.data
  } catch {
    showToast(t('admin.order_detail.toast_transition_error'))
  } finally {
    transitioning.value = false
  }
}

const printingReceipt = ref(false)

async function printReceipt() {
  if (printingReceipt.value) return
  const printWindow = openPrintWindow()
  if (!printWindow) {
    showToast('Your browser blocked the print preview. Please allow popups and try again.')
    return
  }

  printingReceipt.value = true
  try {
    const response = await adminApi.getOrderReceipt(Number(route.params.id))
    printBlob(response.data, printWindow)
  } catch {
    printWindow.close()
    showToast(t('admin.order_detail.toast_receipt_error'))
  } finally {
    printingReceipt.value = false
  }
}

onMounted(loadOrder)
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="loading"
      class="card p-10 text-center text-sm text-gray-500"
    >
      {{ $t('common.loading') }}
    </div>

    <template v-else-if="order">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <button class="btn-icon" :title="$t('admin.order_detail.back')" @click="router.push({ name: 'admin-orders' })">
            <ArrowLeft class="h-5 w-5" />
          </button>
          <div>
            <h1 class="text-2xl font-bold text-ink">{{ $t('admin.order_detail.title', { number: order.order_number }) }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">
              {{ $t('admin.order_detail.placed', { date: formatDateTime(order.placed_at ?? '') }) }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="btn-secondary btn-sm" :disabled="printingReceipt" @click="printReceipt">
            <Printer v-if="!printingReceipt" class="h-4 w-4" />
            <FileText v-else class="h-4 w-4 animate-pulse" />
            {{ $t('admin.order_detail.receipt') }}
          </button>
          <StatusTag :status="order.status" />
          <BaseBadge :variant="order.payment_status === 'paid' ? 'success' : order.payment_status === 'refunded' ? 'warning' : 'neutral'">
            {{ $t(`admin.order_detail.payment_${order.payment_status}`) }}
          </BaseBadge>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ $t('admin.order_detail.customer_information') }}</h2>
          <div class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.customer') }}</span>
              <span class="text-right font-medium text-ink">{{ order.customer_name ?? '—' }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.customers.email') }}</span>
              <span class="text-right text-ink">{{ order.email ?? '—' }}</span>
            </div>
            <div v-if="order.phone" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.phone') }}</span>
              <span class="text-right text-ink">{{ order.phone }}</span>
            </div>
            <div v-if="order.coupon_code" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.coupon') }}</span>
              <span class="text-right font-mono text-xs text-ink">{{ order.coupon_code }}</span>
            </div>
          </div>
          <div v-if="order.note" class="mt-4 rounded-lg bg-canvas p-3 text-sm text-gray-600">
            <span class="font-medium text-ink">{{ $t('admin.order_detail.note') }}:</span> {{ order.note }}
          </div>
        </div>

        <div class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ $t('admin.order_detail.shipping_address') }}</h2>
          <div class="mt-3 space-y-0.5 text-sm text-gray-600">
            <p v-for="(line, i) in renderAddress(order.shipping_address)" :key="i">{{ line }}</p>
          </div>
        </div>

        <div class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ $t('admin.order_detail.billing_address') }}</h2>
          <div class="mt-3 space-y-0.5 text-sm text-gray-600">
            <p v-for="(line, i) in renderAddress(order.billing_address)" :key="i">{{ line }}</p>
          </div>
        </div>
      </div>

      <div class="card overflow-hidden">
        <div class="border-b border-border-gray p-4">
          <h2 class="text-base font-semibold text-ink">{{ $t('admin.order_detail.items') }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border-gray bg-canvas/60 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <th class="px-4 py-3">{{ $t('admin.order_detail.item') }}</th>
                <th class="px-4 py-3">{{ $t('product.sku') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('admin.order_detail.unit_price') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('order.qty') }}</th>
                <th class="px-4 py-3 text-right">{{ $t('order.total') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border-gray">
              <tr v-for="item in order.items" :key="item.id">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <img v-if="item.image_path" :src="item.image_path" :alt="item.product_name" class="h-12 w-10 rounded-lg object-cover" />
                    <div class="min-w-0">
                      <p class="font-medium text-ink">{{ item.product_name }}</p>
                      <p v-if="item.variant_label" class="text-xs text-gray-500">{{ item.variant_label }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ item.sku ?? '—' }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ formatPrice(item.unit_price) }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ item.quantity }}</td>
                <td class="px-4 py-3 text-right font-semibold text-ink">{{ formatPrice(item.line_total) }}</td>
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

      <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ $t('admin.order_detail.payment') }}</h2>
          <div v-if="order.payment" class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.payment_method') }}</span>
              <span class="font-medium text-ink">{{ capitalize(order.payment.method) }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.payment_status') }}</span>
              <span class="text-ink">{{ t(`admin.order_detail.payment_${order.payment.status}`) }}</span>
            </div>
            <div v-if="order.payment.transaction_id" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.transaction_id') }}</span>
              <span class="font-mono text-xs text-ink">{{ order.payment.transaction_id }}</span>
            </div>
            <div v-if="order.payment.amount" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.paid_amount') }}</span>
              <span class="font-medium text-ink">{{ formatPrice(order.payment.amount) }}</span>
            </div>
            <div v-if="order.payment.paid_at" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.paid_at') }}</span>
              <span class="text-ink">{{ formatDateTime(order.payment.paid_at) }}</span>
            </div>
          </div>
          <p v-else class="mt-3 text-sm text-gray-500">{{ $t('admin.order_detail.no_payment') }}</p>
        </div>

        <div class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ $t('admin.order_detail.shipment') }}</h2>
          <div v-if="order.shipment" class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.shipment_status') }}</span>
              <span class="text-ink">{{ capitalize(order.shipment.status) }}</span>
            </div>
            <div v-if="order.shipment.carrier" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.carrier') }}</span>
              <span class="text-ink">{{ order.shipment.carrier }}</span>
            </div>
            <div v-if="order.shipment.tracking_number" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.tracking_number') }}</span>
              <span class="font-mono text-xs text-ink">{{ order.shipment.tracking_number }}</span>
            </div>
            <div v-if="order.shipment.shipped_at" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.shipped_at') }}</span>
              <span class="text-ink">{{ formatDateTime(order.shipment.shipped_at) }}</span>
            </div>
            <div v-if="order.shipment.delivered_at" class="flex justify-between gap-3">
              <span class="text-gray-500">{{ $t('admin.order_detail.delivered_at') }}</span>
              <span class="text-ink">{{ formatDateTime(order.shipment.delivered_at) }}</span>
            </div>
          </div>
          <div v-else class="mt-3 flex items-center gap-3 text-sm text-gray-500">
            <PackageCheck class="h-5 w-5 text-gray-400" />
            {{ $t('admin.order_detail.no_shipment') }}
          </div>
        </div>
      </div>

      <div v-if="allowedTransitions.length" class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-sm font-semibold text-ink">{{ $t('admin.order_detail.transition') }}</h2>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="s in allowedTransitions"
            :key="s"
            type="button"
            class="btn-primary btn-sm"
            :disabled="transitioning"
            @click="transitionTo(s)"
          >
            <ChevronRight class="h-4 w-4" />
            {{ $t('admin.order_detail.transition_to', { status: t(`status.${s}`) }) }}
          </button>
        </div>
      </div>
    </template>

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
