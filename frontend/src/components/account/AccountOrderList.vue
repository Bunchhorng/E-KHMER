<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowUpRight, Package } from 'lucide-vue-next'
import type { ApiOrderListItem } from '@/api/orders'
import StatusTag from '@/components/StatusTag.vue'
import { formatDate, formatPrice } from '@/utils/format'
defineProps<{ orders: ApiOrderListItem[] }>()
</script>

<template>
  <div class="space-y-3 md:hidden">
    <article v-for="order in orders" :key="order.order_number" class="card space-y-4 p-4">
      <div class="flex flex-wrap items-center justify-between gap-2"><RouterLink :to="`/account/orders/${order.order_number}`" class="break-all text-sm font-semibold text-primary">{{ order.order_number }}</RouterLink><StatusTag :status="order.status" /></div>
      <div class="flex items-center gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-primary"><Package class="h-5 w-5" /></div><div><p class="text-sm font-semibold">{{ formatPrice(order.total) }}</p><p class="mt-0.5 text-xs text-muted">{{ formatDate(order.placed_at) }} · {{ $t('account.ui.item_count', { count: order.items_count }, order.items_count) }}</p></div></div>
      <div class="flex gap-2 border-t border-border-gray pt-3"><RouterLink :to="`/account/orders/${order.order_number}`" class="btn-secondary min-h-10 flex-1">{{ $t('account.ui.view_details') }}</RouterLink><RouterLink :to="`/order/tracking/${order.order_number}`" class="btn-ghost min-h-10 flex-1">{{ $t('order.track') }}<ArrowUpRight class="h-4 w-4" /></RouterLink></div>
    </article>
  </div>
  <div class="card hidden overflow-x-auto md:block">
    <table class="w-full text-left text-sm">
      <caption class="sr-only">{{ $t('nav.my_orders') }}</caption>
      <thead class="border-b border-border-gray bg-canvas/60 text-xs text-muted"><tr><th scope="col" class="px-5 py-4 font-medium">{{ $t('order.order') }}</th><th scope="col" class="px-4 py-4 font-medium">{{ $t('order.placed') }}</th><th scope="col" class="px-4 py-4 font-medium">{{ $t('order.total') }}</th><th scope="col" class="px-4 py-4 font-medium">{{ $t('order.status') }}</th><th scope="col" class="px-5 py-4 text-right font-medium">{{ $t('order.action') }}</th></tr></thead>
      <tbody class="divide-y divide-border-gray"><tr v-for="order in orders" :key="order.order_number" class="transition-colors hover:bg-canvas/50"><td class="px-5 py-4"><RouterLink :to="`/account/orders/${order.order_number}`" class="font-semibold text-primary hover:underline">{{ order.order_number }}</RouterLink><p class="mt-1 text-xs text-muted">{{ $t('account.ui.item_count', { count: order.items_count }, order.items_count) }}</p></td><td class="whitespace-nowrap px-4 py-4 text-muted">{{ formatDate(order.placed_at) }}</td><td class="whitespace-nowrap px-4 py-4 font-semibold">{{ formatPrice(order.total) }}</td><td class="px-4 py-4"><StatusTag :status="order.status" /></td><td class="px-5 py-4 text-right"><RouterLink :to="`/order/tracking/${order.order_number}`" class="btn-secondary btn-sm min-h-9">{{ $t('order.track') }}<ArrowUpRight class="h-3.5 w-3.5" /></RouterLink></td></tr></tbody>
    </table>
  </div>
</template>
