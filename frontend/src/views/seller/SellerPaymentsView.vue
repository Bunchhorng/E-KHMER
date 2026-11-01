<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import type { PaginatedResponse } from '@/api/catalog'
import { useSellerList } from '@/composables/useSellerList'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice } from '@/utils/format'
import BasePagination from '@/components/BasePagination.vue'
import CodCollectionPanel from '@/components/CodCollectionPanel.vue'
import type { CodPayment } from '@/api/cod'
interface SellerPayment { method?: 'bakong'; id: number; order_number: string; amount: number; refunded_amount: number; refund_memo: string; currency: string; status: string; transaction_hash: string | null; paid_at: string | null }
const { t } = useI18n(); const shopId = Number(useRoute().params.id)
const references = ref<Record<number, string>>({}); const busy = ref(false); const feedback = ref('')
const method = ref<'cod' | 'bakong'>('cod')
const { items, loading, error, page, pageCount, pageSize, total, load } = useSellerList<SellerPayment | CodPayment>(page => apiClient.get<PaginatedResponse<SellerPayment | CodPayment>>(`/seller/shops/${shopId}/payments`, { params: { page, method: method.value } }), () => t('seller.load_failed'))
watch(method, () => load(1))
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
    <div class="flex flex-wrap gap-2"><button type="button" :class="method === 'cod' ? 'btn-primary' : 'btn-secondary'" @click="method = 'cod'">{{ t('cod.method') }}</button><button type="button" :class="method === 'bakong' ? 'btn-primary' : 'btn-secondary'" @click="method = 'bakong'">Bakong</button></div>
    <p v-if="method === 'bakong'" class="text-sm text-muted">{{ t('bakong.direct_help') }}</p>
    <p v-if="error || feedback" role="alert" class="text-sm text-red-600">{{ error || feedback }}</p>
    <p v-if="loading" role="status">{{ t('common.loading') }}</p>
    <p v-else-if="!items.length" class="card p-6 text-sm text-muted">{{ t(method === 'cod' ? 'cod.empty' : 'bakong.no_payments') }}</p>
    <template v-for="payment in items" :key="`${method}-${payment.id}`">
    <CodCollectionPanel v-if="payment.method === 'cod'" class="card p-5" :payment="payment" :endpoint="`/seller/shops/${shopId}/payments/${payment.id}/collect-cod`" @collected="load(page)" />
    <section v-else class="card space-y-3 p-5">
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
    </template>
    <BasePagination :page="page" :page-count="pageCount" :total-items="total" :page-size="pageSize" @update:page="load" />
  </div>
</template>

