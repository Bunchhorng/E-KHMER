<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { CheckCircle2, Clock3, Plus, Store } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi, type SellerShop } from '@/api/seller'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import BaseBadge from '@/components/BaseBadge.vue'
const { t } = useI18n()
const shop = ref<SellerShop | null>(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const fields = ref<Record<string, string>>({})
const addingAnother = ref(false)
const loaded = ref(false)
const emptyForm = () => ({ name: '', description: '', email: '', phone: '', address_line: '', city: '', province: '', postal_code: '', country: 'KH' })
const form = ref(emptyForm())
async function load() {
  loading.value = true; error.value = ''; loaded.value = false
  try { const { data } = await sellerApi.getApplication(); shop.value = data?.data ?? null; loaded.value = true }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { loading.value = false }
}
async function submit() {
  if (!form.value.name.trim() || submitting.value) return
  submitting.value = true; error.value = ''; fields.value = {}
  try { const { data } = await sellerApi.apply({ ...form.value, name: form.value.name.trim() }); shop.value = data.data; addingAnother.value = false; form.value = emptyForm() }
  catch (cause) { fields.value = extractFieldErrors(cause); error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { submitting.value = false }
}
onMounted(load)
</script>
<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <SellerPageHeader :title="t('seller.application_title')" :description="t('seller.application_help')"><button v-if="shop && !addingAnother" class="btn-secondary" @click="addingAnother = true; error = ''; fields = {}"><Plus :size="17" />{{ t('seller.add_shop') }}</button></SellerPageHeader>
    <ol class="grid gap-3 text-sm sm:grid-cols-3"><li v-for="(key, index) in ['application_step1', 'application_step2', 'application_step3']" :key="key" class="rounded-xl border px-4 py-3" :class="index === (shop && !addingAnother ? (shop.status === 'active' ? 2 : 1) : 0) ? 'border-primary/30 bg-primary/5 font-semibold text-primary' : 'border-border-gray text-muted'">{{ t('seller.' + key) }}</li></ol>
    <p v-if="error" role="alert" class="rounded-xl bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400">{{ error }} <button v-if="!loaded" class="ml-2 underline" @click="load">{{ t('actions.retry') }}</button></p>
    <div v-if="loading" class="card p-10 text-center text-sm text-muted">{{ t('common.loading') }}</div>
    <template v-else-if="loaded">
      <section v-if="shop && !addingAnother" class="card rounded-2xl p-6 sm:p-8"><component :is="shop.status === 'active' ? CheckCircle2 : Clock3" :size="36" class="mb-5 text-primary" /><BaseBadge :variant="shop.status === 'active' ? 'success' : shop.status === 'rejected' ? 'danger' : 'warning'">{{ t((['suspended', 'closed'].includes(shop.status) ? 'seller.' : 'status.') + shop.status) }}</BaseBadge><h2 class="mt-4 text-xl font-semibold">{{ shop.name }}</h2><p class="mt-3 text-sm leading-relaxed text-muted">{{ t(shop.status === 'active' ? 'seller.active_help' : shop.status === 'pending' ? 'seller.pending_help' : shop.status === 'rejected' ? 'seller.rejected_help' : 'seller.unavailable_shop') }}</p><p v-if="shop.rejection_reason" class="mt-4 rounded-xl bg-canvas p-4 text-sm text-muted">{{ shop.rejection_reason }}</p><RouterLink v-if="shop.status === 'active'" :to="{ name: 'seller-shop-dashboard', params: { id: shop.id } }" class="btn-primary mt-6">{{ t('seller.open_workspace') }}</RouterLink></section>
      <form v-else class="card rounded-2xl p-5 sm:p-8" @submit.prevent="submit"><h2 class="flex items-center gap-2 font-semibold"><Store :size="20" class="text-primary" />{{ t('seller.application_step1') }}</h2><div class="mt-6 grid gap-5 sm:grid-cols-2">
        <label class="sm:col-span-2"><span class="label">{{ t('seller.shop_name') }} *</span><input v-model="form.name" class="input w-full" required maxlength="190" :aria-invalid="!!fields.name" /><span v-if="fields.name" class="mt-1 block text-xs text-red-600">{{ fields.name }}</span></label>
        <label class="sm:col-span-2"><span class="label">{{ t('seller.description') }}</span><textarea v-model="form.description" class="textarea w-full" rows="4" maxlength="5000"></textarea></label>
        <label><span class="label">{{ t('seller.public_email') }}</span><input v-model="form.email" class="input w-full" type="email" maxlength="190" autocomplete="email" :aria-invalid="!!fields.email" /><span v-if="fields.email" class="mt-1 block text-xs text-red-600">{{ fields.email }}</span></label>
        <label><span class="label">{{ t('seller.phone') }}</span><input v-model="form.phone" class="input w-full" type="tel" maxlength="50" autocomplete="tel" /></label>
        <label class="sm:col-span-2"><span class="label">{{ t('seller.address') }}</span><input v-model="form.address_line" class="input w-full" maxlength="255" autocomplete="street-address" /></label>
        <label><span class="label">{{ t('seller.city') }}</span><input v-model="form.city" class="input w-full" maxlength="190" autocomplete="address-level2" /></label>
        <label><span class="label">{{ t('seller.province') }}</span><input v-model="form.province" class="input w-full" maxlength="190" autocomplete="address-level1" /></label>
      </div><div class="mt-7 flex flex-wrap gap-3 border-t border-border-gray pt-6"><button class="btn-primary" :disabled="submitting">{{ t(submitting ? 'common.saving' : 'seller.submit_application') }}</button><button v-if="shop" type="button" class="btn-secondary" :disabled="submitting" @click="addingAnother = false; error = ''">{{ t('actions.cancel') }}</button></div></form>
    </template>
  </div>
</template>
