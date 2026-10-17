<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import type { ApiOrder } from '@/api/checkout'
import type { PaginatedResponse } from '@/api/catalog'
import { extractErrorMessage } from '@/api/errors'
import BaseModal from '@/components/BaseModal.vue'
import BasePagination from '@/components/BasePagination.vue'
import { formatPrice } from '@/utils/format'
interface ReturnRecord { id: number; order_number: string; product_name: string; variant_label: string; quantity: number; status: string; reason: string; resolution_note: string | null; refund_amount: number; refund_memo: string; currency: string; photo_count: number; shop_name: string | null }
const props = withDefaults(defineProps<{ manage?: boolean }>(), { manage: false })
const { t } = useI18n()
const route = useRoute()
const prefix = computed(() => route.path.startsWith('/seller/') ? `/seller/shops/${route.params.id}/returns` : props.manage ? '/admin/returns' : '/returns')
const items = ref<ReturnRecord[]>([]); const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const loading = ref(false); const error = ref(''); const busy = ref(false); const evidence = ref('')
const form = reactive({ orderNumber: String(route.query.order ?? ''), itemId: 0, quantity: 1, reason: '' })
const order = ref<ApiOrder | null>(null); const files = ref<File[]>([])
const selected = ref<ReturnRecord | null>(null); const action = ref('approved'); const note = ref(''); const reference = ref('')
const restock = ref(false)
async function load(page = 1) {
  loading.value = true; error.value = ''
  try { const result = (await apiClient.get<PaginatedResponse<ReturnRecord>>(prefix.value, { params: { page } })).data; items.value = result.data; meta.value = result.meta }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { loading.value = false }
}
async function findOrder() {
  error.value = ''; order.value = null; form.itemId = 0
  try { order.value = (await apiClient.get<{ data: ApiOrder }>(`/orders/${encodeURIComponent(form.orderNumber.trim())}`)).data.data; form.itemId = order.value.items[0]?.id ?? 0 }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
}
async function submit() {
  if (busy.value) return
  busy.value = true; error.value = ''
  try {
    const data = new FormData(); data.append('order_item_id', String(form.itemId)); data.append('quantity', String(form.quantity)); data.append('reason', form.reason.trim())
    files.value.forEach((file, index) => data.append(`photos[${index}]`, file))
    await apiClient.post('/returns', data)
    form.reason = ''; form.quantity = 1; files.value = []; await load()
  } catch (cause) { error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busy.value = false }
}
async function transition() {
  if (!selected.value || busy.value) return
  busy.value = true; error.value = ''
  try { await apiClient.put(`${prefix.value}/${selected.value.id}`, { status: action.value, note: note.value || null, reference: reference.value || null, restock: restock.value }); selected.value = null; await load(meta.value.current_page) }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busy.value = false }
}
async function photo(item: ReturnRecord, index: number) {
  try {
    const response = await apiClient.get<Blob>(`${prefix.value}/${item.id}/photos/${index}`, { responseType: 'blob' })
    if (evidence.value) URL.revokeObjectURL(evidence.value)
    evidence.value = URL.createObjectURL(response.data)
  } catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
}
function open(item: ReturnRecord) { selected.value = item; action.value = item.status === 'requested' ? 'approved' : item.status === 'approved' ? 'received' : 'refunded'; note.value = ''; reference.value = ''; restock.value = false }
onMounted(() => { void load(); if (form.orderNumber && !props.manage) void findOrder() })
function closeEvidence() { if (evidence.value) URL.revokeObjectURL(evidence.value); evidence.value = '' }
onBeforeUnmount(closeEvidence)
</script>
<template>
  <main class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
    <h1 class="text-2xl font-bold">{{ t('returns.title') }}</h1><p class="text-sm text-muted">{{ t('returns.help') }}</p>
    <p v-if="error" role="alert" class="card p-4 text-sm text-red-600">{{ error }}</p>
    <section v-if="!manage" class="card space-y-4 p-5">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="findOrder"><label class="flex-1"><span class="label">{{ t('returns.order_number') }}</span><input v-model="form.orderNumber" class="input w-full" required maxlength="100" /></label><button class="btn-secondary">{{ t('returns.find_order') }}</button></form>
      <form v-if="order" class="space-y-4" @submit.prevent="submit">
        <label class="block"><span class="label">{{ t('returns.item') }}</span><select v-model.number="form.itemId" class="select w-full" required><option v-for="item in order.items" :key="item.id" :value="item.id">{{ item.product_name }} · {{ item.variant_label }} ({{ item.quantity }})</option></select></label>
        <label class="block"><span class="label">{{ t('returns.quantity') }}</span><input v-model.number="form.quantity" class="input" type="number" required min="1" :max="order.items.find(item => item.id === form.itemId)?.quantity ?? 1" /></label>
        <label class="block"><span class="label">{{ t('returns.reason') }}</span><textarea v-model="form.reason" class="textarea w-full" required minlength="10" maxlength="2000" rows="3"></textarea></label>
        <label class="block"><span class="label">{{ t('returns.photos') }}</span><input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="files = Array.from(($event.target as HTMLInputElement).files ?? [])" /></label>
        <button class="btn-primary" :disabled="busy">{{ t('returns.submit') }}</button>
      </form>
    </section>
    <p v-if="loading" role="status" class="text-sm text-muted">{{ t('common.loading') }}</p>
    <p v-else-if="!items.length" class="card p-8 text-sm text-muted">{{ t('returns.empty') }}</p>
    <section v-for="item in items" :key="item.id" class="card space-y-3 p-5">
      <div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-semibold">{{ item.product_name }} × {{ item.quantity }}</h2><p class="mt-1 text-xs text-muted">{{ item.order_number }} · {{ item.shop_name }}</p></div><span class="text-sm font-semibold text-primary">{{ t('returns.' + item.status) }}</span></div>
      <p class="whitespace-pre-line text-sm">{{ item.reason }}</p><p v-if="item.resolution_note" class="text-sm text-muted">{{ item.resolution_note }}</p>
      <p class="text-sm">{{ t('returns.refund') }}: {{ formatPrice(item.refund_amount, item.currency) }}</p>
      <div class="flex flex-wrap gap-3"><button v-for="index in item.photo_count" :key="index" class="text-xs text-primary" @click="photo(item, index - 1)">{{ t('returns.photo', { number: index }) }}</button><button v-if="manage && ['requested', 'approved', 'received'].includes(item.status)" class="btn-secondary" @click="open(item)">{{ t('returns.review') }}</button></div>
    </section>
    <BasePagination :page="meta.current_page" :page-count="meta.last_page" :total-items="meta.total" :page-size="meta.per_page" @update:page="load" />
    <BaseModal :model-value="!!selected" :title="t('returns.review')" @update:model-value="!$event && (selected = null)">
      <p v-if="error" role="alert" class="mb-4 text-sm text-red-600">{{ error }}</p>
      <p v-if="action === 'refunded'" class="mb-4 break-all text-sm">{{ t('returns.refund_memo', { memo: selected?.refund_memo }) }}</p>
      <form class="space-y-4" @submit.prevent="transition"><label class="block"><span class="label">{{ t('returns.action') }}</span><select v-model="action" class="select w-full"><template v-if="selected?.status === 'requested'"><option value="approved">{{ t('returns.approved') }}</option><option value="rejected">{{ t('returns.rejected') }}</option></template><option v-else :value="selected?.status === 'approved' ? 'received' : 'refunded'">{{ t('returns.' + action) }}</option></select></label><label class="block"><span class="label">{{ t('returns.note') }}</span><textarea v-model="note" class="textarea w-full" maxlength="2000" :required="action === 'rejected'"></textarea></label><label v-if="action === 'received'" class="flex items-center gap-2 text-sm"><input v-model="restock" type="checkbox" />{{ t('returns.restock') }}</label><label v-if="action === 'refunded'" class="block"><span class="label">{{ t('returns.reference') }}</span><input v-model="reference" class="input w-full" required maxlength="190" /><span class="mt-2 block text-xs text-muted">{{ t('returns.refund_help') }}</span></label><button class="btn-primary" :disabled="busy">{{ t('actions.save') }}</button></form>
    </BaseModal>
    <BaseModal :model-value="!!evidence" :title="t('returns.photos')" @update:model-value="!$event && closeEvidence()"><img :src="evidence" :alt="t('returns.photos')" class="max-h-[65vh] w-full object-contain" /></BaseModal>
  </main>
</template>

