<script setup lang="ts">
import type { ApiOrder } from '@/api/checkout'
defineProps<{ order: ApiOrder }>()
const label = (status: string) => ({ unpaid: 'pay_on_delivery', partial: 'partial', paid: 'paid', partially_refunded: 'partially_refunded', refunded: 'refunded', failed: 'cancelled', cancelled: 'cancelled' })[status] ?? 'pay_on_delivery'
</script>
<template>
  <div v-if="order.payment?.method === 'cod'" class="card space-y-2 rounded-none p-4 sm:p-5" data-customer-cod>
    <p class="text-sm font-semibold text-ink">{{ $t('cod.method') }} · {{ $t('cod.customer_' + label(order.status === 'cancelled' ? 'cancelled' : order.payment_status)) }}</p>
    <p v-for="allocation in order.shop_orders" :key="allocation.id" class="text-xs text-muted sm:text-sm">{{ allocation.shop?.name }} · {{ $t('cod.customer_' + label(allocation.payment_status)) }}</p>
  </div>
</template>
