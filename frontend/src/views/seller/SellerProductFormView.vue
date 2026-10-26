<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Banknote, CheckCircle2, ChevronDown, ChevronRight, Circle, ClipboardCheck, ExternalLink, Eye, FileText, Info, Save, Send, ShoppingBag, Star, Truck } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi } from '@/api/seller'
import { categoriesApi, brandsApi, type ApiBrand, type ApiCategory } from '@/api'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import SellerProductPhotos from '@/components/seller/SellerProductPhotos.vue'
import type { ProductPhoto } from '@/types/productPhotos'
import type { SellerVariant } from '@/types/sellerVariants'
import SellerProductVariants from '@/components/seller/SellerProductVariants.vue'
import SellerBarcodeField from '@/components/seller/SellerBarcodeField.vue'
import AssetImage from '@/components/AssetImage.vue'
import RichTextEditor from '@/components/RichTextEditor.vue'
import type { ApiShippingMethod } from '@/api/shipping'
import { formatPrice } from '@/utils/format'
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const workspace = useSellerWorkspace()
const shopId = Number(route.params.id)
const productId = Number(route.params.productId)
const isEdit = !!productId
const saving = ref(false)
const saveIntent = ref<'current' | 'draft'>('current')
const formElement = ref<HTMLFormElement | null>(null)
const touched = reactive({ price: false, stock: false, weight: false })
const shippingMethods = ref<ApiShippingMethod[]>([])
const shippingLoading = ref(false)
const shippingError = ref('')
const rating = reactive({ average: 0, count: 0 })
const loading = ref(true)
const loaded = ref(false)
const error = ref('')
const fields = ref<Record<string, string>>({})
const multiVariant = ref(false)
const variants = ref<SellerVariant[]>([])
const originalQuantities = ref<Record<number, number>>({})
const categories = ref<ApiCategory[]>([])
const brands = ref<ApiBrand[]>([])
const photos = ref<ProductPhoto[]>([])
const photoError = computed(() => Object.entries(fields.value).find(([key]) => key === 'images' || key.startsWith('images.') || key === 'image_files' || key.startsWith('image_files.'))?.[1])
const form = reactive({ name: '', sku: '', barcode: '', cost_price: null as number | null, category_id: 0, brand_id: 0, short_description: '', description: '', price: 0, compare_at_price: null as number | null, weight: null as number | null, initial_stock: 0, is_active: true })
const baseline = ref('')
const saved = ref(false)
function snapshot() { return JSON.stringify({ form, photos: photos.value.map(photo => photo.url), variants: variants.value, multiVariant: multiVariant.value }) }
function canLeave() { return !saving.value && (!loaded.value || saved.value || snapshot() === baseline.value || window.confirm(t('seller.unsaved'))) }
onBeforeRouteLeave(canLeave)
onBeforeRouteUpdate(canLeave)
const productList = computed(() => ({ name: 'seller-shop-products', params: { id: shopId } }))
const primaryLabel = computed(() => isEdit || !form.is_active ? 'seller.save_product' : 'seller.publish_product')
const categoryOptions = computed(() => {
  const flatten = (items: ApiCategory[], depth = 0): { id: number; label: string }[] => items.flatMap(item => [{ id: item.id, label: '— '.repeat(depth) + item.name }, ...flatten(item.children ?? [], depth + 1)])
  return flatten(categories.value)
})
const activeShipping = computed(() => shippingMethods.value.filter(method => method.is_active))
function hasNonNegativeNumber(value: unknown) { return value !== '' && value !== null && value !== undefined && Number.isFinite(Number(value)) && Number(value) >= 0 }
const previewPrice = computed(() => {
  const prices = multiVariant.value ? variants.value.filter(variant => variant.is_active).map(variant => Number(variant.price)).filter(Number.isFinite) : []
  return prices.length ? Math.min(...prices) : Number(form.price) || 0
})
const checklist = computed(() => [
  { key: 'photos', section: 'seller-product-photos', done: photos.value.length ? 1 : 0, total: 1 },
  { key: 'details', section: 'seller-product-details', done: [!!form.name.trim(), !!form.category_id, !!form.short_description.trim(), !!form.description.replace(/<[^>]*>/g, '').trim()].filter(Boolean).length, total: 4 },
  { key: 'pricing', section: 'seller-product-pricing', done: [multiVariant.value ? variants.value.length > 0 && variants.value.every(variant => hasNonNegativeNumber(variant.price)) : (isEdit || touched.price) && hasNonNegativeNumber(form.price), hasNonNegativeNumber(form.cost_price), multiVariant.value ? variants.value.length > 0 && variants.value.every(variant => Number.isInteger(Number(variant.quantity)) && Number(variant.quantity) >= variant.reserved_quantity) : (isEdit || touched.stock) && hasNonNegativeNumber(form.initial_stock) && Number.isInteger(Number(form.initial_stock)) && Number(form.initial_stock) >= (variants.value[0]?.reserved_quantity ?? 0)].filter(Boolean).length, total: 3 },
  { key: 'shipping', section: 'seller-product-shipping', done: [(isEdit || touched.weight) && hasNonNegativeNumber(form.weight), activeShipping.value.length > 0].filter(Boolean).length, total: 2 }
])
function jumpTo(section: string) {
  const target = document.getElementById(section)
  target?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' })
  const control = [...target?.querySelectorAll<HTMLElement>('input, select, button, [contenteditable="true"]') ?? []].find(element => element.getClientRects().length > 0)
  control?.focus({ preventScroll: true })
}
function toggleVariants(event: Event) {
  multiVariant.value = (event.target as HTMLInputElement).checked
  if (multiVariant.value && !variants.value.length) variants.value.push({ key: 'first', name: '', sku: '', barcode: form.barcode, cost_price: form.cost_price, price: form.price, compare_at_price: null, quantity: form.initial_stock, reserved_quantity: 0, is_active: true, image_id: null, attributes: [{ attribute: 'Color', value: '' }, { attribute: 'Size', value: '' }] })
}
async function loadShipping() {
  shippingLoading.value = true; shippingError.value = ''
  try { shippingMethods.value = (await sellerApi.shippingMethods(shopId)).data.data }
  catch (cause) { shippingError.value = extractErrorMessage(cause, t('seller.shipping_load_failed')) }
  finally { shippingLoading.value = false }
}
async function load() {
  loading.value = true; error.value = ''; loaded.value = false
  try {
    const [categoryResponse, brandResponse] = await Promise.all([categoriesApi.list(), brandsApi.list()])
    categories.value = categoryResponse.data.data; brands.value = brandResponse.data.data
    if (isEdit) {
      const product = (await sellerApi.getProduct(shopId, productId)).data.data
      rating.average = product.rating_avg; rating.count = product.rating_count
      photos.value = [...product.gallery].sort((a, b) => Number(b.is_cover) - Number(a.is_cover) || a.sort_order - b.sort_order).map(image => ({ key: `saved-${image.id}`, url: image.image_path }))
      const simple = product.variants.length === 1 && !product.variants[0]?.attributes.length ? product.variants[0] : null
      originalQuantities.value = Object.fromEntries(product.variants.map(variant => [variant.id, variant.quantity]))
      multiVariant.value = !simple
      variants.value = product.variants.map(variant => ({ key: `existing-${variant.id}`, id: variant.id, name: variant.name ?? '', sku: variant.sku ?? '', barcode: variant.barcode ?? '', cost_price: variant.cost_price ?? null, price: variant.price ?? product.price, compare_at_price: variant.compare_at_price, quantity: variant.quantity, reserved_quantity: variant.reserved_quantity, is_active: variant.is_active, image_id: variant.image_id, attributes: variant.attributes.map(attribute => ({ attribute: attribute.name, value: attribute.value })) }))
      Object.assign(form, { name: product.name, sku: product.sku ?? '', barcode: simple?.barcode ?? product.barcode ?? '', cost_price: simple?.cost_price ?? product.cost_price ?? null, category_id: product.category?.id ?? 0, brand_id: product.brand?.id ?? 0, short_description: product.short_description ?? '', description: product.description ?? '', price: simple?.price ?? product.price, compare_at_price: simple ? simple.compare_at_price : product.compare_at_price, weight: product.weight, initial_stock: simple?.quantity ?? 0, is_active: product.is_active })
    }
    baseline.value = snapshot()
    loaded.value = true
  } catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { loading.value = false }
}
async function save(intent: 'current' | 'draft' = 'current') {
  if (saving.value || !loaded.value) return
  saving.value = true; saveIntent.value = intent; error.value = ''; fields.value = {}
  try {
    const nullableNumber = (value: unknown) => value === '' || value == null ? null : Number(value)
    const payload: Record<string, unknown> = { ...form, name: form.name.trim(), sku: form.sku.trim() || null, barcode: form.barcode.trim() || null, cost_price: nullableNumber(form.cost_price), category_id: form.category_id || null, brand_id: form.brand_id || null, compare_at_price: nullableNumber(form.compare_at_price), weight: nullableNumber(form.weight) }
    payload.is_active = intent === 'draft' ? false : form.is_active
    if (isEdit) delete payload.initial_stock
    const simpleVariant = variants.value[0]
    if (isEdit && !multiVariant.value && simpleVariant?.id && Number(form.initial_stock) !== originalQuantities.value[simpleVariant.id]) {
      payload.quantity = Number(form.initial_stock)
    }
    if (multiVariant.value) {
      payload.variants = JSON.stringify(variants.value.map(variant => ({
        ...(variant.id ? { id: variant.id } : {}),
        ...(!variant.id || Number(variant.quantity) !== originalQuantities.value[variant.id] ? { quantity: Number(variant.quantity) } : {}),
        name: variant.name.trim() || null, sku: variant.sku.trim() || null,
        barcode: variant.barcode.trim() || null, cost_price: nullableNumber(variant.cost_price),
        price: Number(variant.price), compare_at_price: nullableNumber(variant.compare_at_price),
        is_active: variant.is_active, image_id: photos.value.some(photo => photo.key === `saved-${variant.image_id}`) ? variant.image_id : null,
        attributes: variant.attributes.filter(attribute => attribute.attribute.trim() && attribute.value.trim()).map(attribute => ({ attribute: attribute.attribute.trim(), value: attribute.value.trim() }))
      })))
    }
    const multipart = new FormData()
    for (const [key, value] of Object.entries(payload)) {
      multipart.append(key, value == null ? '' : typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
    }
    let uploadIndex = 0
    multipart.append('images', JSON.stringify(photos.value.map(photo => {
      if (!photo.file) return photo.url
      multipart.append(`image_files[${uploadIndex}]`, photo.file)
      return `upload:${uploadIndex++}`
    })))
    if (isEdit) await sellerApi.updateProduct(shopId, productId, multipart)
    else await sellerApi.createProduct(shopId, multipart)
    if (workspace.selectedId === shopId) workspace.dashboard = null
    saved.value = true
    saving.value = false
    await router.push(productList.value)
  } catch (cause) { fields.value = extractFieldErrors(cause); error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { saving.value = false }
}
function saveDraft() { if (formElement.value?.reportValidity()) void save('draft') }
onMounted(() => { void load(); void loadShipping() })
</script>
<template>
  <form ref="formElement" class="product-form" @submit.prevent="save()">
    <RouterLink :to="productList" class="form-back inline-flex items-center gap-2 font-medium text-muted hover:text-primary"><ArrowLeft :size="15" />{{ t('seller.products') }}</RouterLink>
    <p v-if="error" role="alert" class="mb-4 rounded-xl bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400">{{ error }} <button v-if="!loaded" type="button" class="ml-2 underline" @click="load">{{ t('actions.retry') }}</button></p>
    <div v-if="loading" class="card h-80 animate-pulse bg-border-gray/20" aria-busy="true"><span class="sr-only">{{ t('common.loading') }}</span></div>
    <div v-else-if="loaded" class="product-form-grid">
      <div class="product-form-main min-w-0">
        <SellerPageHeader class="form-heading" :title="t(isEdit ? 'seller.edit_product' : 'seller.create_product')" :description="t(isEdit ? 'seller.form_help' : 'seller.create_product_help')">
          <button type="button" class="btn-secondary form-draft" :disabled="saving" @click="saveDraft"><Save :size="18" />{{ t(saving && saveIntent === 'draft' ? 'common.saving' : 'seller.save_draft') }}</button>
          <button type="submit" class="btn-primary form-publish" :disabled="saving"><component :is="isEdit || !form.is_active ? Save : Send" :size="19" :stroke-width="1.7" />{{ t(saving && saveIntent === 'current' ? 'common.saving' : primaryLabel) }}</button>
        </SellerPageHeader>

        <SellerProductPhotos id="seller-product-photos" v-model="photos" :disabled="saving" :server-error="photoError" />

        <section id="seller-product-details" class="card form-section" aria-labelledby="product-details-title">
          <header class="form-section-heading"><span class="form-section-icon"><FileText :size="24" :stroke-width="1.8" /></span><div><h2 id="product-details-title">{{ t('seller.details') }}</h2><p>{{ t('seller.details_help') }}</p></div></header>
          <div class="form-fields grid gap-x-4 gap-y-3.5 sm:grid-cols-2">
            <label class="sm:col-span-2"><span class="label">{{ t('seller.name') }} *</span><input v-model="form.name" required class="input" maxlength="255" :placeholder="t('seller.name_placeholder')" :disabled="saving" :aria-invalid="!!fields.name" /><span v-if="fields.name" class="field-error">{{ fields.name }}</span></label>
            <label><span class="label">{{ t('seller.sku') }}</span><input v-model="form.sku" class="input" :aria-label="t('seller.sku')" maxlength="255" :placeholder="t('seller.sku_placeholder')" :disabled="saving" :aria-invalid="!!fields.sku" /><span class="field-help" :class="{ 'text-red-600': fields.sku }">{{ fields.sku || t('seller.sku_help') }}</span></label>
            <SellerBarcodeField v-model="form.barcode" class="form-barcode" :disabled="saving" :error="fields.barcode" :placeholder="t('seller.barcode_placeholder')" :help="t('seller.barcode_form_help')" />
            <label><span class="label">{{ t('seller.category') }}</span><span class="form-select relative block"><select v-model.number="form.category_id" class="select" :aria-label="t('seller.category')" :disabled="saving" :aria-invalid="!!fields.category_id"><option :value="0">{{ t('seller.select_category') }}</option><option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.label }}</option></select><ChevronDown :size="15" aria-hidden="true" /></span><span v-if="fields.category_id" class="field-error">{{ fields.category_id }}</span></label>
            <label><span class="label">{{ t('seller.brand') }}</span><span class="form-select relative block"><select v-model.number="form.brand_id" class="select" :aria-label="t('seller.brand')" :disabled="saving" :aria-invalid="!!fields.brand_id"><option :value="0">{{ t('seller.select_brand') }}</option><option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option></select><ChevronDown :size="15" aria-hidden="true" /></span><span v-if="fields.brand_id" class="field-error">{{ fields.brand_id }}</span></label>
            <label class="sm:col-span-2"><span class="mb-1 flex items-center justify-between gap-3"><span class="label !mb-0">{{ t('seller.short_description') }}</span><span id="seller-short-description-count" class="character-count text-muted">{{ form.short_description.length }} / 255</span></span><input v-model="form.short_description" class="input" :aria-label="t('seller.short_description')" aria-describedby="seller-short-description-count" maxlength="255" :disabled="saving" :placeholder="t('seller.short_description_placeholder')" :aria-invalid="!!fields.short_description" /><span v-if="fields.short_description" class="field-error">{{ fields.short_description }}</span></label>
            <div class="sm:col-span-2"><p class="label">{{ t('seller.full_description') }}</p><RichTextEditor v-model="form.description" :label="t('seller.full_description')" :placeholder="t('seller.description_placeholder')" :disabled="saving" /><p v-if="fields.description" class="field-error">{{ fields.description }}</p></div>
          </div>
        </section>

        <section id="seller-product-pricing" class="card form-section" aria-labelledby="product-pricing-title">
          <header class="form-section-heading"><span class="form-section-icon"><Banknote :size="24" :stroke-width="1.8" /></span><div><h2 id="product-pricing-title">{{ t('seller.pricing_stock') }}</h2><p>{{ t('seller.pricing_form_help') }}</p></div></header>
          <div class="grid gap-x-4 gap-y-4 sm:grid-cols-2">
            <label><span class="label">{{ t('seller.purchase_price') }} (USD)</span><input v-model.number="form.cost_price" min="0" max="99999999.99" step="0.01" type="number" class="input" :placeholder="t('seller.price_placeholder')" :disabled="saving" :aria-label="`${t('seller.purchase_price')} (USD)`" aria-describedby="seller-cost-help" :aria-invalid="!!fields.cost_price" /><span id="seller-cost-help" class="field-help" :class="{ 'text-red-600': fields.cost_price }">{{ fields.cost_price || t('seller.purchase_help') }}</span></label>
            <label><span class="label">{{ t('seller.price') }} (USD) *</span><input v-model.number="form.price" required min="0" max="99999999.99" step="0.01" type="number" class="input" :disabled="saving" :aria-invalid="!!fields.price" @input="touched.price = true" /><span v-if="fields.price" class="field-error">{{ fields.price }}</span></label>
            <label><span class="label">{{ t('seller.original_price') }} (USD)</span><input v-model.number="form.compare_at_price" min="0" max="99999999.99" step="0.01" type="number" class="input" :disabled="saving" :placeholder="t('seller.price_placeholder')" :aria-invalid="!!fields.compare_at_price" /><span class="field-help">{{ fields.compare_at_price || t('seller.original_help') }}</span></label>
            <label v-if="!multiVariant"><span class="label">{{ t(isEdit ? 'seller.on_hand' : 'seller.initial_stock') }} *</span><input v-model.number="form.initial_stock" required :min="isEdit ? (variants[0]?.reserved_quantity ?? 0) : 0" max="1000000" step="1" type="number" class="input" :disabled="saving" :aria-label="t(isEdit ? 'seller.on_hand' : 'seller.initial_stock') + ' *'" :aria-invalid="!!(fields.quantity || fields.initial_stock)" @input="touched.stock = true" /><span v-if="fields.quantity || fields.initial_stock" role="alert" class="field-error">{{ fields.quantity || fields.initial_stock }}</span><span v-else-if="isEdit && variants[0]?.reserved_quantity" class="field-help">{{ t('seller.reserved') }}: {{ variants[0]?.reserved_quantity }}</span><span v-else class="field-help">{{ t('seller.stock_later') }}</span></label>
          </div>
          <div class="form-variants-toggle mt-5 rounded-xl border border-border-gray bg-canvas/50 p-4"><label class="flex items-center gap-3 text-sm font-semibold"><input :checked="multiVariant" type="checkbox" :disabled="saving || (isEdit && variants.length > 1)" @change="toggleVariants" />{{ t('seller_variants.enable') }}</label><p v-if="fields.variants" role="alert" class="field-error">{{ fields.variants }}</p></div>
          <SellerProductVariants v-if="multiVariant" v-model="variants" class="form-variants mt-4" :photos="photos" :disabled="saving" :price="form.price" :cost-price="form.cost_price" :errors="fields" />
        </section>

        <section id="seller-product-shipping" class="card form-section" aria-labelledby="product-shipping-title">
          <header class="form-section-heading"><span class="form-section-icon"><Truck :size="24" :stroke-width="1.8" /></span><div><h2 id="product-shipping-title">{{ t('seller.product_shipping') }}</h2><p>{{ t('seller.product_shipping_help') }}</p></div></header>
          <div class="grid gap-4 sm:grid-cols-2">
            <label><span class="label">{{ t('seller.weight') }}</span><input v-model.number="form.weight" min="0" max="999999.99" step="0.01" type="number" class="input" :placeholder="t('seller.weight_placeholder')" :disabled="saving" :aria-invalid="!!fields.weight" @input="touched.weight = true" /><span v-if="fields.weight" class="field-error">{{ fields.weight }}</span></label>
            <div><p class="label">{{ t('seller.delivery_options') }}</p><p v-if="shippingLoading" class="field-help">{{ t('common.loading') }}</p><p v-else-if="shippingError" role="alert" class="field-error">{{ shippingError }} <button type="button" class="underline" @click="loadShipping">{{ t('actions.retry') }}</button></p><p v-else class="field-help !mt-0">{{ activeShipping.length ? activeShipping.map(method => method.name).join(', ') : t('seller.no_delivery_options') }}</p><RouterLink :to="{ name: 'seller-shop-settings', params: { id: shopId } }" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary">{{ t('seller.manage_shipping') }}<ChevronRight :size="14" /></RouterLink></div>
          </div>
        </section>
        <RouterLink :to="productList" class="form-cancel text-sm font-medium text-muted hover:text-primary">{{ t('actions.cancel') }}</RouterLink>
      </div>

      <aside class="product-form-aside" :aria-label="t('seller.product_setup')">
        <section class="card form-side-card" aria-labelledby="product-status-title">
          <header class="form-section-heading"><span class="form-section-icon"><Eye :size="23" /></span><div><h2 id="product-status-title">{{ t('seller.product_status') }}</h2><p>{{ t('seller.product_status_help') }}</p></div></header>
          <label class="status-select form-select relative block" :class="form.is_active ? 'status-live' : 'status-hidden'"><span class="sr-only">{{ t('seller.visibility') }}</span><span class="status-dot" aria-hidden="true"></span><select v-model="form.is_active" class="select" :aria-label="t('seller.visibility')" :disabled="saving" aria-describedby="product-visibility-help"><option :value="true">{{ t('seller.live') }}</option><option :value="false">{{ t('seller.hidden') }}</option></select><ChevronDown :size="15" aria-hidden="true" /></label>
          <p id="product-visibility-help" class="side-help">{{ t(form.is_active ? 'seller.live_status_help' : 'seller.hidden_status_help') }}</p>
        </section>
        <section class="card form-side-card" aria-labelledby="product-checklist-title">
          <header class="form-section-heading"><span class="form-section-icon"><ClipboardCheck :size="24" :stroke-width="1.8" /></span><div><h2 id="product-checklist-title">{{ t('seller.setup_checklist') }}</h2><p>{{ t('seller.checklist_help') }}</p></div></header>
          <ul class="setup-checklist"><li v-for="step in checklist" :key="step.key"><button type="button" :aria-label="t('seller.checklist_' + step.key) + ': ' + step.done + '/' + step.total" @click="jumpTo(step.section)"><component :is="step.done === step.total ? CheckCircle2 : Circle" :size="21" :stroke-width="1.5" class="shrink-0" :class="step.done === step.total ? 'text-primary' : 'text-muted/50'" /><span class="min-w-0 flex-1">{{ t('seller.checklist_' + step.key) }}</span><span class="tabular-nums text-muted">{{ step.done }}/{{ step.total }}</span><ChevronRight :size="14" class="shrink-0 text-muted" aria-hidden="true" /></button></li></ul>
        </section>
        <section class="card form-side-card form-preview" aria-labelledby="product-preview-title">
          <header class="form-section-heading"><span class="form-section-icon"><ExternalLink :size="23" :stroke-width="1.8" /></span><div><h2 id="product-preview-title">{{ t('seller.product_preview') }}</h2><p>{{ t('seller.product_preview_help') }}</p></div></header>
          <div class="preview-card overflow-hidden rounded-xl border border-border-gray">
            <div class="preview-product flex items-start gap-3"><AssetImage v-if="photos[0]" :src="photos[0].url" :alt="form.name || t('seller.name')" class="preview-image rounded-lg border border-border-gray bg-canvas" /><span v-else class="preview-image flex items-center justify-center rounded-lg bg-canvas text-muted/60" aria-hidden="true"><ShoppingBag :size="42" :stroke-width="1.5" /></span><div class="min-w-0 flex-1"><h3 class="preview-name font-semibold">{{ form.name.trim() || t('seller.name') }}</h3><p class="preview-price font-bold tabular-nums">{{ formatPrice(previewPrice) }}</p><p v-if="form.short_description.trim()" class="preview-description text-muted">{{ form.short_description }}</p><div v-else class="preview-lines" aria-hidden="true"><span></span><span></span></div><div class="preview-rating flex items-center gap-0.5 text-muted/70" :aria-label="t('seller.preview_rating', { rating: rating.average, count: rating.count })"><Star v-for="star in 5" :key="star" :size="13" :fill="star <= Math.round(rating.average) ? 'currentColor' : 'none'" aria-hidden="true" /><span class="ml-1">({{ rating.count }})</span></div></div></div>
            <p class="preview-note flex items-start gap-2 border-t border-border-gray bg-primary/[0.04]"><Info :size="15" class="mt-0.5 shrink-0 text-primary" /><span>{{ t('seller.preview_note') }}</span></p>
          </div>
        </section>
      </aside>
    </div>
  </form>
</template>

<style scoped>
.product-form { min-width: 0; }
.form-back { margin: -2px 0 0 4px; font-size: 14px; line-height: 1.5; }
.product-form-grid { display: grid; min-width: 0; grid-template-columns: minmax(0, 1fr) 330px; gap: 20px; align-items: start; }
.product-form-main { display: flex; flex-direction: column; gap: 16px; }
.form-heading { min-height: 76px; padding: 4px 5px 0; }
.form-heading :deep(h1) { font-size: 36px; line-height: 1.2; font-weight: 750; letter-spacing: -1.1px; }
.form-heading :deep(p) { margin-top: 3px; font-size: 15px; line-height: 1.6; }
.form-heading :deep(> div:last-child) { gap: 10px; padding-top: 4px; }
.form-draft, .form-publish { min-height: 44px; padding: 9px 17px; border-radius: 8px; font-size: 14px; gap: 10px; }
.form-publish { background: linear-gradient(120deg, #1762ff, #2c69ff); box-shadow: 0 3px 6px rgb(30 99 255 / 8%); }
.form-section, .form-side-card { min-width: 0; border-radius: 16px; padding: 18px 20px 20px; scroll-margin-top: 100px; }
.product-form :deep(#seller-product-photos) { scroll-margin-top: 100px; }
.form-section-heading { display: flex; min-width: 0; align-items: flex-start; gap: 16px; margin-bottom: 7px; }
.form-section-icon { display: flex; width: 42px; height: 42px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 12px; background: rgb(var(--color-primary) / 9%); color: rgb(var(--color-primary)); }
.form-section-heading h2 { font-size: 15px; font-weight: 650; line-height: 1.5; letter-spacing: -.15px; }
.form-section-heading p { margin-top: 1px; color: rgb(var(--color-muted)); font-size: 12px; line-height: 1.5; }
.product-form :deep(.label) { margin-bottom: 4px; font-size: 13px; line-height: 1.5; font-weight: 550; }
.product-form :deep(.input), .product-form :deep(.select), .product-form :deep(.textarea) { min-height: 38px; padding: 7px 13px; border-radius: 8px; font-size: 14px; line-height: 1.5; }
.product-form :deep(.input::placeholder) { color: rgb(var(--color-muted) / 70%); }
.product-form :deep(.select) { padding-right: 34px; }
.product-form :deep([contenteditable]) { min-height: 150px; font-size: 13px; line-height: 1.7; }
.product-form :deep([contenteditable] ul) { padding-left: 1.4em; list-style-type: disc; }
.product-form :deep([contenteditable] ol) { padding-left: 1.4em; list-style-type: decimal; }
.product-form :deep([contenteditable] h3) { font-size: 1.25em; font-weight: 650; }
.product-form :deep([contenteditable] blockquote) { border-left: 3px solid rgb(var(--color-border)); padding-left: 12px; color: rgb(var(--color-muted)); }
.field-help, .field-error { display: block; margin-top: 5px; font-size: 12px; line-height: 1.6; }
.field-help { color: rgb(var(--color-muted)); }
.field-error { color: #dc2626; }
.character-count { font-size: 12px; line-height: 1.5; }
.form-select > svg { position: absolute; top: 50%; right: 14px; transform: translateY(-50%); pointer-events: none; }
.form-barcode :deep(.btn-secondary) { min-height: 38px; padding: 7px 12px; font-size: 12px; border-radius: 8px; gap: 7px; }
.form-barcode :deep(.font-mono) { font-family: inherit; }
.form-barcode :deep(p) { margin-top: 5px; font-size: 12px; line-height: 1.6; }
.form-variants-toggle input { width: 16px; height: 16px; flex-shrink: 0; accent-color: rgb(var(--color-primary)); }
.product-form :deep(.form-variants) { padding: 16px; box-shadow: none; }
.form-cancel { align-self: flex-start; margin: 0 4px 8px; }
.product-form-aside { position: sticky; top: 96px; display: flex; min-width: 0; flex-direction: column; gap: 16px; }
.form-side-card { padding: 15px 17px 18px; }
.form-side-card .form-section-heading { gap: 14px; margin-bottom: 16px; }
.form-side-card .form-section-heading h2 { font-size: 14px; }
.form-side-card .form-section-heading p { font-size: 12px; }
.status-select .select { min-height: 43px; padding-left: 41px; font-size: 14px; font-weight: 550; }
.status-live .select { border-color: rgb(16 185 129 / 15%); background: rgb(16 185 129 / 10%); }
.status-hidden .select { background: rgb(var(--color-muted) / 7%); }
.status-dot { position: absolute; top: 50%; left: 17px; z-index: 1; width: 11px; height: 11px; transform: translateY(-50%); border-radius: 50%; background: rgb(var(--color-muted)); pointer-events: none; }
.status-live .status-dot { background: #16b85c; }
.side-help { margin-top: 9px; font-size: 12px; line-height: 1.6; color: rgb(var(--color-muted)); }
.setup-checklist { margin-top: -3px; }
.setup-checklist li + li { border-top: 1px solid rgb(var(--color-border)); }
.setup-checklist button { display: flex; width: 100%; min-height: 39px; align-items: center; gap: 13px; text-align: left; font-size: 12px; line-height: 1.5; }
.setup-checklist button:hover { color: rgb(var(--color-primary)); }
.preview-product { padding: 11px; }
.preview-image { width: 112px; height: 114px; flex-shrink: 0; }
.preview-name { padding-top: 2px; font-size: 13px; line-height: 1.5; overflow-wrap: anywhere; }
.preview-price { margin-top: 6px; font-size: 16px; line-height: 1.5; }
.preview-description { display: -webkit-box; margin-top: 4px; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 11px; line-height: 1.5; overflow-wrap: anywhere; }
.preview-lines { display: flex; margin-top: 4px; flex-direction: column; gap: 9px; }
.preview-lines span { height: 11px; border-radius: 6px; background: rgb(var(--color-border) / 70%); }
.preview-lines span:last-child { width: 85%; }
.preview-rating { margin-top: 11px; font-size: 11px; line-height: 1.4; }
.preview-note { padding: 10px 11px; font-size: 10px; line-height: 1.8; color: rgb(var(--color-muted)); }
:global(html.dark .product-form .field-error) { color: #f87171; }
@media (min-width: 1280px) and (max-width: 1535px) { .product-form-grid { grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; } .form-heading { min-height: 108px; } .form-heading :deep(h1) { font-size: 32px; } .form-heading :deep(> div:last-child) { padding-top: 0; } .form-section, .form-side-card { padding-inline: 16px; } .preview-image { width: 90px; height: 110px; } }
@media (max-width: 1279px) { .product-form-grid { grid-template-columns: minmax(0, 1fr); } .product-form-aside { position: static; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); } .form-preview { grid-column: 1 / -1; } .preview-image { width: 112px; } .form-heading { min-height: 100px; } }
@media (max-width: 767px) { .form-back { margin-bottom: 14px; font-size: 13px; } .form-heading { padding: 0 2px; gap: 12px; min-height: 112px; } .form-heading :deep(h1) { font-size: 30px; letter-spacing: -.7px; } .form-heading :deep(p) { font-size: 13px; } .form-heading :deep(> div:last-child) { padding-top: 0; } .form-draft, .form-publish { min-height: 42px; padding-inline: 13px; font-size: 13px; gap: 8px; } .form-section { padding: 16px; } .form-section-heading { gap: 12px; } .product-form-aside { grid-template-columns: minmax(0, 1fr); } .form-preview { grid-column: auto; } .product-form :deep(.input), .product-form :deep(.select) { min-height: 42px; font-size: 14px; } .form-barcode :deep(.input) { flex-basis: 160px; } .form-barcode :deep(.btn-secondary) { min-height: 42px; } }
</style>
