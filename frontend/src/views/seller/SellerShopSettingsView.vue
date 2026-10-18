<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, RouterLink, useRoute } from 'vue-router'
import { Save, Store, Truck } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi } from '@/api/seller'
import type { ApiShippingMethod } from '@/api/shipping'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import AssetImage from '@/components/AssetImage.vue'
const { t } = useI18n()
const shopId = Number(useRoute().params.id)
const workspace = useSellerWorkspace()
const form = reactive({ name: '', description: '', email: '', phone: '', address_line: '', city: '', province: '', postal_code: '', country: 'KH', bakong_account_id: '', bakong_merchant_name: '', bakong_merchant_city: '' })
const images = reactive({ logo: '', banner: '' })
const uploads = reactive<{ logo: File | null; banner: File | null }>({ logo: null, banner: null })
const removals = reactive({ logo: false, banner: false })
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const fields = ref<Record<string, string>>({})
const baseline = ref('')
const loaded = ref(false)
const shippingMethods = ref<ApiShippingMethod[]>([])
const method = reactive({ id: 0, name: '', code: '', description: '', price: 0, estimated_days_min: 1, estimated_days_max: 3, is_active: true })
const shippingBusy = ref(false)
const shippingError = ref('')
const settingsFields = ['email', 'phone', 'address_line', 'city', 'province', 'postal_code', 'country'] as const
const bankFields = ['bakong_account_id', 'bakong_merchant_name', 'bakong_merchant_city'] as const
const publicUrl = computed(() => workspace.currentShop ? `/shops/${workspace.currentShop.slug}` : '/shops')
function snapshot() { return JSON.stringify({ form, images, removals }) }
function canLeave() { return !saving.value && (!loaded.value || snapshot() === baseline.value || window.confirm(t('seller.unsaved'))) }
onBeforeRouteLeave(canLeave)
onBeforeRouteUpdate(canLeave)
function cleanPreview(field: 'logo' | 'banner') { if (uploads[field]) URL.revokeObjectURL(images[field]) }
function chooseImage(field: 'logo' | 'banner', event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { error.value = t('seller.photos_invalid'); return }
  cleanPreview(field)
  uploads[field] = file; images[field] = URL.createObjectURL(file); removals[field] = false
}
function removeImage(field: 'logo' | 'banner') { cleanPreview(field); uploads[field] = null; images[field] = ''; removals[field] = true }
async function load() {
  loading.value = true; error.value = ''
  try {
    const [settings, shipping] = await Promise.all([sellerApi.getSettings(shopId), sellerApi.shippingMethods(shopId)])
    for (const key of Object.keys(form) as (keyof typeof form)[]) form[key] = settings.data.data[key] ?? ''
    images.logo = settings.data.data.logo ?? ''; images.banner = settings.data.data.banner ?? ''
    shippingMethods.value = shipping.data.data; loaded.value = true; baseline.value = snapshot()
  } catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { loading.value = false }
}
async function save() {
  if (saving.value || !loaded.value) return
  saving.value = true; error.value = ''; success.value = ''; fields.value = {}
  try {
    const data = new FormData()
    Object.entries(form).forEach(([key, value]) => data.append(key, value.trim()))
    for (const field of ['logo', 'banner'] as const) {
      if (uploads[field]) data.append(`${field}_file`, uploads[field])
      data.append(`remove_${field}`, removals[field] ? '1' : '0')
    }
    const result = (await sellerApi.updateSettings(shopId, data)).data.data
    for (const field of ['logo', 'banner'] as const) { cleanPreview(field); uploads[field] = null; removals[field] = false; images[field] = result[field] ?? '' }
    for (const key of Object.keys(form) as (keyof typeof form)[]) form[key] = result[key] ?? ''
    workspace.updateShop(result)
    baseline.value = snapshot(); success.value = t('shop_settings.saved')
  } catch (cause) { fields.value = extractFieldErrors(cause); error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { saving.value = false }
}
function editMethod(item?: ApiShippingMethod) {
  Object.assign(method, item ?? { id: 0, name: '', code: '', description: '', price: 0, estimated_days_min: 1, estimated_days_max: 3, is_active: true })
}
async function saveShipping() {
  if (shippingBusy.value) return
  shippingBusy.value = true; shippingError.value = ''
  try {
    const payload = { ...method, code: method.code.trim() || `shop-${shopId}-${Date.now()}` }
    if (method.id) await sellerApi.updateShippingMethod(shopId, method.id, payload)
    else await sellerApi.createShippingMethod(shopId, payload)
    shippingMethods.value = (await sellerApi.shippingMethods(shopId)).data.data
    editMethod()
  } catch (cause) { shippingError.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { shippingBusy.value = false }
}
onMounted(load)
onBeforeUnmount(() => { cleanPreview('logo'); cleanPreview('banner') })
</script>
<template>
  <div class="space-y-6">
    <SellerPageHeader :title="t('shop_settings.title')" :description="t('shop_settings.help')"><RouterLink :to="publicUrl" class="btn-secondary"><Store :size="17" />{{ t('shop_settings.view_shop') }}</RouterLink></SellerPageHeader>
    <p v-if="error" role="alert" class="rounded-xl bg-red-500/10 p-4 text-sm text-red-600">{{ error }} <button v-if="!loaded" class="underline" @click="load">{{ t('actions.retry') }}</button></p>
    <p v-if="success" role="status" class="rounded-xl bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-400">{{ success }}</p>
    <div v-if="loading" class="card h-64 animate-pulse" aria-busy="true"></div>
    <template v-else-if="loaded">
      <form class="card space-y-6 p-5 sm:p-7" @submit.prevent="save">
        <fieldset :disabled="saving" class="space-y-6">
          <label class="block"><span class="label">{{ t('shop_settings.name') }} *</span><input v-model="form.name" class="input w-full" required maxlength="190" /><span v-if="fields.name" class="text-xs text-red-600">{{ fields.name }}</span></label>
          <label class="block"><span class="label">{{ t('seller.description') }}</span><textarea v-model="form.description" class="textarea w-full" rows="4" maxlength="2000"></textarea></label>
          <div class="grid gap-5 sm:grid-cols-2">
            <div v-for="field in (['logo', 'banner'] as const)" :key="field">
              <span class="label">{{ t('shop_settings.' + field) }}</span><AssetImage :src="images[field]" :alt="t('shop_settings.' + field)" class="h-36 w-full rounded-xl border border-border-gray bg-canvas" />
              <input type="file" accept="image/jpeg,image/png,image/webp" class="mt-3 block w-full text-xs" :aria-label="t('shop_settings.' + field)" @change="chooseImage(field, $event)" />
              <button v-if="images[field]" type="button" class="mt-2 text-xs text-red-600" @click="removeImage(field)">{{ t('actions.remove') }}</button>
            </div>
          </div>
          <p class="text-xs text-muted">{{ t('shop_settings.public_notice') }}</p>
          <div class="grid gap-5 sm:grid-cols-2">
            <label v-for="field in settingsFields" :key="field"><span class="label">{{ t('shop_settings.' + field) }}</span><input v-model="form[field]" :type="field === 'email' ? 'email' : 'text'" class="input w-full" :maxlength="field === 'country' ? 2 : 190" :required="field === 'country'" /><span v-if="fields[field]" class="text-xs text-red-600">{{ fields[field] }}</span></label>
          </div>
          <div class="rounded-xl border border-border-gray p-5"><h2 class="font-semibold">{{ t('shop_settings.bakong') }}</h2><p class="mt-2 text-xs text-muted">{{ t('shop_settings.bakong_help') }}</p><div class="mt-4 grid gap-4 sm:grid-cols-3"><label v-for="field in bankFields" :key="field"><span class="label">{{ t('shop_settings.' + field) }}</span><input v-model="form[field]" class="input w-full" :maxlength="field === 'bakong_account_id' ? 32 : field === 'bakong_merchant_name' ? 25 : 15" /><span v-if="fields[field]" class="text-xs text-red-600">{{ fields[field] }}</span></label></div></div>
          <button class="btn-primary" :disabled="saving"><Save :size="17" />{{ t(saving ? 'common.saving' : 'actions.save') }}</button>
        </fieldset>
      </form>
      <section class="card p-5 sm:p-7"><h2 class="flex items-center gap-2 font-semibold"><Truck :size="19" />{{ t('shop_settings.shipping') }}</h2><p class="mt-2 text-xs text-muted">{{ t('shop_settings.shipping_help') }}</p>
        <ul class="mt-4 divide-y divide-border-gray"><li v-for="item in shippingMethods" :key="item.id" class="flex items-center justify-between gap-4 py-3 text-sm"><span>{{ item.name }} · USD {{ item.price }} · {{ item.is_active ? t('seller.live') : t('seller.hidden') }}</span><button type="button" class="text-primary" @click="editMethod(item)">{{ t('actions.edit') }}</button></li></ul>
        <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveShipping">
          <label><span class="label">{{ t('shop_settings.shipping_name') }}</span><input v-model="method.name" class="input w-full" required maxlength="255" /></label>
          <label><span class="label">{{ t('seller.price') }} (USD)</span><input v-model.number="method.price" class="input w-full" required type="number" min="0" max="999999.99" step="0.01" /></label>
          <label><span class="label">{{ t('shop_settings.days_min') }}</span><input v-model.number="method.estimated_days_min" class="input w-full" required type="number" min="0" max="365" /></label>
          <label><span class="label">{{ t('shop_settings.days_max') }}</span><input v-model.number="method.estimated_days_max" class="input w-full" required type="number" :min="method.estimated_days_min" max="365" /></label>
          <label class="flex items-center gap-2 text-sm"><input v-model="method.is_active" type="checkbox" />{{ t('seller.live') }}</label>
          <p v-if="shippingError" role="alert" class="text-sm text-red-600">{{ shippingError }}</p>
          <div class="flex gap-3 sm:col-span-2"><button class="btn-primary" :disabled="shippingBusy">{{ t(method.id ? 'actions.save' : 'shop_settings.add_shipping') }}</button><button v-if="method.id" type="button" class="btn-secondary" @click="editMethod()">{{ t('actions.cancel') }}</button></div>
        </form>
      </section>
    </template>
  </div>
</template>

