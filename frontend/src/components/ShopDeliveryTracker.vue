<script setup lang="ts">
import { Check, Store, Truck } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import StatusTag from '@/components/StatusTag.vue'
import AssetImage from '@/components/AssetImage.vue'
import type { ApiOrder, ApiShopOrder } from '@/api/checkout'
import { formatDateTime, formatPrice } from '@/utils/format'

defineProps<{ allocations: ApiShopOrder[]; platformShipment?: ApiOrder['shipment'] }>()
const { t } = useI18n()
const stages = ['pending', 'confirmed', 'processing', 'shipped', 'delivered']
const isClosed = (status: string) => ['cancelled', 'refunded'].includes(status)
function statusLabel(status: string) {
  return t(['in_transit', 'returned'].includes(status) ? `admin.shipments.filter.${status}` : `status.${status}`)
}
</script>

<template>
  <section class="space-y-4">
    <div><h2 class="text-lg font-semibold text-ink">{{ t('marketplace.shop_deliveries') }}</h2><p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ t('marketplace.delivery_description') }}</p></div>
    <article v-for="allocation in allocations" :key="allocation.id" class="card overflow-hidden">
      <header class="flex flex-wrap items-center gap-3 border-b border-border-gray px-5 py-4">
        <img v-if="allocation.shop?.logo" :src="allocation.shop.logo" :alt="allocation.shop.name" class="h-11 w-11 shrink-0 rounded-xl border border-border-gray object-contain" />
        <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><Store class="h-5 w-5" aria-hidden="true" /></span>
        <div class="min-w-0 flex-1"><h3 class="truncate font-semibold text-ink">{{ allocation.shop?.name ?? t('marketplace.shop') }}</h3><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ allocation.shop_order_number }}</p></div>
        <div class="text-right"><StatusTag :status="allocation.status" /><p class="mt-1 text-sm font-semibold tabular-nums text-ink">{{ formatPrice(allocation.total, allocation.currency) }}</p></div>
      </header>
      <div class="space-y-5 p-5">
        <p v-if="isClosed(allocation.status)" class="rounded-lg bg-canvas p-3 text-sm text-gray-500 dark:text-muted">{{ statusLabel(allocation.status) }}</p>
        <ol v-else class="grid grid-cols-5 gap-1.5 sm:gap-3" :aria-label="t('marketplace.delivery_progress')">
          <li v-for="(stage, index) in stages" :key="stage" class="min-w-0 text-center" :aria-current="allocation.status === stage ? 'step' : undefined">
            <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold" :class="index <= stages.indexOf(allocation.status) ? 'bg-primary text-white' : 'bg-gray-100 text-gray-400 dark:bg-surface-hover dark:text-muted'"><Check v-if="index < stages.indexOf(allocation.status)" class="h-4 w-4" aria-hidden="true" /><span v-else>{{ index + 1 }}</span></div>
            <p class="mt-2 break-words text-[10px] leading-relaxed sm:text-xs" :class="index <= stages.indexOf(allocation.status) ? 'font-medium text-ink' : 'text-gray-400 dark:text-muted'">{{ statusLabel(stage) }}</p>
          </li>
        </ol>
        <div v-if="allocation.shipment && !isClosed(allocation.status)" class="rounded-xl border border-border-gray bg-canvas/50 p-4">
          <div class="mb-2 flex items-center justify-between gap-3"><span class="inline-flex items-center gap-2 text-sm font-medium text-ink"><Truck class="h-4 w-4 text-primary" aria-hidden="true" />{{ t('marketplace.shipment') }}</span><StatusTag :status="allocation.shipment.status" /></div>
          <dl class="grid gap-2 text-xs sm:grid-cols-2"><div><dt class="text-gray-500 dark:text-muted">{{ t('marketplace.carrier') }}</dt><dd class="mt-1 font-medium text-ink">{{ allocation.shipment.carrier ?? t('marketplace.not_available') }}</dd></div><div><dt class="text-gray-500 dark:text-muted">{{ t('marketplace.tracking_number') }}</dt><dd class="mt-1 break-all font-mono font-medium text-ink">{{ allocation.shipment.tracking_number ?? t('marketplace.not_available') }}</dd></div><div v-if="allocation.shipment.shipped_at"><dt class="text-gray-500 dark:text-muted">{{ t('marketplace.dispatched_at') }}</dt><dd class="mt-1 text-ink">{{ formatDateTime(allocation.shipment.shipped_at) }}</dd></div><div v-if="allocation.shipment.delivered_at"><dt class="text-gray-500 dark:text-muted">{{ t('marketplace.delivered_at') }}</dt><dd class="mt-1 text-ink">{{ formatDateTime(allocation.shipment.delivered_at) }}</dd></div></dl>
          <p v-if="allocation.shipment.status === 'returned'" class="mt-3 text-xs text-amber-700 dark:text-amber-300">{{ t('marketplace.returned_notice') }}</p>
        </div>
        <div class="divide-y divide-border-gray"><div v-for="item in allocation.items" :key="item.id" class="flex items-center gap-3 py-3"><AssetImage :src="item.image_path" :alt="item.product_name" class="h-12 w-12 rounded-lg border border-border-gray" /><div class="min-w-0 flex-1"><p class="break-words text-sm font-medium text-ink">{{ item.product_name }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ item.variant_label }} · {{ item.quantity }} × {{ formatPrice(item.unit_price, allocation.currency) }}</p></div><span class="shrink-0 text-sm font-semibold tabular-nums text-ink">{{ formatPrice(item.line_total, allocation.currency) }}</span></div></div>
        <details v-if="allocation.tracking_events.length" class="border-t border-border-gray pt-3"><summary class="cursor-pointer text-sm font-medium text-primary">{{ t('marketplace.delivery_history') }}</summary><ol class="mt-4 space-y-3 border-l border-border-gray pl-4"><li v-for="event in allocation.tracking_events" :key="event.id"><p class="text-sm font-medium text-ink">{{ statusLabel(event.status) }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ event.description }}</p><time class="mt-1 block text-xs text-gray-400 dark:text-muted" :datetime="event.at">{{ formatDateTime(event.at) }}</time></li></ol></details>
      </div>
    </article>
    <article v-if="platformShipment && !platformShipment.shop_order_id" class="card p-5"><h3 class="font-semibold text-ink">{{ t('marketplace.platform_shipment') }}</h3><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('marketplace.platform_shipment_description') }}</p><div class="mt-3 flex flex-wrap items-center justify-between gap-3"><span class="break-all text-sm text-ink">{{ platformShipment.carrier ?? t('marketplace.not_available') }} · {{ platformShipment.tracking_number ?? t('marketplace.not_available') }}</span><StatusTag :status="platformShipment.status" /></div></article>
  </section>
</template>
