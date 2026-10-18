<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import apiClient from '@/api/client'
import { useCartStore } from '@/stores/cart'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice } from '@/utils/format'
import { useI18n } from 'vue-i18n'
import type { ApiOrder } from '@/api/checkout'
interface ShopPayment { id: number; shop_name: string; amount: number; currency: string; status: string; qr_payload: string | null; expires_at: string; image?: string }
const { t } = useI18n()
const route = useRoute(); const router = useRouter(); const cart = useCartStore()
const payments = ref<ShopPayment[]>([]); const loading = ref(true); const checking = ref(false); const error = ref('')
const now = ref(Date.now()); const orderStatus = ref('pending')
const expired = computed(() => payments.value.some(payment => new Date(payment.expires_at).getTime() <= now.value))
let generation = 0
const clock = window.setInterval(() => now.value = Date.now(), 1000)
async function load() {
  const current = ++generation; loading.value = true; error.value = ''
  try {
    const data = (await apiClient.get<{ data: ShopPayment[] }>(`/checkout/${route.params.orderNumber}/bakong`)).data.data
    const rendered = await Promise.all(data.map(async payment => ({ ...payment, image: payment.qr_payload ? await QRCode.toDataURL(payment.qr_payload, { width: 320, margin: 3, errorCorrectionLevel: 'M' }) : undefined })))
    if (current === generation) payments.value = rendered
  } catch (cause) { if (current === generation) error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { if (current === generation) loading.value = false }
}
async function verify() {
  if (checking.value) return
  checking.value = true; error.value = ''
  try {
    const order = (await apiClient.post<{ data: ApiOrder }>(`/checkout/${route.params.orderNumber}/bakong/verify`)).data.data
    orderStatus.value = order.status
    if (order.status === 'confirmed' && order.payment_status === 'paid') {
      await cart.fetch()
      await router.replace({ name: 'order-success', params: { orderId: order.order_number } })
    } else {
      await load()
      if (!expired.value && !error.value) error.value = t('bakong.not_found')
    }
  } catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { checking.value = false }
}
watch(() => route.params.orderNumber, load, { immediate: true })
onBeforeUnmount(() => { generation++; window.clearInterval(clock) })
</script>
<template>
  <div class="space-y-6 py-8">
    <div><h1 class="text-2xl font-bold">{{ t('bakong.title') }}</h1><p class="mt-3 text-sm leading-relaxed text-muted">{{ t('bakong.help') }}</p><p class="mt-2 text-xs text-muted">{{ route.params.orderNumber }}</p></div>
    <p v-if="error" role="alert" class="card p-4 text-sm text-red-600">{{ error }}</p>
    <p v-if="expired" role="status" class="rounded-xl bg-amber-500/10 p-4 text-sm">{{ t('bakong.expired') }}</p>
    <div v-if="loading" class="card h-72 animate-pulse" aria-busy="true"></div>
    <div v-else class="grid gap-6 sm:grid-cols-2">
      <section v-for="payment in payments" :key="payment.id" class="card rounded-2xl p-6 text-center">
        <h2 class="text-lg font-semibold">{{ payment.shop_name }}</h2><p class="mt-2 text-xl font-bold">{{ formatPrice(payment.amount, payment.currency) }}</p>
        <p class="mt-2 text-sm font-medium" :class="payment.status === 'paid' ? 'text-emerald-600' : 'text-muted'">{{ t('bakong.status_' + payment.status) }}</p>
        <img v-if="payment.status === 'pending' && !expired && payment.image" :src="payment.image" :alt="t('bakong.qr_alt', { shop: payment.shop_name })" class="mx-auto mt-4 max-w-full rounded-xl bg-white" width="320" height="320" />
        <p class="mt-3 text-xs text-muted">{{ t('bakong.expires') }} {{ new Date(payment.expires_at).toLocaleTimeString() }}</p>
      </section>
    </div>
    <div class="flex flex-wrap gap-3"><button class="btn-primary" :disabled="checking || loading" @click="verify">{{ t(checking ? 'bakong.checking' : 'bakong.verify') }}</button><RouterLink to="/account/orders" class="btn-secondary">{{ t('nav.my_orders') }}</RouterLink><RouterLink to="/support" class="btn-secondary">{{ t('bakong.contact') }}</RouterLink></div>
  </div>
</template>

