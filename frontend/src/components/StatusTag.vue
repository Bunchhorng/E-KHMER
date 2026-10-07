<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseBadge from './BaseBadge.vue'

type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

const props = defineProps<{ status: string; label?: string }>()
const { t, te } = useI18n()
const label = computed(() => {
  if (props.label) return props.label
  const key = `status.${props.status.trim().toLowerCase().replace(/\s+/g, '_')}`
  return te(key) ? t(key) : props.status
})

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
  <BaseBadge :variant="variant" dot>{{ label }}</BaseBadge>
</template>
