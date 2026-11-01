<script setup lang="ts">
import type { ApiOrder } from '@/api/checkout'
defineProps<{ order: ApiOrder }>()
const label = (status: string) => ({ unpaid: 'pay_on_delivery', partial: 'partial', paid: 'paid', partially_refunded: 'partially_refunded', refunded: 'refunded', failed: 'cancelled', cancelled: 'cancelled' })[status] ?? 'pay_on_delivery'
</script>
<template>
  <div v-if="order.payment?.method === 'cod'" class="card space-y-2 p-5" data-customer-cod>
    <p class="font-semibold">{{ $t('cod.method') }} · {{ $t('cod.customer_' + label(order.status === 'cancelled' ? 'cancelled' : order.payment_status)) }}</p>
    <p v-for="allocation in order.shop_orders" :key="allocation.id" class="text-sm text-muted">{{ allocation.shop?.name }} · {{ $t('cod.customer_' + label(allocation.payment_status)) }}</p>
  </div>
</template>
