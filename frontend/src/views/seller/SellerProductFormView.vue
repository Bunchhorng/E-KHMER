<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Info, Save } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi } from '@/api/seller'
import { categoriesApi, brandsApi, type ApiBrand, type ApiCategory } from '@/api'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const workspace = useSellerWorkspace()
const shopId = Number(route.params.id)
const productId = Number(route.params.productId)
const isEdit = !!productId
const saving = ref(false)
const loading = ref(true)
const loaded = ref(false)
const error = ref('')
const fields = ref<Record<string, string>>({})
const multiVariant = ref(false)
const categories = ref<ApiCategory[]>([])
const brands = ref<ApiBrand[]>([])
const form = reactive({ name: '', sku: '', category_id: 0, brand_id: 0, short_description: '', description: '', price: 0, compare_at_price: null as number | null, weight: null as number | null, initial_stock: 0, is_active: true })
const baseline = ref('')
const saved = ref(false)
function canLeave() { return !saving.value && (!loaded.value || saved.value || JSON.stringify(form) === baseline.value || window.confirm(t('seller.unsaved'))) }
onBeforeRouteLeave(canLeave)
onBeforeRouteUpdate(canLeave)
const productList = computed(() => ({ name: 'seller-shop-products', params: { id: shopId } }))
async function load() {
  loading.value = true; error.value = ''; loaded.value = false
  try {
    const [categoryResponse, brandResponse] = await Promise.all([categoriesApi.list(), brandsApi.list()])
    categories.value = categoryResponse.data.data; brands.value = brandResponse.data.data
    if (isEdit) {
      const product = (await sellerApi.getProduct(shopId, productId)).data.data
      const simple = product.variants.length === 1 && product.variants[0]?.is_default ? product.variants[0] : null
      multiVariant.value = product.variants.length > 1
      Object.assign(form, { name: product.name, sku: product.sku ?? '', category_id: product.category?.id ?? 0, brand_id: product.brand?.id ?? 0, short_description: product.short_description ?? '', description: product.description ?? '', price: simple?.price ?? product.price, compare_at_price: simple ? simple.compare_at_price : product.compare_at_price, weight: product.weight, is_active: product.is_active })
    }
    baseline.value = JSON.stringify(form)
    loaded.value = true
  } catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { loading.value = false }
}
async function save() {
  if (saving.value || !loaded.value) return
  saving.value = true; error.value = ''; fields.value = {}
  try {
    const nullableNumber = (value: unknown) => value === '' || value == null ? null : Number(value)
    const payload: Record<string, unknown> = { ...form, name: form.name.trim(), sku: form.sku.trim() || null, category_id: form.category_id || null, brand_id: form.brand_id || null, compare_at_price: nullableNumber(form.compare_at_price), weight: nullableNumber(form.weight) }
    if (isEdit) { delete payload.initial_stock; await sellerApi.updateProduct(shopId, productId, payload) }
    else await sellerApi.createProduct(shopId, payload)
    if (workspace.selectedId === shopId) workspace.dashboard = null
    saved.value = true
    saving.value = false
    await router.push(productList.value)
  } catch (cause) { fields.value = extractFieldErrors(cause); error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { saving.value = false }
}
onMounted(() => void load())
</script>
<template>
  <form class="mx-auto max-w-6xl space-y-6" @submit.prevent="save">
    <RouterLink :to="productList" class="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-primary"><ArrowLeft :size="16" />{{ t('seller.products') }}</RouterLink>
    <SellerPageHeader :title="t(isEdit ? 'seller.edit_product' : 'seller.add_product')" :description="t('seller.form_help')"><RouterLink :to="productList" class="btn-secondary">{{ t('actions.cancel') }}</RouterLink><button class="btn-primary" :disabled="saving || loading || !loaded"><Save :size="17" />{{ t(saving ? 'common.saving' : 'seller.save_product') }}</button></SellerPageHeader>
    <p v-if="error" role="alert" class="rounded-xl bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400">{{ error }} <button v-if="!loaded" type="button" class="ml-2 underline" @click="load">{{ t('actions.retry') }}</button></p>
    <div v-if="loading" class="card h-80 animate-pulse bg-border-gray/20" aria-busy="true"><span class="sr-only">{{ t('common.loading') }}</span></div>
    <div v-else-if="loaded" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div class="min-w-0 space-y-6">
        <section class="card rounded-2xl p-5 sm:p-6"><h2 class="font-semibold">{{ t('seller.details') }}</h2><p class="mt-1 text-xs text-muted">{{ t('seller.details_help') }}</p>
          <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <label class="sm:col-span-2"><span class="label">{{ t('seller.name') }} *</span><input v-model="form.name" required class="input w-full" maxlength="255" :aria-invalid="!!fields.name" /><span v-if="fields.name" class="mt-1 block text-xs text-red-600">{{ fields.name }}</span></label>
            <label class="sm:col-span-2"><span class="label">{{ t('seller.sku') }}</span><input v-model="form.sku" class="input w-full" maxlength="255" :aria-invalid="!!fields.sku" /><span class="mt-1.5 block text-xs leading-relaxed text-muted">{{ fields.sku || t('seller.sku_help') }}</span></label>
            <label><span class="label">{{ t('seller.category') }}</span><select v-model.number="form.category_id" class="select w-full"><option :value="0">{{ t('seller.none') }}</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
            <label><span class="label">{{ t('seller.brand') }}</span><select v-model.number="form.brand_id" class="select w-full"><option :value="0">{{ t('seller.none') }}</option><option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option></select></label>
            <label class="sm:col-span-2"><span class="label">{{ t('seller.short_description') }}</span><input v-model="form.short_description" class="input w-full" maxlength="255" /></label>
            <label class="sm:col-span-2"><span class="label">{{ t('seller.description') }}</span><textarea v-model="form.description" class="textarea w-full" rows="6" maxlength="60000"></textarea></label>
          </div>
        </section>
        <section class="card rounded-2xl p-5 sm:p-6"><h2 class="font-semibold">{{ t('seller.pricing_stock') }}</h2><p v-if="multiVariant" class="mt-3 rounded-lg bg-primary/5 p-3 text-xs leading-relaxed text-muted">{{ t('seller.variants_help') }}</p><div class="mt-6 grid gap-5 sm:grid-cols-2">
          <label><span class="label">{{ t('seller.price') }} (USD) *</span><input v-model.number="form.price" required min="0" max="99999999.99" step="0.01" type="number" class="input w-full" :aria-invalid="!!fields.price" /><span v-if="fields.price" class="mt-1 block text-xs text-red-600">{{ fields.price }}</span></label>
          <label><span class="label">{{ t('seller.original_price') }} (USD)</span><input v-model.number="form.compare_at_price" min="0" max="99999999.99" step="0.01" type="number" class="input w-full" /><span class="mt-1.5 block text-xs leading-relaxed text-muted">{{ t('seller.original_help') }}</span></label>
          <label><span class="label">{{ t('seller.weight') }}</span><input v-model.number="form.weight" min="0" max="999999.99" step="0.01" type="number" class="input w-full" /></label>
          <label v-if="!isEdit"><span class="label">{{ t('seller.initial_stock') }} *</span><input v-model.number="form.initial_stock" required min="0" max="1000000" step="1" type="number" class="input w-full" :aria-invalid="!!fields.initial_stock" /><span class="mt-1.5 block text-xs leading-relaxed" :class="fields.initial_stock ? 'text-red-600' : 'text-muted'">{{ fields.initial_stock || t('seller.stock_later') }}</span></label>
        </div><div v-if="isEdit" class="mt-5 flex items-start gap-3 rounded-xl bg-canvas p-4"><Info :size="18" class="shrink-0 text-primary" /><div><p class="text-xs leading-relaxed text-muted">{{ t('seller.edit_stock_help') }}</p><RouterLink :to="{ name: 'seller-shop-inventory', params: { id: shopId }, query: { q: form.sku || form.name } }" class="mt-2 inline-block text-sm font-medium text-primary">{{ t('seller.update_stock') }} →</RouterLink></div></div></section>
      </div>
      <section class="card rounded-2xl p-5 sm:p-6 xl:sticky xl:top-28"><h2 class="font-semibold">{{ t('seller.visibility') }}</h2><p class="mt-2 text-xs leading-relaxed text-muted">{{ t('seller.visibility_help') }}</p><div class="mt-5 space-y-3"><label v-for="option in [{ value: true, label: 'live' }, { value: false, label: 'hidden' }]" :key="option.label" class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm font-medium" :class="form.is_active === option.value ? 'border-primary bg-primary/5 text-primary' : 'border-border-gray'"><input v-model="form.is_active" type="radio" name="visibility" :value="option.value" class="accent-primary" />{{ t('seller.' + option.label) }}</label></div><button class="btn-primary mt-6 w-full" :disabled="saving"><Save :size="17" />{{ t(saving ? 'common.saving' : 'seller.save_product') }}</button><p class="mt-4 text-xs leading-relaxed text-muted">{{ t('seller.shop_only') }}</p></section>
    </div>
  </form>
</template>
