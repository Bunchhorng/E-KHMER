<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import type { PaginatedResponse } from '@/api/catalog'
import { useSellerList } from '@/composables/useSellerList'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice } from '@/utils/format'
import BasePagination from '@/components/BasePagination.vue'
interface SellerPayment { id: number; order_number: string; amount: number; refunded_amount: number; refund_memo: string; currency: string; status: string; transaction_hash: string | null; paid_at: string | null }
const { t } = useI18n(); const shopId = Number(useRoute().params.id)
const references = ref<Record<number, string>>({}); const busy = ref(false); const feedback = ref('')
const { items, loading, error, page, pageCount, pageSize, total, load } = useSellerList<SellerPayment>(page => apiClient.get<PaginatedResponse<SellerPayment>>(`/seller/shops/${shopId}/payments`, { params: { page } }), () => t('seller.load_failed'))
async function refund(id: number) {
  if (busy.value) return
  busy.value = true; feedback.value = ''
  try { await apiClient.post(`/seller/shops/${shopId}/payments/${id}/refund`, { reference: references.value[id] }); await load() }
  catch (cause) { feedback.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busy.value = false }
}
onMounted(() => load())
</script>
<template>
  <div class="space-y-5">
    <h1 class="text-2xl font-bold">{{ t('bakong.seller_payments') }}</h1>
    <p class="text-sm text-muted">{{ t('bakong.direct_help') }}</p>
    <p v-if="error || feedback" role="alert" class="text-sm text-red-600">{{ error || feedback }}</p>
    <p v-if="loading" role="status">{{ t('common.loading') }}</p>
    <p v-else-if="!items.length" class="card p-6 text-sm text-muted">{{ t('bakong.no_payments') }}</p>
    <section v-for="payment in items" :key="payment.id" class="card space-y-3 p-5">
      <div class="flex flex-wrap justify-between gap-3">
        <h2 class="font-semibold">{{ payment.order_number }}</h2>
        <p>{{ formatPrice(payment.amount, payment.currency) }} · {{ t('bakong.status_' + payment.status) }}</p>
      </div>
      <p v-if="payment.transaction_hash" class="break-all font-mono text-xs text-muted">{{ payment.transaction_hash }}</p>
      <p class="text-sm text-muted">{{ t('returns.refunded') }}: {{ formatPrice(payment.refunded_amount, payment.currency) }}</p>
      <form v-if="payment.status === 'refund_required'" class="space-y-3" @submit.prevent="refund(payment.id)">
        <p class="text-xs text-muted">{{ t('returns.refund_help') }}</p>
        <p class="break-all text-sm">{{ t('returns.refund_memo', { memo: payment.refund_memo }) }}</p>
        <input v-model="references[payment.id]" class="input w-full" required pattern="[a-fA-F0-9]{64}" :aria-label="t('returns.reference')" :placeholder="t('returns.reference')" />
        <button class="btn-primary" :disabled="busy">{{ t('bakong.verify_refund') }}</button>
      </form>
    </section>
    <BasePagination :page="page" :page-count="pageCount" :total-items="total" :page-size="pageSize" @update:page="load" />
  </div>
</template>

