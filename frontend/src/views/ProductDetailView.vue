<script setup lang="ts">
import { computed, ref, reactive, watch, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  ZoomIn,
  Heart,
  ShoppingCart,
  Truck,
  RefreshCw,
  ShieldCheck,
  CreditCard,
  BadgeCheck,
  Zap,
  Loader2
} from 'lucide-vue-next'
import StarRating from '@/components/StarRating.vue'
import QuantityCounter from '@/components/QuantityCounter.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import StatusTag from '@/components/StatusTag.vue'
import EmptyState from '@/components/EmptyState.vue'
import { catalogApi } from '@/api/catalog'
import type { ProductDetail } from '@/api/catalog'
import { reviewsApi } from '@/api/reviews'
import type { ApiReview } from '@/api/reviews'
import type { Product, ProductVariant, ProductImage, Specification } from '@/types'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { useUiStore } from '@/stores/ui'
import { useI18n } from 'vue-i18n'
import { formatPrice, formatDate } from '@/utils/format'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const wishlist = useWishlistStore()
const ui = useUiStore()
const auth = useAuthStore()

const loading = ref(true)
const error = ref<string | null>(null)
const rawProduct = ref<ProductDetail | null>(null)
const reviewsData = ref<ApiReview[]>([])
const reviewsLoading = ref(false)

function mapToProduct(api: ProductDetail): Product {
  const defaultVariant = api.variants.find((v) => v.is_default) ?? api.variants[0]
  const totalStock = api.variants.reduce((sum, v) => sum + v.available_quantity, 0)

  const images: ProductImage[] = [
    { id: String(api.id), url: api.cover_image ?? '', alt: api.name },
    ...api.gallery.map((g) => ({ id: String(g.id), url: g.image_path, alt: g.alt_text ?? '' }))
  ]

  const specifications: Specification[] = api.attributes.map((a) => ({
    label: a.name,
    value: a.values.map((v) => v.value).join(', ')
  }))

  const colorAttr = api.attributes.find((a) => a.name === 'Color' || a.type === 'color')
  const colors: string[] = colorAttr
    ? colorAttr.values.map((v) => v.swatch_color ?? '#CBD5E1')
    : []

  const sizeAttr = api.attributes.find((a) => a.name === 'Size' || a.type === 'size')
  const sizes: string[] = sizeAttr ? sizeAttr.values.map((v) => v.value) : []

  return {
    id: String(api.id),
    slug: api.slug,
    title: api.name,
    brand: { id: api.brand?.slug ?? '', name: api.brand?.name ?? '' },
    category: { id: api.category?.slug ?? '', name: api.category?.name ?? '', slug: api.category?.slug ?? '' },
    sku: api.sku,
    description: api.description ?? '',
    specifications,
    price: api.price,
    compareAtPrice: api.compare_at_price,
    discountPercent:
      api.compare_at_price && api.compare_at_price > api.price
        ? Math.round(((api.compare_at_price - api.price) / api.compare_at_price) * 100)
        : null,
    rating: api.rating_avg,
    reviewCount: api.rating_count,
    images,
    variants: api.variants.map((v): ProductVariant => ({
      id: String(v.id),
      sku: v.sku,
      attributes: v.attributes.map((a) => ({ name: a.name, value: a.value })),
      price: v.price,
      compareAtPrice: v.compare_at_price,
      stockQuantity: v.available_quantity,
      isInStock: v.in_stock,
      imageId: null
    })),
    stockQuantity: defaultVariant?.available_quantity ?? totalStock,
    isInStock: api.in_stock,
    isNew: false,
    isBestSeller: false,
    isFeatured: api.is_featured,
    colors,
    sizes
  }
}

function mapToReview(api: ApiReview) {
  return {
    id: String(api.id),
    productId: String(api.product?.id ?? ''),
    author: api.user.name,
    rating: api.rating,
    title: api.title ?? '',
    body: api.body ?? '',
    date: api.created_at,
    verified: api.verified,
    status: api.status as 'approved' | 'pending' | 'rejected'
  }
}

const product = computed(() => (rawProduct.value ? mapToProduct(rawProduct.value) : null))

const selectedImage = ref<ProductImage | null>(null)
const quantity = ref(1)
const selectedOptions = reactive<Record<string, string>>({})
const zoomActive = ref(false)
const zoomX = ref(50)
const zoomY = ref(50)
const lightboxOpen = ref(false)
const activeTab = ref('description')
const writeReviewOpen = ref(false)
const writeReviewError = ref('')
const reviewSubmitted = ref(false)
const reviewRating = ref(5)
const reviewTitle = ref('')
const reviewBody = ref('')
const reviewSubmitting = ref(false)

function openWriteReview() {
  writeReviewError.value = ''
  reviewSubmitted.value = false
  if (!auth.isAuthenticated) return
  writeReviewOpen.value = !writeReviewOpen.value
}

async function submitReview() {
  if (!product.value || reviewSubmitting.value) return
  reviewSubmitting.value = true
  writeReviewError.value = ''
  try {
    await reviewsApi.create({
      product_id: Number(product.value.id),
      rating: reviewRating.value,
      title: reviewTitle.value.trim() || undefined,
      body: reviewBody.value.trim() || undefined
    })
    reviewSubmitted.value = true
    writeReviewOpen.value = false
    reviewTitle.value = ''
    reviewBody.value = ''
    reviewRating.value = 5
  } catch (e: unknown) {
    const err = e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
    const errorKey = Object.keys(err.response?.data?.errors ?? {})[0]
    writeReviewError.value =
      (errorKey ? err.response?.data?.errors?.[errorKey]?.[0] : undefined) ??
      err.response?.data?.message ??
      t('product.review_submit_error')
  } finally {
    reviewSubmitting.value = false
  }
}

const TABS = [
  { key: 'description', labelKey: 'product.description' },
  { key: 'specifications', labelKey: 'product.specifications' },
  { key: 'shipping', labelKey: 'product.shipping_returns' },
  { key: 'reviews', labelKey: 'product.reviews' }
]

function resetProduct(p: Product | null) {
  for (const key of Object.keys(selectedOptions)) {
    delete selectedOptions[key]
  }
  if (!p) return
  selectedImage.value = p.images[0] ?? null
  quantity.value = 1
  const first = p.variants.find((v) => v.isInStock) ?? p.variants[0]
  if (first) {
    for (const a of first.attributes) selectedOptions[a.name] = a.value
  }
}

watch(
  () => route.params.slug,
  () => {
    fetchProduct()
  }
)

async function fetchProduct() {
  const slug = String(route.params.slug ?? '')
  if (!slug) return

  loading.value = true
  error.value = null
  rawProduct.value = null
  reviewsData.value = []

  try {
    const { data: productRes } = await catalogApi.getProduct(slug)
    rawProduct.value = productRes.data
    resetProduct(product.value)

    if (product.value) {
      reviewsLoading.value = true
      try {
        const { data: reviewsRes } = await reviewsApi.getProductReviews(Number(product.value.id))
        reviewsData.value = reviewsRes.data
      } catch {
        reviewsData.value = []
      } finally {
        reviewsLoading.value = false
      }
    }
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Failed to load product'
    rawProduct.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchProduct()
})

const mainImage = computed(() => selectedImage.value ?? product.value?.images[0] ?? null)

const attributeGroups = computed<{ name: string; values: { name: string; value: string }[] }[]>(() => {
  const p = product.value
  if (!p) return []
  const map = new Map<string, { name: string; value: string }[]>()
  for (const v of p.variants) {
    for (const a of v.attributes) {
      const arr = map.get(a.name) ?? []
      if (!arr.some((x) => x.value === a.value)) arr.push({ name: a.name, value: a.value })
      map.set(a.name, arr)
    }
  }
  return Array.from(map.entries()).map(([name, values]) => ({ name, values }))
})

const selectedVariant = computed(() => {
  const p = product.value
  if (!p) return undefined
  return p.variants.find((v) => v.attributes.every((a) => selectedOptions[a.name] === a.value))
})

const hasCombination = computed(() => {
  const p = product.value
  if (!p) return true
  if (!p.variants.length) return true
  return Boolean(
    p.variants.some((v) => v.attributes.every((a) => selectedOptions[a.name] === a.value))
  )
})

function optionAvailable(name: string, value: string): boolean {
  const p = product.value
  if (!p) return true
  const candidate = { ...selectedOptions, [name]: value }
  return p.variants.some((v) => v.attributes.every((a) => candidate[a.name] === a.value))
}

function selectOption(name: string, value: string) {
  if (!optionAvailable(name, value)) return
  selectedOptions[name] = value
}

const swatchColorMap = computed(() => {
  const map = new Map<string, string>()
  const attrs = rawProduct.value?.attributes ?? []
  const colorAttr = attrs.find((a) => a.name === 'Color' || a.type === 'color')
  if (colorAttr) {
    for (const v of colorAttr.values) {
      map.set(v.value, v.swatch_color ?? '#CBD5E1')
    }
  }
  return map
})

function colorHex(value: string): string {
  return swatchColorMap.value.get(value) ?? '#CBD5E1'
}

const currentPrice = computed(() => selectedVariant.value?.price ?? product.value?.price ?? 0)

const currentCompareAt = computed(() => {
  if (selectedVariant.value?.compareAtPrice) return selectedVariant.value.compareAtPrice
  return product.value?.compareAtPrice ?? null
})

const currentStock = computed(() => selectedVariant.value?.stockQuantity ?? product.value?.stockQuantity ?? 0)

const discountPercent = computed(() => {
  if (currentCompareAt.value && currentCompareAt.value > currentPrice.value) {
    return Math.round(((currentCompareAt.value - currentPrice.value) / currentCompareAt.value) * 100)
  }
  return product.value?.discountPercent ?? null
})

const saveAmount = computed(() =>
  currentCompareAt.value && currentCompareAt.value > currentPrice.value
    ? currentCompareAt.value - currentPrice.value
    : 0
)

const stockStatus = computed<'out' | 'low' | 'in'>(() => {
  const p = product.value
  if (!p) return 'out'
  const variantOut = selectedVariant.value && !selectedVariant.value.isInStock
  if (variantOut || !p.isInStock) return 'out'
  if (currentStock.value <= 5) return 'low'
  return 'in'
})

const stockLabel = computed(() => {
  switch (stockStatus.value) {
    case 'out':
      return t('product.out_of_stock')
    case 'low':
      return t('product.only_left', { count: currentStock.value })
    default:
      return t('product.in_stock')
  }
})

const zoomStyle = computed(() => ({
  transform: `scale(${zoomActive.value ? 1.75 : 1})`,
  transformOrigin: `${zoomX.value}% ${zoomY.value}%`
}))

function onZoomMove(e: MouseEvent) {
  const el = e.currentTarget as HTMLElement
  const rect = el.getBoundingClientRect()
  zoomX.value = ((e.clientX - rect.left) / rect.width) * 100
  zoomY.value = ((e.clientY - rect.top) / rect.height) * 100
}

function openLightbox() {
  if (mainImage.value) lightboxOpen.value = true
}

function addToCart() {
  const p = product.value
  if (!p || !selectedVariant.value?.id) return
  cart.addItem({ variantId: selectedVariant.value.id, quantity: quantity.value })
  ui.openCartDrawer()
}

function buyNow() {
  const p = product.value
  if (!p || !selectedVariant.value?.id) return
  cart.addItem({ variantId: selectedVariant.value.id, quantity: quantity.value })
  router.push('/checkout')
}

function toggleWishlist() {
  const p = product.value
  if (p) wishlist.toggle(p.id)
}

function goToTab(key: string) {
  activeTab.value = key
}

watch(selectedVariant, () => {
  if (quantity.value > currentStock.value) quantity.value = Math.max(1, currentStock.value)
})

const reviews = computed(() => reviewsData.value.map(mapToReview))

const ratingBreakdown = computed(() => {
  const buckets = [5, 4, 3, 2, 1].map((star) => ({
    star,
    count: reviews.value.filter((r) => r.rating === star).length
  }))
  return buckets
})

const reviewTotal = computed(() => ratingBreakdown.value.reduce((sum, b) => sum + b.count, 0))

function bucketPercent(count: number): number {
  if (!reviewTotal.value) return 0
  return Math.round((count / reviewTotal.value) * 100)
}

const featureList = computed(() => {
  const p = product.value
  if (!p) return []
  return p.description
    .split('.')
    .map((s) => s.trim())
    .filter(Boolean)
    .slice(0, 4)
})
</script>

<template>
  <div class="container-app py-6">
    <template v-if="loading">
      <div class="flex min-h-[400px] items-center justify-center">
        <Loader2 class="h-8 w-8 animate-spin text-primary" />
      </div>
    </template>

    <template v-else-if="error">
      <EmptyState
        :title="$t('product.not_found_title')"
        :description="error"
        :cta-label="$t('product.back_to_shop')"
        @cta="router.push('/shop')"
      />
    </template>

    <template v-else-if="product">
      <nav class="flex items-center gap-1 text-xs text-gray-500 dark:text-muted dark:text-gray-500">
        <RouterLink to="/" class="hover:text-primary">{{ $t('nav.home') }}</RouterLink>
        <span>/</span>
        <span>{{ product.category.name }}</span>
        <span>/</span>
        <span class="truncate text-ink dark:text-ink">{{ product.title }}</span>
      </nav>

      <div class="mt-6 grid gap-10 lg:grid-cols-2">
        <div>
          <div
            class="group relative overflow-hidden rounded-2xl border border-border-gray bg-white dark:border-border-gray dark:bg-surface"
            @mouseenter="zoomActive = true"
            @mouseleave="zoomActive = false"
            @mousemove="onZoomMove"
          >
            <button class="absolute inset-0 z-10 cursor-zoom-in" :aria-label="$t('product.zoom_image')" @click="openLightbox"></button>
            <img
              :src="mainImage?.url"
              :alt="mainImage?.alt ?? product.title"
              class="aspect-square w-full object-cover transition-transform duration-150"
              :style="zoomStyle"
            />
            <span v-if="zoomActive" class="absolute right-3 top-3 z-20 rounded-full bg-white/80 p-2 text-ink dark:text-ink dark:bg-surface-hover/80">
              <ZoomIn class="h-5 w-5" />
            </span>
            <span v-if="discountPercent" class="absolute left-4 top-4 z-20 rounded-lg bg-accent px-2.5 py-1 text-sm font-bold text-ink dark:text-gray-900">-{{ discountPercent }}%</span>
          </div>

          <div class="mt-4 grid grid-cols-4 gap-3">
            <button
              v-for="img in product.images"
              :key="img.id"
              type="button"
              class="aspect-square overflow-hidden rounded-lg border-2 transition"
              :class="selectedImage?.id === img.id ? 'border-primary' : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600'"
              @click="selectedImage = img"
            >
              <img :src="img.url" :alt="img.alt ?? product.title" class="h-full w-full object-cover" />
            </button>
          </div>
        </div>

        <div>
          <div class="text-sm font-semibold uppercase tracking-wide text-primary">{{ product.brand.name }}</div>
          <h1 class="mt-1 text-2xl font-bold text-ink dark:text-ink lg:text-3xl">{{ product.title }}</h1>
          <div v-if="rawProduct?.shop" class="mt-3 flex items-center gap-2 text-sm text-gray-500 dark:text-muted"><img v-if="rawProduct.shop.logo" :src="rawProduct.shop.logo" :alt="rawProduct.shop.name" class="h-7 w-7 rounded-lg border border-border-gray object-contain" /><span>{{ $t('marketplace.sold_by', { shop: rawProduct.shop.name }) }}</span></div>

          <div class="mt-2 text-xs text-gray-500 dark:text-muted dark:text-gray-500">{{ $t('product.sku') }}: {{ selectedVariant?.sku ?? product.sku }}</div>

          <div class="mt-3 flex items-center gap-2 text-sm">
            <StarRating :value="product.rating" :show-value="true" />
            <span class="text-xs text-gray-500 dark:text-muted dark:text-gray-500">({{ $t('product.review_count', { count: product.reviewCount }) }})</span>
            <button class="text-xs font-medium text-primary hover:underline" @click="goToTab('reviews')">{{ $t('product.write_a_review') }}</button>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="text-3xl font-extrabold text-ink dark:text-ink">{{ formatPrice(currentPrice) }}</span>
            <span v-if="currentCompareAt" class="text-lg text-gray-400 dark:text-gray-500 line-through">{{ formatPrice(currentCompareAt) }}</span>
            <span v-if="saveAmount" class="rounded-md bg-accent/20 px-2 py-0.5 text-sm font-semibold text-ink dark:text-ink">{{ $t('product.you_save', { amount: formatPrice(saveAmount) }) }}</span>
          </div>

          <div class="mt-3 flex items-center gap-2">
            <StatusTag :status="stockLabel" />
            <span class="text-xs text-gray-500 dark:text-muted dark:text-gray-500">{{ $t('product.available', { count: currentStock }) }}</span>
          </div>

          <template v-for="group in attributeGroups" :key="group.name">
            <div class="mt-6">
              <div class="mb-2 text-sm font-semibold text-ink dark:text-ink">{{ group.name }}</div>

              <template v-if="group.name === 'Color'">
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="v in group.values"
                    :key="v.value"
                    type="button"
                    :disabled="!optionAvailable(group.name, v.value)"
                    :title="v.value"
                    class="h-9 w-9 rounded-full transition"
                    :class="[
                      !optionAvailable(group.name, v.value) && 'cursor-not-allowed opacity-40',
                      selectedOptions[group.name] === v.value
                        ? 'ring-2 ring-primary ring-offset-2'
                        : 'ring-1 ring-gray-300 dark:ring-gray-600'
                    ]"
                    :style="{ backgroundColor: colorHex(v.value) }"
                    @click="selectOption(group.name, v.value)"
                  ></button>
                </div>
              </template>

              <template v-else>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="v in group.values"
                    :key="v.value"
                    type="button"
                    :disabled="!optionAvailable(group.name, v.value)"
                    class="rounded-lg border px-4 py-2 text-sm transition"
                    :class="[
                      !optionAvailable(group.name, v.value) && 'cursor-not-allowed opacity-40',
                      selectedOptions[group.name] === v.value
                        ? 'border-primary bg-primary/5 text-primary'
                        : 'border-border-gray text-ink dark:text-ink hover:border-gray-300 dark:border-border-gray dark:hover:border-gray-500'
                    ]"
                    @click="selectOption(group.name, v.value)"
                  >
                    {{ v.value }}
                  </button>
                </div>
              </template>
            </div>
          </template>

          <div v-if="!hasCombination" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-600">
            {{ $t('product.no_combination') }}
          </div>

          <div class="mt-6 flex flex-wrap items-center gap-3">
            <QuantityCounter v-model="quantity" :max="currentStock" />
            <button
              class="btn-primary btn-lg flex-1"
              :disabled="stockStatus === 'out' || !hasCombination"
              @click="addToCart"
            >
              <ShoppingCart class="h-5 w-5" />
              {{ $t('actions.add_to_cart') }}
            </button>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-3">
            <button
              class="btn-secondary btn-lg flex-1"
              :disabled="stockStatus === 'out' || !hasCombination"
              @click="buyNow"
            >
              {{ $t('actions.buy_now') }}
            </button>
            <button
              type="button"
              class="btn-secondary btn-lg px-4"
              :aria-label="$t('product.toggle_wishlist')"
              @click="toggleWishlist"
            >
              <Heart
                class="h-5 w-5"
                :class="wishlist.isWishlisted(product.id) ? 'fill-red-500 text-red-500' : ''"
              />
            </button>
          </div>

          <div class="mt-8 grid grid-cols-2 gap-3">
            <div class="flex items-center gap-3 rounded-lg bg-canvas p-3 dark:bg-canvas">
              <Truck class="h-5 w-5 text-primary" />
              <div class="text-xs text-gray-600 dark:text-muted">
                <div class="font-semibold text-ink dark:text-ink">{{ $t('home.benefit_shipping_title') }}</div>
                {{ $t('product.shipping_over_100') }}
              </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg bg-canvas p-3 dark:bg-canvas">
              <RefreshCw class="h-5 w-5 text-primary" />
              <div class="text-xs text-gray-600 dark:text-muted">
                <div class="font-semibold text-ink dark:text-ink">{{ $t('product.returns_30_days') }}</div>
                {{ $t('product.no_questions_asked') }}
              </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg bg-canvas p-3 dark:bg-canvas">
              <ShieldCheck class="h-5 w-5 text-primary" />
              <div class="text-xs text-gray-600 dark:text-muted">
                <div class="font-semibold text-ink dark:text-ink">{{ $t('product.warranty_2_years') }}</div>
                {{ $t('product.full_coverage') }}
              </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg bg-canvas p-3 dark:bg-canvas">
              <CreditCard class="h-5 w-5 text-primary" />
              <div class="text-xs text-gray-600 dark:text-muted">
                <div class="font-semibold text-ink dark:text-ink">{{ $t('product.secure_payment') }}</div>
                {{ $t('product.ssl_encrypted') }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-12 border-b border-border-gray dark:border-border-gray">
        <div class="flex flex-wrap gap-6">
          <button
            v-for="tab in TABS"
            :key="tab.key"
            type="button"
            class="border-b-2 pb-3 text-sm font-semibold transition"
            :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-gray-500 dark:text-muted hover:text-ink dark:hover:text-ink'"
            @click="goToTab(tab.key)"
          >
            {{ $t(tab.labelKey) }}
          </button>
        </div>
      </div>

      <div class="py-8">
        <div v-if="activeTab === 'description'">
          <p class="text-gray-600 dark:text-muted leading-relaxed">{{ product.description }}</p>
          <div class="mt-6">
            <h3 class="text-lg font-bold text-ink dark:text-ink">{{ $t('product.key_features') }}</h3>
            <ul class="mt-3 space-y-2">
              <li v-for="(f, i) in featureList" :key="i" class="flex items-start gap-2 text-sm text-gray-600 dark:text-muted">
                <span class="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-primary"></span>
                {{ f }}
              </li>
            </ul>
          </div>
        </div>

        <div v-else-if="activeTab === 'specifications'">
          <table class="w-full border-collapse overflow-hidden rounded-xl border border-border-gray text-sm dark:border-border-gray">
            <tbody>
              <tr v-for="(spec, i) in product.specifications" :key="spec.label" :class="i % 2 ? 'bg-white dark:bg-surface' : 'bg-canvas dark:bg-canvas'">
                <td class="border border-border-gray px-4 py-3 font-semibold text-ink dark:text-ink dark:border-border-gray">{{ spec.label }}</td>
                <td class="border border-border-gray px-4 py-3 text-gray-600 dark:text-muted dark:border-border-gray">{{ spec.value }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else-if="activeTab === 'shipping'" class="grid gap-4 md:grid-cols-3">
          <div class="card p-5">
            <Truck class="h-6 w-6 text-primary" />
            <div class="mt-3 font-semibold text-ink dark:text-ink">{{ $t('product.standard') }}</div>
            <p class="mt-1 text-sm text-gray-500 dark:text-muted dark:text-gray-500">{{ $t('product.standard_desc') }}</p>
          </div>
          <div class="card p-5">
            <Zap class="h-6 w-6 text-accent" />
            <div class="mt-3 font-semibold text-ink dark:text-ink">{{ $t('product.express') }}</div>
            <p class="mt-1 text-sm text-gray-500 dark:text-muted dark:text-gray-500">{{ $t('product.express_desc') }}</p>
          </div>
          <div class="card p-5">
            <RefreshCw class="h-6 w-6 text-primary" />
            <div class="mt-3 font-semibold text-ink dark:text-ink">{{ $t('product.returns') }}</div>
            <p class="mt-1 text-sm text-gray-500 dark:text-muted dark:text-gray-500">{{ $t('product.returns_desc') }}</p>
          </div>
        </div>

        <div v-else-if="activeTab === 'reviews'">
          <div class="grid gap-8 lg:grid-cols-3">
            <div class="card h-fit p-6">
              <div class="text-4xl font-extrabold text-ink dark:text-ink">{{ product.rating }}</div>
              <div class="mt-2">
                <StarRating :value="product.rating" />
              </div>
              <div class="mt-1 text-sm text-gray-500 dark:text-muted dark:text-gray-500">{{ $t('product.based_on_reviews', { count: product.reviewCount }) }}</div>
              <div class="mt-5 space-y-2">
                <div v-for="b in ratingBreakdown" :key="b.star" class="flex items-center gap-2">
                  <span class="w-3 text-xs text-gray-500 dark:text-muted dark:text-gray-500">{{ b.star }}</span>
                  <div class="h-2 flex-1 overflow-hidden rounded-full bg-canvas dark:bg-canvas">
                    <div class="h-full rounded-full bg-accent" :style="{ width: bucketPercent(b.count) + '%' }"></div>
                  </div>
                </div>
              </div>
              <button class="btn-outline mt-6 w-full" @click="openWriteReview">{{ $t('product.write_a_review') }}</button>
            </div>

            <div class="lg:col-span-2">
              <h3 class="text-lg font-bold text-ink dark:text-ink">{{ $t('product.verified_reviews') }}</h3>

              <div v-if="!auth.isAuthenticated" class="mt-2 rounded-lg bg-canvas px-4 py-3 text-xs text-gray-600 dark:text-muted dark:bg-canvas">
                {{ $t('product.sign_in_review') }}
                <RouterLink to="/auth/login" class="ml-1 font-semibold text-primary hover:underline">
                  {{ $t('nav.sign_in') }}
                </RouterLink>
              </div>

              <div v-else-if="writeReviewOpen" class="mt-4 rounded-xl border border-border-gray p-4 dark:border-border-gray">
                <div class="mb-3 flex items-center justify-between">
                  <span class="text-sm font-semibold text-ink dark:text-ink">{{ $t('product.your_review') }}</span>
                  <button type="button" class="btn-ghost btn-sm" @click="writeReviewOpen = false">{{ $t('actions.close') }}</button>
                </div>
                <div class="mb-3 flex items-center gap-1">
                  <button
                    v-for="star in 5"
                    :key="star"
                    type="button"
                    class="text-2xl leading-none transition-colors"
                    :class="star <= reviewRating ? 'text-accent' : 'text-gray-300 dark:text-gray-600'"
                    :aria-label="`${star} star${star > 1 ? 's' : ''}`"
                    @click="reviewRating = star"
                  >
                    ★
                  </button>
                </div>
                <input
                  v-model="reviewTitle"
                  type="text"
                  class="input mb-3"
                  :placeholder="$t('product.review_title_placeholder')"
                />
                <textarea
                  v-model="reviewBody"
                  class="textarea mb-3"
                  rows="4"
                  :placeholder="$t('product.review_body_placeholder')"
                ></textarea>
                <p v-if="writeReviewError" class="mb-3 text-xs font-medium text-red-500">{{ writeReviewError }}</p>
                <button type="button" class="btn-primary" :disabled="reviewSubmitting" @click="submitReview">
                  {{ reviewSubmitting ? $t('product.review_submitting') : $t('product.review_submit') }}
                </button>
              </div>

              <p v-else-if="reviewSubmitted" class="mt-2 rounded-lg bg-emerald-100 px-3 py-2 text-xs font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                {{ $t('product.review_pending_note') }}
              </p>

              <div v-if="reviewsLoading" class="mt-5 flex justify-center py-8">
                <Loader2 class="h-6 w-6 animate-spin text-primary" />
              </div>

              <div v-else-if="reviews.length" class="mt-5 space-y-5">
                <div v-for="r in reviews" :key="r.id" class="card p-5">
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                      <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                        {{ r.author.charAt(0).toUpperCase() }}
                      </div>
                      <div>
                        <div class="flex items-center gap-1.5 text-sm font-semibold text-ink dark:text-ink">
                          {{ r.author }}
                          <BadgeCheck v-if="r.verified" class="h-4 w-4 text-primary" />
                        </div>
                        <div class="flex items-center gap-2">
                          <StarRating :value="r.rating" size="sm" />
                          <span class="text-xs text-gray-400 dark:text-gray-500">{{ formatDate(r.date) }}</span>
                        </div>
                      </div>
                    </div>
                    <BaseBadge v-if="!r.verified" variant="neutral">{{ $t('product.unverified_purchase') }}</BaseBadge>
                  </div>
                  <div class="mt-3">
                    <div class="font-semibold text-ink dark:text-ink">{{ r.title }}</div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-muted">{{ r.body }}</p>
                  </div>
                </div>
              </div>

              <div v-else class="mt-5">
                <EmptyState
                  :title="$t('product.no_reviews_title')"
                  :description="$t('product.no_reviews_description')"
                  :cta-label="$t('product.write_a_review')"
                  @cta="openWriteReview"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template v-else>
      <EmptyState
        :title="$t('product.not_found_title')"
        :description="$t('product.not_found_description')"
        :cta-label="$t('product.back_to_shop')"
        @cta="router.push('/shop')"
      />
    </template>

    <Teleport to="body">
      <div v-if="lightboxOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-6" @click="lightboxOpen = false">
        <img :src="mainImage?.url" :alt="mainImage?.alt ?? ''" class="max-h-full max-w-full rounded-xl object-contain" />
      </div>
    </Teleport>
  </div>
</template>
