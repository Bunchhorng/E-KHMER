<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Plus,
  X,
  UploadCloud,
  ImagePlus,
  Star,
  RefreshCw,
  Save,
  Check,
  AlertTriangle
} from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { AdminBrand, AdminCategory, AdminProduct } from '@/api/admin'
import { mediaApi } from '@/api/uploads'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice } from '@/utils/format'

const { t } = useI18n()

const router = useRouter()
const route = useRoute()
const isEdit = computed(() => Boolean(route.params.id))

interface GeneratedVariant {
  id: string
  backendId?: number
  attributes: { name: string; value: string }[]
  sku: string
  price: number | null
  stock: number
  enabled: boolean
}

interface AttrRow {
  name: string
  values: string[]
}

interface ImageItem {
  id: number
  url: string
  name: string
  isMain: boolean
  file?: File
  savedPath?: string
}

const form = reactive({
  title: '',
  brand: '',
  category: '',
  sku: '',
  status: 'Active',
  description: '',
  price: 0,
  compareAt: 0,
  baseStock: 0,
  trackInventory: true
})

const errors = reactive<Record<string, string>>({})

const attributes = ref<AttrRow[]>([{ name: 'Color', values: ['Black', 'White'] }])
const variants = ref<GeneratedVariant[]>([])
const images = ref<ImageItem[]>([])

const brands = ref<AdminBrand[]>([])
const categories = ref<AdminCategory[]>([])

const brandNames = computed(() => brands.value.map((b) => b.name))
const categoryNames = computed(() => categories.value.map((c) => c.name))

const discountPct = computed(() => {
  const price = Number(form.price)
  const compare = Number(form.compareAt)
  if (compare > price && price > 0) return Math.round((1 - price / compare) * 100)
  return 0
})

function setAttrValues(attr: AttrRow, value: string) {
  attr.values = value
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean)
}

function addAttribute() {
  attributes.value.push({ name: '', values: [] })
}

function removeAttribute(index: number) {
  attributes.value.splice(index, 1)
}

function cartesianProduct<T>(arrays: T[][]): T[][] {
  return arrays.reduce<T[][]>(
    (acc, arr) => acc.flatMap((combo) => arr.map((item) => [...combo, item])),
    [[]]
  )
}

function generateVariants() {
  const usable = attributes.value.filter((a) => a.name.trim() !== '' && a.values.length > 0)
  const incomplete = attributes.value.length > 0 && usable.length !== attributes.value.length
  if (incomplete) {
    showToast(t('admin.products.define_attr_values'))
    return
  }
  if (attributes.value.length === 0) {
    variants.value = []
    return
  }

  const valueSets = usable.map((a) => ({
    name: a.name.trim(),
    values: Array.from(new Set(a.values.map((v) => v.trim()).filter(Boolean)))
  }))

  const names = valueSets.map((v) => v.name)
  const matrix = cartesianProduct(valueSets.map((v) => v.values))

  variants.value = matrix.map((combo, i) => ({
    id: `v-${Date.now()}-${i}`,
    attributes: combo.map((value, j) => ({ name: names[j], value })),
    sku: `SKU-${i + 1}`,
    price: null,
    stock: 0,
    enabled: true
  }))
  showToast(t('admin.products.toast_variants_generated', { count: variants.value.length }))
}

function removeVariant(index: number) {
  variants.value.splice(index, 1)
}

const dragOver = ref(false)
const objectUrls: string[] = []

function addFile(file: File) {
  if (!file.type.startsWith('image/')) return
  const url = URL.createObjectURL(file)
  objectUrls.push(url)
  images.value.push({ id: Date.now() + Math.random(), url, name: file.name, isMain: images.value.length === 0, file })
}

function onFiles(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  for (const file of Array.from(input.files)) addFile(file)
  input.value = ''
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  const files = e.dataTransfer?.files
  if (!files) return
  for (const file of Array.from(files)) addFile(file)
}

function pickFiles() {
  document.getElementById('image-input')?.click()
}

function setMain(id: number) {
  images.value.forEach((img) => (img.isMain = img.id === id))
}

function removeImage(id: number) {
  images.value = images.value.filter((img) => img.id !== id)
}

onBeforeUnmount(() => {
  for (const url of objectUrls) URL.revokeObjectURL(url)
})

function scrollToId(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

function validate(): boolean {
  errors.title = form.title.trim() ? '' : t('admin.products.error_title_required')
  // These fields are deliberately optional in AdminProductRequest. Requiring
  // them only in the browser meant older/simple products (and free products)
  // could never be edited, despite the API accepting the update.
  errors.price = Number.isFinite(Number(form.price)) && Number(form.price) >= 0
    ? ''
    : t('admin.products.error_price_required')
  errors.brand = ''
  errors.category = ''
  errors.images = ''

  if (variants.value.length) {
    const bad = variants.value.filter((v) => v.enabled && (!Number.isInteger(Number(v.stock)) || v.stock < 0))
    errors.variants = bad.length
      ? t('admin.products.error_variants', { count: bad.length })
      : ''
  } else {
    errors.variants = ''
  }

  return Object.values(errors).every((v) => v === '')
}

function loadForEdit(product: AdminProduct): void {
  form.title = product.name
  form.brand = product.brand?.name ?? ''
  form.category = product.category?.name ?? ''
  form.sku = product.sku
  form.status = product.is_active ? 'Active' : 'Draft'
  form.description = product.description ?? ''
  form.price = product.price
  form.compareAt = product.compare_at_price ?? 0
  form.baseStock = product.variants.reduce((sum, v) => sum + (v.available_quantity || 0), 0)

  attributes.value = product.attributes.length
    ? product.attributes.map((a) => ({ name: a.name, values: a.values.map((v) => v.value) }))
    : [{ name: '', values: [] }]

  variants.value = product.variants.map((v, i) => ({
    id: `v-${Date.now()}-${i}`,
    backendId: v.id,
    attributes: v.attributes.map((a) => ({ name: a.name, value: a.value })),
    sku: v.sku ?? '',
    price: v.price,
    stock: v.available_quantity,
    enabled: v.is_active
  }))

  images.value = product.gallery.map((g, i) => ({
    id: g.id,
    url: g.image_path,
    name: `Image ${i + 1}`,
    isMain: i === 0,
    savedPath: g.image_path
  }))
}

const saving = ref(false)

async function resolveImagePaths(): Promise<string[]> {
  const ordered = [...images.value].sort((a, b) => (a.isMain ? -1 : 0) - (b.isMain ? -1 : 0))
  const paths: string[] = []

  for (const img of ordered) {
    if (img.file) {
      const { data: resp } = await mediaApi.uploadImage(img.file, 'products')
      paths.push(resp.data.path)
    } else if (img.savedPath) {
      paths.push(img.savedPath)
    } else if (img.url) {
      paths.push(img.url)
    }
  }

  return paths
}

async function save() {
  const ok = validate()
  if (!ok) {
    if (errors.images) scrollToId('images')
    else if (errors.variants) scrollToId('variants')
    else scrollToId('basic')
    showToast(t('admin.products.fix_fields'))
    return
  }

  const selectedBrand = brands.value.find((b) => b.name === form.brand)
  const selectedCategory = categories.value.find((c) => c.name === form.category)
  const productId = Number(route.params.id)

  const payload: Record<string, unknown> = {
    name: form.title,
    description: form.description || null,
    price: form.price,
    compare_at_price: form.compareAt || null,
    sku: form.sku || null,
    is_active: form.status === 'Active',
    brand_id: selectedBrand?.id ?? null,
    category_id: selectedCategory?.id ?? null
  }

  try {
    saving.value = true
    const imagePaths = await resolveImagePaths()

    if (isEdit.value) {
      let variantsPayload: Array<Record<string, unknown>> = variants.value.map((v) => ({
        ...(v.backendId ? { id: v.backendId } : {}),
        name: v.attributes.map((a) => `${a.name}: ${a.value}`).join(', '),
        sku: v.sku,
        price: v.price ?? form.price,
        compare_at_price: form.compareAt || null,
        quantity: v.stock,
        is_active: v.enabled,
        ...(v.backendId
          ? {}
          : { attributes: v.attributes.map((a) => ({ attribute: a.name, value: a.value })) })
      }))

      // A simple product still needs one concrete inventory-bearing variant:
      // the cart and checkout intentionally transact against variants only.
      // Without this fallback, the visible base-stock field was discarded and
      // products saved without generated options could never be purchased.
      if (variantsPayload.length === 0) {
        variantsPayload = [{
          name: 'Default',
          sku: form.sku || null,
          price: form.price,
          compare_at_price: form.compareAt || null,
          quantity: form.baseStock,
          is_active: true,
          attributes: []
        }]
      }

      await adminApi.updateProduct(productId, { ...payload, variants: variantsPayload, images: imagePaths })
      showToast(t('admin.products.toast_updated', { title: form.title, price: formatPrice(form.price) }))
      router.push({ name: 'admin-products' })
      return
    }

    let variantsPayload: Array<Record<string, unknown>> = variants.value
      .filter((x) => x.enabled)
      .map((v) => ({
        name: v.attributes.map((a) => `${a.name}: ${a.value}`).join(', '),
        sku: v.sku,
        price: v.price ?? form.price,
        compare_at_price: form.compareAt || null,
        quantity: v.stock,
        is_active: true,
        attributes: v.attributes.map((a) => ({ attribute: a.name, value: a.value }))
      }))

    if (variantsPayload.length === 0) {
      variantsPayload = [{
        name: 'Default',
        sku: form.sku || null,
        price: form.price,
        compare_at_price: form.compareAt || null,
        quantity: form.baseStock,
        is_active: true,
        attributes: []
      }]
    }

    await adminApi.createProduct({
      ...payload,
      variants: variantsPayload,
      images: imagePaths
    })

    showToast(t('admin.products.toast_saved', { title: form.title, price: formatPrice(form.price) }))
    router.push({ name: 'admin-products' })
  } catch (error) {
    // Surface Laravel's 422 details (duplicate SKU, image ownership, etc.) so
    // an actionable API rejection is not mistaken for a broken update button.
    showToast(extractErrorMessage(error, t('admin.products.toast_save_error')))
  } finally {
    saving.value = false
  }
}

function saveDraft() {
  showToast(t('admin.products.toast_draft_saved'))
}

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

onMounted(async () => {
  try {
    const [brandsResp, catsResp] = await Promise.all([
      adminApi.listBrands(),
      adminApi.listCategories()
    ])
    brands.value = brandsResp.data.data
    categories.value = catsResp.data.data

    if (isEdit.value) {
      const id = Number(route.params.id)
      if (!id) {
        router.replace({ name: 'admin-products' })
        return
      }
      const { data: productResp } = await adminApi.getProduct(id)
      loadForEdit(productResp.data)
    }
  } catch {
    if (isEdit.value) {
      showToast(t('admin.products.toast_load_error'))
      router.replace({ name: 'admin-products' })
    }
  }
})
</script>

<template>
  <form class="space-y-6 pb-16" @submit.prevent="save()">
    <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="section-eyebrow">{{ $t('admin.products.catalog') }}</div>
        <h1 class="text-xl font-bold text-ink">{{ isEdit ? $t('admin.products.edit_product') : $t('admin.products.add_new_product') }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn-secondary btn-sm" type="button" @click="router.push({ name: 'admin-products' })">{{ $t('actions.cancel') }}</button>
        <button class="btn-ghost btn-sm" type="button" @click="saveDraft()">{{ $t('admin.products.save_draft') }}</button>
        <button class="btn-primary btn-sm" type="submit" :disabled="saving">
          <Save class="h-4 w-4" />
          {{ $t('admin.products.save_product') }}
        </button>
      </div>
    </div>

    <div id="basic" class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_1') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.basic_information') }}</h2>
      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="label" for="p-title">{{ $t('admin.products.title_label') }}</label>
          <input
            id="p-title"
            v-model="form.title"
            class="input"
            :class="{ 'input-error': errors.title }"
            :placeholder="$t('admin.products.title_placeholder')"
          />
          <p v-if="errors.title" class="mt-1 text-xs text-red-600">{{ errors.title }}</p>
        </div>

        <div>
          <label class="label" for="p-brand">{{ $t('admin.products.brand_label') }}</label>
          <select id="p-brand" v-model="form.brand" class="select" :class="{ 'input-error': errors.brand }">
            <option value="" disabled>{{ $t('admin.products.select_brand') }}</option>
            <option v-for="b in brandNames" :key="b" :value="b">{{ b }}</option>
          </select>
          <p v-if="errors.brand" class="mt-1 text-xs text-red-600">{{ errors.brand }}</p>
        </div>

        <div>
          <label class="label" for="p-category">{{ $t('admin.products.category_label') }}</label>
          <select id="p-category" v-model="form.category" class="select" :class="{ 'input-error': errors.category }">
            <option value="" disabled>{{ $t('admin.products.select_category') }}</option>
            <option v-for="c in categoryNames" :key="c" :value="c">{{ c }}</option>
          </select>
          <p v-if="errors.category" class="mt-1 text-xs text-red-600">{{ errors.category }}</p>
        </div>

        <div>
          <label class="label" for="p-sku">{{ $t('product.sku') }}</label>
          <input id="p-sku" v-model="form.sku" class="input" placeholder="AUTOGEN-001" />
        </div>

        <div>
          <label class="label" for="p-status">{{ $t('admin.products.status_label') }}</label>
          <select id="p-status" v-model="form.status" class="select">
            <option value="Active">{{ $t('status.active') }}</option>
            <option value="Draft">{{ $t('status.draft') }}</option>
            <option value="Archived">{{ $t('admin.products.status_archived') }}</option>
          </select>
        </div>

        <div class="sm:col-span-2">
          <label class="label" for="p-description">{{ $t('product.description') }}</label>
          <textarea
            id="p-description"
            v-model="form.description"
            class="textarea"
            rows="4"
            :placeholder="$t('admin.products.description_placeholder')"
          ></textarea>
        </div>
      </div>
    </div>

    <div id="pricing" class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_2') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.pricing_discount') }}</h2>
      <div class="mt-5 grid gap-4 sm:grid-cols-3">
        <div>
          <label class="label" for="p-price">{{ $t('admin.products.price_label') }}</label>
          <input id="p-price" v-model.number="form.price" class="input" type="number" min="0" step="0.01" placeholder="0.00" />
          <p v-if="errors.price" class="mt-1 text-xs text-red-600">{{ errors.price }}</p>
        </div>
        <div>
          <label class="label" for="p-compare">{{ $t('admin.products.compare_at_price') }}</label>
          <input id="p-compare" v-model.number="form.compareAt" class="input" type="number" min="0" step="0.01" placeholder="0.00" />
        </div>
        <div>
          <label class="label">{{ $t('admin.products.discount_label') }}</label>
          <div class="flex h-[42px] items-center">
            <span
              v-if="discountPct > 0"
              class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600"
            >
              {{ discountPct }}%
            </span>
            <span v-else class="text-sm text-gray-400"></span>
          </div>
        </div>
      </div>

      <div v-if="form.price > 0" class="mt-5 rounded-xl bg-canvas p-4">
        <div class="flex items-baseline gap-3">
          <span class="text-2xl font-bold text-ink">{{ formatPrice(form.price) }}</span>
          <span v-if="discountPct > 0" class="text-sm text-gray-400 line-through">{{ formatPrice(Number(form.compareAt)) }}</span>
          <span v-if="discountPct > 0" class="text-sm font-semibold text-red-600">{{ $t('admin.products.percent_off', { percent: discountPct }) }}</span>
        </div>
      </div>
    </div>

    <div id="variants" class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_3') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.variant_attributes') }}</h2>
      <p class="mt-1 text-sm text-gray-500">
        {{ $t('admin.products.variant_description') }}
      </p>

      <div class="mt-5 space-y-4">
        <div v-for="(attr, i) in attributes" :key="i" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
          <div>
            <label class="label" :for="`attr-name-${i}`">{{ $t('admin.products.attribute_name') }}</label>
            <input :id="`attr-name-${i}`" v-model="attr.name" class="input" :placeholder="$t('admin.products.attribute_name_placeholder')" />
          </div>
          <div>
            <label class="label" :for="`attr-values-${i}`">{{ $t('admin.products.attribute_values_label') }}</label>
            <input
              :id="`attr-values-${i}`"
              class="input"
              :placeholder="$t('admin.products.attribute_values_placeholder')"
              :value="attr.values.join(', ')"
              @input="setAttrValues(attr, ($event.target as HTMLInputElement).value)"
            />
          </div>
          <button class="btn-icon h-10 w-10 hover:text-red-600" type="button" :title="$t('admin.products.remove_attribute')" @click="removeAttribute(i)">
            <X class="h-4 w-4" />
          </button>
        </div>

        <button type="button" class="btn-secondary btn-sm mt-3" @click="addAttribute()">
          <Plus class="h-4 w-4" />
          {{ $t('admin.products.add_attribute') }}
        </button>
      </div>

      <div class="mt-6">
        <button type="button" class="btn-primary" @click="generateVariants()">
          <RefreshCw class="h-4 w-4" />
          {{ $t('admin.products.generate_variants') }}
        </button>
      </div>

      <div v-if="variants.length" class="mt-6">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-ink">{{ $t('admin.products.variants_generated', { count: variants.length }) }}</h3>
        </div>
        <div class="overflow-x-auto rounded-lg border border-border-gray">
          <table class="w-full text-sm">
            <thead class="bg-canvas text-left text-xs uppercase tracking-wide text-gray-500">
              <tr>
                <th class="px-3 py-2"></th>
                <th class="px-3 py-2">{{ $t('admin.products.attribute_combination') }}</th>
                <th class="px-3 py-2">{{ $t('product.sku') }}</th>
                <th class="px-3 py-2">{{ $t('admin.products.price_label') }}</th>
                <th class="px-3 py-2">{{ $t('admin.products.column_stock') }}</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border-gray">
              <tr v-for="(v, i) in variants" :key="v.id">
                <td class="px-3 py-2">
                  <input type="checkbox" v-model="v.enabled" class="h-4 w-4 accent-primary" />
                </td>
                <td class="px-3 py-2 font-medium text-ink">
                  {{ v.attributes.map((a) => a.value).join(' · ') }}
                </td>
                <td class="px-3 py-2">
                  <input v-model="v.sku" class="input px-2 py-1 text-xs" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="v.price" class="input px-2 py-1 text-xs" type="number" min="0" step="0.01" :placeholder="form.price ? String(form.price) : '0.00'" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="v.stock" class="input px-2 py-1 text-xs" type="number" min="0" />
                </td>
                <td class="px-3 py-2">
                  <button class="btn-icon h-8 w-8 hover:text-red-600" type="button" :title="$t('admin.products.remove_variant')" @click="removeVariant(i)">
                    <X class="h-4 w-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="errors.variants" class="mt-2 flex items-center gap-1 text-xs text-red-600">
          <AlertTriangle class="h-3.5 w-3.5" />
          {{ errors.variants }}
        </p>
      </div>
    </div>

    <div id="inventory" class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_4') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.inventory_stock') }}</h2>
      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="p-stock">{{ $t('admin.products.base_stock_label') }}</label>
          <input id="p-stock" v-model.number="form.baseStock" class="input" type="number" min="0" placeholder="0" />
        </div>
        <div>
          <label class="label">{{ $t('admin.products.track_inventory') }}</label>
          <button
            type="button"
            role="switch"
            :aria-checked="form.trackInventory"
            class="relative h-6 w-11 rounded-full transition-colors"
            :class="form.trackInventory ? 'bg-success' : 'bg-gray-200 dark:bg-surface-hover'"
            @click="form.trackInventory = !form.trackInventory"
          >
            <span
              class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all"
              :class="form.trackInventory ? 'left-[22px]' : 'left-0.5'"
            ></span>
          </button>
        </div>
      </div>
      <p class="mt-3 flex items-center gap-1 text-xs text-gray-500">
        <Check class="h-3.5 w-3.5 text-success" />
        {{ $t('admin.products.per_variant_stock_note') }}
      </p>
    </div>

    <div id="images" class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_5') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.images_title') }}</h2>

      <div
        class="mt-5 rounded-xl border-2 border-dashed p-10 text-center transition"
        :class="dragOver ? 'border-primary bg-primary/5' : 'border-gray-300'"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop"
      >
        <UploadCloud class="mx-auto h-10 w-10 text-gray-400" />
        <p class="mt-3 text-sm font-medium text-ink">{{ $t('admin.products.drag_drop_images') }}</p>
        <p class="mt-1 text-xs text-gray-400">{{ $t('admin.products.image_format_note') }}</p>
        <input id="image-input" type="file" accept="image/*" multiple class="hidden" @change="onFiles" />
        <div class="mt-4 flex flex-wrap justify-center gap-2">
          <button class="btn-secondary btn-sm" type="button" @click="pickFiles()">
            <ImagePlus class="h-4 w-4" />
            {{ $t('admin.products.browse_files') }}
          </button>
        </div>
      </div>
      <p v-if="errors.images" class="mt-2 text-xs text-red-600">{{ errors.images }}</p>

      <div v-if="images.length" class="mt-4 grid grid-cols-4 gap-3">
        <div v-for="img in images" :key="img.id" class="group relative">
          <img :src="img.url" :alt="img.name" class="aspect-square w-full rounded-lg object-cover" />
          <button
            class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-white shadow hover:text-red-600"
            type="button"
            :title="$t('admin.products.remove_image')"
            @click="removeImage(img.id)"
          >
            <X class="h-3.5 w-3.5" />
          </button>
          <button
            class="absolute bottom-1 left-1 flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
            :class="img.isMain ? 'bg-accent text-ink dark:text-gray-900' : 'bg-white/90 text-gray-500 hover:text-ink'"
            type="button"
            @click="setMain(img.id)"
          >
            <Star v-if="img.isMain" class="h-3 w-3 fill-current" />
            <Star v-else class="h-3 w-3" />
            {{ img.isMain ? $t('admin.products.main') : $t('admin.products.set_main') }}
          </button>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <button class="btn-secondary" type="button" @click="router.push({ name: 'admin-products' })">{{ $t('actions.cancel') }}</button>
      <button class="btn-primary" type="submit" :disabled="saving">
        <Save class="h-4 w-4" />
        {{ $t('admin.products.save_product') }}
      </button>
    </div>

    <transition name="fade">
      <div
        v-if="toast"
        class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover"
      >
        {{ toast }}
      </div>
    </transition>
  </form>
</template>
