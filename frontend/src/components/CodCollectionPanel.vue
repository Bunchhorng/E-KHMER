<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import apiClient from '@/api/client'
import type { CodPayment } from '@/api/cod'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice, formatDateTime } from '@/utils/format'
import BaseModal from '@/components/BaseModal.vue'

const props = defineProps<{ payment: CodPayment; endpoint: string }>()
const emit = defineEmits<{ collected: [] }>()
const { t } = useI18n()
const confirming = ref(false)
const busy = ref(false)
const error = ref('')
const succeeded = ref(false)
async function collect() {
  if (busy.value || succeeded.value) return
  busy.value = true; error.value = ''
  try {
    // The server owns the amount, actor and collection timestamp.
    await apiClient.post(props.endpoint)
    succeeded.value = true; confirming.value = false
    emit('collected')
  } catch (cause) {
    error.value = axios.isAxiosError(cause) && (cause.response?.status ?? 0) >= 500
      ? t('cod.failed') : extractErrorMessage(cause, t('cod.failed'))
  } finally { busy.value = false }
}
</script>

<template>
  <section class="space-y-3" :data-cod-payment="payment.id">
    <div class="flex flex-wrap justify-between gap-3">
      <div><h3 class="font-semibold">{{ payment.shop_order_number }}</h3><p class="text-xs text-muted">{{ payment.shop.name }} · {{ t('cod.method') }}</p></div>
      <p class="font-semibold">{{ formatPrice(payment.amount, payment.currency) }}</p>
    </div>
    <p class="text-sm" data-cod-status>{{ t('cod.status_' + (succeeded && payment.status === 'unpaid' ? 'paid' : payment.status)) }}</p>
    <p v-if="payment.collected_at" class="text-sm text-muted">{{ t('cod.audit', { name: payment.collected_by ?? '—', at: formatDateTime(payment.collected_at) }) }}</p>
    <p v-if="succeeded" role="status" class="text-sm text-emerald-600">{{ t('cod.success') }}</p>
    <button v-if="payment.can_collect && !succeeded" type="button" class="btn-primary" @click="error = ''; confirming = true">{{ t('cod.record') }}</button>
    <p v-else-if="payment.status === 'unpaid' && !succeeded" class="text-xs text-muted">{{ t('cod.delivery_required') }}</p>
    <BaseModal :model-value="confirming" :title="t('cod.confirm_title')" :close-on-backdrop="!busy" @update:model-value="!busy && (confirming = $event)">
      <p class="font-semibold">{{ payment.shop_order_number }} · {{ formatPrice(payment.amount, payment.currency) }}</p>
      <p class="mt-3 text-sm text-muted">{{ t('cod.confirm_help') }}</p>
      <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
      <template #footer><div class="flex flex-wrap justify-end gap-3"><button type="button" class="btn-secondary" :disabled="busy" @click="confirming = false">{{ t('actions.cancel') }}</button><button type="button" class="btn-primary" :disabled="busy" @click="collect">{{ t(busy ? 'cod.recording' : 'cod.confirm') }}</button></div></template>
    </BaseModal>
  </section>
</template>
