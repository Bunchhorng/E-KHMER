<script setup lang="ts">
import { computed } from 'vue'
import BaseBadge from './BaseBadge.vue'

type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

const props = defineProps<{ status: string }>()

const statusVariantMap: Record<string, BadgeVariant> = {
  pending: 'warning',
  confirmed: 'info',
  processing: 'info',
  shipped: 'info',
  delivered: 'success',
  cancelled: 'danger',
  completed: 'success',
  paid: 'success',
  unpaid: 'warning',
  failed: 'danger',
  refunded: 'info',
  active: 'success',
  inactive: 'neutral',
  'in stock': 'success',
  'out of stock': 'danger',
  'low stock': 'warning',
  approved: 'success',
  rejected: 'danger',
  expired: 'danger',
  draft: 'neutral'
}

const variant = computed<BadgeVariant>(() => {
  const key = props.status.trim().toLowerCase()
  return statusVariantMap[key] ?? 'neutral'
})
</script>

<template>
  <BaseBadge :variant="variant" dot>{{ status }}</BaseBadge>
</template>
