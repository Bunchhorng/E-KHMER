<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Check, RefreshCw, Truck } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import { sellerApi } from '@/api/seller'
import { extractErrorMessage } from '@/api/errors'
import type { ApiShopOrder } from '@/api/checkout'
import ShopDeliveryTracker from '@/components/ShopDeliveryTracker.vue'
import { formatPrice } from '@/utils/format'

const props = withDefaults(defineProps<{ allocation: ApiShopOrder; mode?: 'seller' | 'admin'; shopId?: number }>(), { mode: 'seller' })
const emit = defineEmits<{ updated: [allocation: ApiShopOrder] }>()
const { t } = useI18n()
const busy = ref(false)
const error = ref('')
const success = ref('')
const carrier = ref('')
const trackingNumber = ref('')
const note = ref('')
const closed = computed(() => ['pending', 'cancelled', 'refunded'].includes(props.allocation.parent_status))
const guidance = computed(() => {
  if (props.allocation.shipment?.status === 'returned') return 'seller.returned_guidance'
  if (props.allocation.parent_status === 'pending') return 'marketplace.waiting_confirmation'
  if (closed.value) return 'marketplace.no_order_actions'
  return ({ confirmed: 'seller.prepare_guidance', processing: 'seller.dispatch_guidance', shipped: 'seller.delivery_guidance', delivered: 'seller.done_guidance' } as Record<string, string>)[props.allocation.status] ?? 'marketplace.no_order_actions'
})
function actionLabel(status: string) {
  const key = ({ processing: 'seller.prepare', shipped: 'seller.dispatch', delivered: 'seller.deliver' } as Record<string, string>)[status]
  return props.mode === 'seller' && key ? t(key) : t('marketplace.mark_status', { status: t(`status.${status}`) })
}
const shipmentActions = computed(() => props.allocation.status === 'shipped' && ['shipped', 'in_transit'].includes(props.allocation.shipment?.status ?? '') ? [ ...(props.allocation.shipment?.status === 'shipped' ? ['in_transit'] : []), 'returned'] : [])
const addressLines = computed(() => {
  const a = props.allocation.shipping_address
  if (!a) return []
  return [a.full_name, a.address_line1, a.address_line2, [a.city, a.state, a.postal_code].filter(Boolean).join(', '), a.country].filter(Boolean)
})
watch(() => props.allocation, allocation => { carrier.value = allocation.shipment?.carrier ?? ''; trackingNumber.value = allocation.shipment?.tracking_number ?? '' }, { immediate: true })

async function save(kind: 'order' | 'shipment', status: string) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    const metadata = { status, carrier: carrier.value.trim(), tracking_number: trackingNumber.value.trim() }
    const shopId = props.shopId ?? props.allocation.shop?.id
    if (props.mode === 'seller' && !shopId) throw new Error('Shop context unavailable')
    const response = kind === 'order'
      ? props.mode === 'admin'
        ? await adminApi.transitionShopOrder(props.allocation.order_id, props.allocation.id, { ...metadata, note: note.value.trim() || undefined })
        : await sellerApi.transitionOrder(shopId!, props.allocation.id, { ...metadata, note: note.value.trim() || undefined })
      : props.mode === 'admin'
        ? await adminApi.updateShopShipment(props.allocation.order_id, props.allocation.id, metadata)
        : await sellerApi.updateShipment(shopId!, props.allocation.id, metadata)
    emit('updated', response.data.data)
    note.value = ''
    success.value = t('marketplace.updated')
  } catch (failure) {
    error.value = extractErrorMessage(failure, t('marketplace.update_failed'))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <section v-if="mode === 'seller'" class="rounded-xl border border-primary/10 bg-primary/5 p-5"><h2 class="text-sm font-semibold text-primary">{{ t('seller.next_step') }}</h2><p class="mt-2 text-sm leading-relaxed text-muted">{{ t(guidance) }}</p></section>
    <ShopDeliveryTracker :allocations="[allocation]" :show-heading="mode !== 'seller'" />
    <section class="card space-y-4 p-5">
      <h3 class="font-semibold text-ink">{{ t('marketplace.fulfilment_actions') }}</h3>
      <p v-if="closed || !allocation.allowed_transitions.length" class="text-sm text-gray-500 dark:text-muted">{{ t(allocation.parent_status === 'pending' ? 'marketplace.waiting_confirmation' : 'marketplace.no_order_actions') }}</p>
      <div v-if="!closed" class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium text-ink" :for="`carrier-${allocation.id}`">{{ t('marketplace.carrier') }}<input :id="`carrier-${allocation.id}`" v-model="carrier" class="input mt-1.5" maxlength="255" :disabled="busy" /></label>
        <label class="block text-sm font-medium text-ink" :for="`tracking-${allocation.id}`">{{ t('marketplace.tracking_number') }}<input :id="`tracking-${allocation.id}`" v-model="trackingNumber" class="input mt-1.5" maxlength="255" :disabled="busy" /></label>
        <label v-if="allocation.allowed_transitions.length" class="block text-sm font-medium text-ink sm:col-span-2" :for="`note-${allocation.id}`">{{ t('marketplace.note') }}<textarea :id="`note-${allocation.id}`" v-model="note" class="input mt-1.5 min-h-20" maxlength="255" :disabled="busy" /></label>
      </div>
      <p v-if="allocation.allowed_transitions.includes('shipped')" class="text-xs text-gray-500 dark:text-muted">{{ t('marketplace.tracking_required') }}</p>
      <p v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><p v-if="success" role="status" class="text-sm text-emerald-600 dark:text-emerald-400">{{ success }}</p>
      <div v-if="!closed" class="flex flex-wrap gap-2">
        <button v-for="status in allocation.allowed_transitions" :key="status" type="button" class="btn-primary btn-sm" :disabled="busy || (status === 'shipped' && (!carrier.trim() || !trackingNumber.trim()))" @click="save('order', status)"><RefreshCw v-if="busy" class="h-4 w-4 animate-spin" /><Truck v-else-if="status === 'shipped'" class="h-4 w-4" /><Check v-else class="h-4 w-4" />{{ actionLabel(status) }}</button>
        <button v-if="allocation.shipment" type="button" class="btn-secondary btn-sm" :disabled="busy" @click="save('shipment', allocation.shipment.status)">{{ t('marketplace.save_tracking') }}</button>
        <button v-for="status in shipmentActions" :key="status" type="button" class="btn-secondary btn-sm" :disabled="busy" @click="save('shipment', status)">{{ t('marketplace.mark_status', { status: t(`admin.shipments.filter.${status}`) }) }}</button>
      </div>
    </section>
    <div class="grid gap-5 sm:grid-cols-2">
      <section class="card p-5"><h3 class="font-semibold text-ink">{{ t('marketplace.customer_shipping') }}</h3><p class="mt-3 text-sm font-medium text-ink">{{ allocation.customer_name }}</p><p class="mt-1 break-all text-sm text-gray-500 dark:text-muted">{{ allocation.email }}</p><p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ allocation.phone }}</p><address class="mt-3 text-sm not-italic leading-relaxed text-gray-500 dark:text-muted"><p v-for="(line, index) in addressLines" :key="index">{{ line }}</p></address></section>
      <section class="card p-5"><h3 class="font-semibold text-ink">{{ t('marketplace.shop_totals') }}</h3><dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-muted">{{ t('admin.order_detail.subtotal') }}</dt><dd class="text-ink">{{ formatPrice(allocation.subtotal, allocation.currency) }}</dd></div><div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-muted">{{ t('admin.order_detail.discount') }}</dt><dd class="text-ink">−{{ formatPrice(allocation.discount_amount, allocation.currency) }}</dd></div><div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-muted">{{ t('admin.order_detail.shipping') }}</dt><dd class="text-ink">{{ formatPrice(allocation.shipping_amount, allocation.currency) }}</dd></div><div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-muted">{{ t('admin.order_detail.tax') }}</dt><dd class="text-ink">{{ formatPrice(allocation.tax_amount, allocation.currency) }}</dd></div><div class="flex justify-between gap-3 border-t border-border-gray pt-3 font-semibold"><dt class="text-ink">{{ t('order.total') }}</dt><dd class="text-primary">{{ formatPrice(allocation.total, allocation.currency) }}</dd></div></dl></section>
    </div>
  </div>
</template>
