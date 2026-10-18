<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import type { SellerVariant } from '@/types/sellerVariants'
import type { ProductPhoto } from '@/types/productPhotos'
import SellerBarcodeField from '@/components/seller/SellerBarcodeField.vue'
const props = defineProps<{ modelValue: SellerVariant[]; photos: ProductPhoto[]; disabled: boolean; price: number; costPrice: number | null; errors?: Record<string, string> }>()
const emit = defineEmits<{ 'update:modelValue': [variants: SellerVariant[]] }>()
const { t } = useI18n()
function add() {
  emit('update:modelValue', [...props.modelValue, { key: `new-${Date.now()}-${Math.random()}`, name: '', sku: '', barcode: '', cost_price: props.costPrice, price: props.price, compare_at_price: null, quantity: 0, reserved_quantity: 0, is_active: true, image_id: null, attributes: [{ attribute: 'Color', value: '' }, { attribute: 'Size', value: '' }] }])
}
function remove(row: SellerVariant) {
  if (row.reserved_quantity || props.disabled || !window.confirm(t('seller_variants.remove_confirm'))) return
  emit('update:modelValue', props.modelValue.filter(item => item.key !== row.key))
}
</script>
<template>
  <section class="card space-y-5 rounded-2xl p-5 sm:p-6">
    <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">{{ t('seller_variants.title') }}</h2><button type="button" class="btn-secondary" :disabled="disabled || modelValue.length >= 200" @click="add"><Plus :size="16" />{{ t('seller_variants.add') }}</button></div>
    <p class="text-xs leading-relaxed text-muted">{{ t('seller_variants.help') }}</p>
    <fieldset v-for="(row, index) in modelValue" :key="row.key" :disabled="disabled" class="space-y-4 rounded-xl border border-border-gray p-4">
      <legend class="px-2 text-sm font-semibold">{{ t('seller_variants.option_number', { number: index + 1 }) }}</legend>
      <div class="grid gap-4 sm:grid-cols-2">
        <label><span class="label">{{ t('seller.name') }}</span><input v-model="row.name" class="input w-full" maxlength="255" :placeholder="t('seller_variants.name_example')" /></label>
        <label><span class="label">{{ t('seller.sku') }}</span><input v-model="row.sku" class="input w-full" maxlength="255" /></label>
        <SellerBarcodeField v-model="row.barcode" :disabled="disabled" :error="errors?.[`variants.${index}.barcode`]" />
        <label><span class="label">{{ t('seller.purchase_price') }} (USD)</span><input v-model.number="row.cost_price" type="number" min="0" max="99999999.99" step="0.01" class="input w-full" :aria-label="`${t('seller.purchase_price')} (USD)`" :aria-describedby="`variant-cost-help-${index}`" :aria-invalid="!!errors?.[`variants.${index}.cost_price`]" /><span :id="`variant-cost-help-${index}`" class="mt-1 block text-xs" :class="errors?.[`variants.${index}.cost_price`] ? 'text-red-600' : 'text-muted'">{{ errors?.[`variants.${index}.cost_price`] || t('seller.purchase_help') }}</span></label>
        <label><span class="label">{{ t('seller.price') }} (USD)</span><input v-model.number="row.price" required type="number" min="0" max="99999999.99" step="0.01" class="input w-full" /></label>
        <label><span class="label">{{ t('seller.original_price') }} (USD)</span><input v-model.number="row.compare_at_price" type="number" min="0" max="99999999.99" step="0.01" class="input w-full" /></label>
        <label><span class="label">{{ t(row.id ? 'seller.on_hand' : 'seller.initial_stock') }}</span><input v-model.number="row.quantity" required type="number" :min="row.reserved_quantity" max="1000000" step="1" class="input w-full" :aria-invalid="!!errors?.[`variants.${index}.quantity`]" /><span v-if="errors?.[`variants.${index}.quantity`]" role="alert" class="mt-1 block text-xs text-red-600">{{ errors[`variants.${index}.quantity`] }}</span><span v-if="row.reserved_quantity" class="mt-1 block text-xs text-muted">{{ t('seller.reserved') }}: {{ row.reserved_quantity }}</span></label>
        <label><span class="label">{{ t('seller_variants.photo') }}</span><select v-model="row.image_id" class="select w-full"><option :value="null">{{ t('seller.cover_photo') }}</option><option v-for="photo in photos.filter(item => item.key.startsWith('saved-'))" :key="photo.key" :value="Number(photo.key.substring(6))">{{ t('seller.photo_number', { number: photos.indexOf(photo) + 1 }) }}</option></select><span class="mt-1 block text-xs text-muted">{{ t('seller_variants.photo_help') }}</span></label>
      </div>
      <div v-for="(attribute, attrIndex) in row.attributes" :key="attrIndex" class="flex items-end gap-2">
        <label class="min-w-0 flex-1"><span class="label">{{ t('seller_variants.attribute') }}</span><input v-model="attribute.attribute" class="input w-full" maxlength="255" /></label>
        <label class="min-w-0 flex-1"><span class="label">{{ t('seller_variants.value') }}</span><input v-model="attribute.value" class="input w-full" maxlength="255" /></label>
        <button type="button" class="btn-icon mb-1 shrink-0 text-red-600" :aria-label="t('seller_variants.remove_attribute')" @click="row.attributes.splice(attrIndex, 1)"><Trash2 :size="16" /></button>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <button type="button" class="text-xs font-medium text-primary" @click="row.attributes.push({ attribute: '', value: '' })">{{ t('seller_variants.add_attribute') }}</button>
        <label class="flex items-center gap-2 text-sm"><input v-model="row.is_active" type="checkbox" />{{ t('seller.live') }}</label>
        <button v-if="modelValue.length > 1" type="button" class="text-xs text-red-600 disabled:opacity-40" :disabled="row.reserved_quantity > 0" @click="remove(row)">{{ t('actions.remove') }}</button>
      </div>
    </fieldset>
  </section>
</template>

