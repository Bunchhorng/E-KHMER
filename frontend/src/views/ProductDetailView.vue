<script setup lang="ts">
import { computed, ref, reactive, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  ZoomIn,
  Heart,
  ShoppingCart,
  Truck,
  RefreshCw,
  BadgeCheck,
  MessageSquareText
} from 'lucide-vue-next'
import StarRating from '@/components/StarRating.vue'
import ProductDescription from '@/components/ProductDescription.vue'
import { productDescriptionText } from '@/utils/productDescription'
import QuantityCounter from '@/components/QuantityCounter.vue'
import StatusTag from '@/components/StatusTag.vue'
import EmptyState from '@/components/EmptyState.vue'
import AppImage from '@/components/AppImage.vue'
import BaseModal from '@/components/BaseModal.vue'
import PdpSkeleton from '@/components/PdpSkeleton.vue'
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
const reviewsError = ref(false)
let reviewRequest = 0

function mapToProduct(api: ProductDetail): Product {
  const defaultVariant = api.variants.find((v) => v.is_default) ?? api.variants[0]
  const totalStock = api.variants.reduce((sum, v) => sum + v.available_quantity, 0)

  const candidates: ProductImage[] = [
    { id: `cover-${api.id}`, url: api.cover_image ?? '', alt: api.name },
    ...api.gallery.map((g) => ({ id: String(g.id), url: g.image_path, alt: g.alt_text ?? '' }))
  ]
  const seen = new Set<string>()
  const images = candidates.filter(image => {
    if (!image.url || seen.has(image.url)) return false
    seen.add(image.url)
    return true
  })

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
      imageId: v.image_id ? String(v.image_id) : null
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
const pendingCartAction = ref<'add' | 'buy' | null>(null)
const cartActionError = ref('')
const checkoutNavigationFailed = ref(false)
let active = true
onBeforeUnmount(() => { active = false; reviewRequest++ })
const selectedOptions = reactive<Record<string, string>>({})
const zoomActive = ref(false)
const zoomX = ref(50)
const zoomY = ref(50)
const lightboxOpen = ref(false)
const detailsRef = ref<HTMLElement | null>(null)
const editingReview = ref(false)
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
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
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
  reviewRequest++
  reviewsError.value = false
  reviewsLoading.value = false
  writeReviewOpen.value = false
  reviewSubmitted.value = false
  writeReviewError.value = ''
  lightboxOpen.value = false
  activeTab.value = 'description'

  try {
    const { data: productRes } = await catalogApi.getProduct(slug)
    rawProduct.value = productRes.data
    resetProduct(product.value)

    if (product.value) void fetchReviews(Number(product.value.id))
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Failed to load product'
    rawProduct.value = null
  } finally {
    loading.value = false
  }
}

async function fetchReviews(productId: number) {
  const current = ++reviewRequest
  reviewsLoading.value = true
  reviewsError.value = false
  try {
    const { data } = await reviewsApi.getProductReviews(productId)
    if (current === reviewRequest && active) reviewsData.value = data.data
  } catch {
    if (current === reviewRequest && active) { reviewsData.value = []; reviewsError.value = true }
  } finally {
    if (current === reviewRequest && active) reviewsLoading.value = false
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
  if (selectedVariant.value) return selectedVariant.value.compareAtPrice
  return product.value?.compareAtPrice ?? null
})

const currentStock = computed(() => selectedVariant.value?.stockQuantity ?? product.value?.stockQuantity ?? 0)

const discountPercent = computed(() => {
  if (currentCompareAt.value && currentCompareAt.value > currentPrice.value) {
    return Math.round(((currentCompareAt.value - currentPrice.value) / currentCompareAt.value) * 100)
  }
  return null
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

const cartActionPending = computed(() => Boolean(pendingCartAction.value) || cart.isPending(`add:${selectedVariant.value?.id}`))

async function performCartAction(action: 'add' | 'buy'): Promise<void> {
  if (cartActionPending.value) return
  cartActionError.value = ''
  checkoutNavigationFailed.value = false
  const missing = attributeGroups.value.find(group => !selectedOptions[group.name])
  if (missing) { cartActionError.value = t('cart.select_option', { name: missing.name }); return }
  const variant = selectedVariant.value
  if (!variant || !hasCombination.value) { cartActionError.value = t('product.no_combination'); return }
  if (!variant.isInStock || stockStatus.value === 'out') { cartActionError.value = t('product.out_of_stock'); return }
  if (!Number.isInteger(quantity.value) || quantity.value < 1 || quantity.value > 99 || quantity.value > currentStock.value) {
    cartActionError.value = t('cart.check_quantity'); return
  }
  // Snapshot primitives before awaiting; later option/route changes cannot alter it.
  const payload = { variantId: variant.id, quantity: quantity.value }
  const origin = route.fullPath
  pendingCartAction.value = action
  try {
    const added = await cart.addItem(payload)
    if (!active || route.fullPath !== origin) return
    if (!added) return // The existing global cart banner displays the normalized error.
    if (action === 'add') { ui.openCartDrawer(); return }
    try {
      const failure = await router.push('/checkout')
      if (failure) checkoutNavigationFailed.value = true
    } catch { checkoutNavigationFailed.value = true }
  } finally { pendingCartAction.value = null }
}

async function addToCart() { await performCartAction('add') }
async function buyNow() { await performCartAction('buy') }

function toggleWishlist() {
  const p = product.value
  if (p) wishlist.toggle(p.id)
}

function goToTab(key: string) {
  activeTab.value = key
}

async function goToReviews() {
  activeTab.value = 'reviews'
  await nextTick()
  const header = document.querySelector<HTMLElement>('[data-testid="mobile-storefront-sticky"]')
  const top = (detailsRef.value?.getBoundingClientRect().top ?? 0) + window.scrollY - (header?.offsetHeight ?? 0) - 12
  window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' })
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

const hasDescription = computed(() => Boolean(productDescriptionText(product.value?.description ?? '').trim()))
</script>

<template>
  <div class="min-w-0 py-4 pb-28 sm:py-6 lg:pb-6">
    <template v-if="loading">
      <div role="status" :aria-label="$t('common.loading')"><PdpSkeleton /></div>
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
      <nav class="flex min-w-0 items-center gap-1 text-xs text-muted" :aria-label="$t('product.breadcrumb')">
        <RouterLink to="/" class="hover:text-primary">{{ $t('nav.home') }}</RouterLink>
        <span>/</span>
        <template v-if="product.category.name"><span class="min-w-0 truncate">{{ product.category.name }}</span><span>/</span></template>
        <span class="truncate text-ink dark:text-ink">{{ product.title }}</span>
      </nav>

      <div class="mt-4 grid min-w-0 gap-6 lg:mt-6 lg:grid-cols-2 lg:gap-10">
        <div class="min-w-0">
          <div
            class="group relative mx-auto aspect-square max-h-[34rem] w-full overflow-hidden border border-border-gray bg-surface-muted"
            @mouseenter="zoomActive = true"
            @mouseleave="zoomActive = false"
            @mousemove="onZoomMove"
          >
            <button v-if="mainImage" type="button" class="absolute inset-0 z-10 cursor-zoom-in" :aria-label="$t('product.zoom_image')" @click="openLightbox"></button>
            <AppImage
              :src="mainImage?.url"
              :alt="mainImage?.alt || product.title"
              fit="contain"
              loading="eager"
              class="h-full w-full transition-transform duration-150"
              :style="zoomStyle"
            />
            <span v-if="zoomActive && mainImage" class="pointer-events-none absolute right-3 top-3 z-20 bg-surface p-2 text-ink">
              <ZoomIn class="h-5 w-5" />
            </span>
            <span v-if="discountPercent" class="absolute left-4 top-4 z-20 rounded-lg bg-accent px-2.5 py-1 text-sm font-bold text-ink dark:text-gray-900">-{{ discountPercent }}%</span>
          </div>

          <div v-if="product.images.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1">
            <button
              v-for="img in product.images"
              :key="img.id"
              type="button"
              class="h-16 w-16 shrink-0 overflow-hidden border-2 transition sm:h-20 sm:w-20"
              :class="selectedImage?.id === img.id ? 'border-primary' : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600'"
              :aria-label="`${$t('product.view_image')}: ${img.alt || product.title}`"
              :aria-pressed="selectedImage?.id === img.id"
              @click="selectedImage = img"
            >
              <AppImage :src="img.url" :alt="img.alt || product.title" fit="contain" class="h-full w-full" />
            </button>
          </div>
        </div>

        <div class="min-w-0">
          <div v-if="product.brand.name" class="text-xs font-semibold uppercase tracking-wide text-primary">{{ product.brand.name }}</div>
          <h1 class="mt-1 break-words text-xl font-bold leading-snug text-ink sm:text-2xl">{{ product.title }}</h1>
          <RouterLink v-if="rawProduct?.shop" :to="{ name: 'shop-storefront', params: { slug: rawProduct.shop.slug } }" class="mt-3 flex items-center gap-2 text-sm text-primary hover:underline"><img v-if="rawProduct.shop.logo" :src="rawProduct.shop.logo" :alt="rawProduct.shop.name" class="h-7 w-7 rounded-lg border border-border-gray object-contain" /><span>{{ $t('marketplace.sold_by', { shop: rawProduct.shop.name }) }}</span></RouterLink>

          <div class="mt-2 break-all text-xs text-muted">{{ $t('product.sku') }}: {{ selectedVariant?.sku ?? product.sku }}</div>

          <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <template v-if="product.reviewCount > 0"><StarRating :value="product.rating" :show-value="true" /><span class="text-xs text-muted">({{ $t('product.review_count', { count: product.reviewCount }) }})</span></template>
            <button class="min-h-11 text-xs font-medium text-primary hover:underline" @click="goToReviews">{{ $t('product.customer_reviews') }}</button>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="max-w-full break-all text-2xl font-extrabold tabular-nums text-ink sm:text-3xl">{{ formatPrice(currentPrice) }}</span>
            <span v-if="currentCompareAt && currentCompareAt > currentPrice" class="max-w-full break-all text-base text-muted line-through">{{ formatPrice(currentCompareAt) }}</span>
            <span v-if="saveAmount" class="rounded-md bg-accent/20 px-2 py-0.5 text-sm font-semibold text-ink dark:text-ink">{{ $t('product.you_save', { amount: formatPrice(saveAmount) }) }}</span>
          </div>

          <div class="mt-3 flex items-center gap-2">
            <StatusTag :status="stockLabel" />
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
                    :disabled="cartActionPending || !optionAvailable(group.name, v.value)"
                    :title="v.value"
                    class="h-11 w-11 transition"
                    :class="[
                      !optionAvailable(group.name, v.value) && 'cursor-not-allowed opacity-40',
                      selectedOptions[group.name] === v.value
                        ? 'ring-2 ring-primary ring-offset-2'
                        : 'ring-1 ring-gray-300 dark:ring-gray-600'
                    ]"
                    :style="{ backgroundColor: colorHex(v.value) }"
                    :aria-label="v.value"
                    :aria-pressed="selectedOptions[group.name] === v.value"
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
                    :disabled="cartActionPending || !optionAvailable(group.name, v.value)"
                    class="min-h-11 border px-4 py-2 text-sm transition"
                    :aria-pressed="selectedOptions[group.name] === v.value"
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
            <QuantityCounter v-model="quantity" :max="Math.min(99, currentStock)" :disabled="cartActionPending || stockStatus === 'out'" />
            <button
              class="btn-primary btn-lg hidden flex-1 lg:inline-flex"
              :disabled="cartActionPending || stockStatus === 'out' || !selectedVariant || !hasCombination"
              :aria-busy="pendingCartAction === 'add'"
              @click="addToCart"
            >
              <ShoppingCart class="h-5 w-5" />
              {{ pendingCartAction === 'add' ? $t('cart.adding') : $t('actions.add_to_cart') }}
            </button>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-3">
            <button
              class="btn-secondary btn-lg hidden flex-1 lg:inline-flex"
              :disabled="cartActionPending || stockStatus === 'out' || !selectedVariant || !hasCombination"
              :aria-busy="pendingCartAction === 'buy'"
              @click="buyNow"
            >
              {{ pendingCartAction === 'buy' ? $t('cart.preparing') : $t('actions.buy_now') }}
            </button>
            <button
              type="button"
              class="btn-secondary btn-lg px-4"
              :aria-label="wishlist.isWishlisted(product.id) ? $t('product.remove_from_wishlist') : $t('product.add_to_wishlist')"
              :aria-pressed="wishlist.isWishlisted(product.id)"
              @click="toggleWishlist"
            >
              <Heart
                class="h-5 w-5"
                :class="wishlist.isWishlisted(product.id) ? 'fill-red-500 text-red-500' : ''"
              />
            </button>
          </div>

          <p v-if="cartActionError" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-300">{{ cartActionError }}</p>
          <div v-if="checkoutNavigationFailed" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-300">
            {{ $t('cart.checkout_navigation_error') }}
            <RouterLink to="/cart" class="ml-1 underline">{{ $t('actions.view_cart') }}</RouterLink>
          </div>

          <div class="mt-6 space-y-3 border-t border-border-gray pt-4 text-sm text-muted">
            <p class="flex items-start gap-2"><Truck class="h-5 w-5 shrink-0 text-primary" />{{ $t('product.delivery_checkout') }}</p>
            <p class="flex items-start gap-2"><RefreshCw class="h-5 w-5 shrink-0 text-primary" />{{ $t('returns.help') }}</p>
          </div>
        </div>
      </div>

      <div ref="detailsRef" class="mt-8 border-b border-border-gray lg:mt-10">
        <div class="flex gap-5 overflow-x-auto whitespace-nowrap">
          <button
            v-for="tab in TABS"
            :key="tab.key"
            type="button"
            class="border-b-2 pb-3 text-sm font-semibold transition"
            :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-gray-500 dark:text-muted hover:text-ink dark:hover:text-ink'"
            :aria-pressed="activeTab === tab.key"
            @click="goToTab(tab.key)"
          >
            {{ $t(tab.labelKey) }}
          </button>
        </div>
      </div>

      <div class="min-w-0 py-5">
        <div v-if="activeTab === 'description'" class="min-w-0 [overflow-wrap:anywhere]">
          <ProductDescription v-if="hasDescription" :content="product.description" />
          <p v-else class="text-sm text-muted">{{ $t('product.no_description') }}</p>
        </div>

        <div v-else-if="activeTab === 'specifications'">
          <table v-if="product.specifications.length" class="w-full table-fixed border-collapse border border-border-gray text-sm [overflow-wrap:anywhere]">
            <tbody>
              <tr v-for="(spec, i) in product.specifications" :key="spec.label" :class="i % 2 ? 'bg-white dark:bg-surface' : 'bg-canvas dark:bg-canvas'">
                <td class="border border-border-gray px-4 py-3 font-semibold text-ink dark:text-ink dark:border-border-gray">{{ spec.label }}</td>
                <td class="border border-border-gray px-4 py-3 text-gray-600 dark:text-muted dark:border-border-gray">{{ spec.value }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-sm text-muted">{{ $t('product.no_specifications') }}</p>
        </div>

        <div v-else-if="activeTab === 'shipping'" class="space-y-3 text-sm text-muted">
          <p>{{ $t('product.delivery_checkout') }}</p>
          <p>{{ $t('returns.help') }}</p>
          <RouterLink to="/account/returns" class="inline-block font-semibold text-primary hover:underline">{{ $t('returns.title') }}</RouterLink>
        </div>

        <section v-else-if="activeTab === 'reviews'" aria-labelledby="customer-reviews-heading" class="min-w-0">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 id="customer-reviews-heading" class="text-lg font-bold text-ink">{{ $t('product.customer_reviews') }}</h2>
              <p class="mt-1 text-sm text-muted">{{ $t('product.reviews_help') }}</p>
            </div>
            <button v-if="!reviewsLoading && !reviewsError && reviews.length && !writeReviewOpen && !reviewSubmitted" type="button" class="btn-primary min-h-11" @click="openWriteReview">{{ $t('product.write_a_review') }}</button>
          </div>

          <p v-if="reviewSubmitted" role="status" class="mt-4 border border-success/30 bg-success/10 px-4 py-3 text-sm text-ink">{{ $t('product.review_pending_note') }}</p>

          <form v-if="writeReviewOpen" class="mt-4 max-w-2xl border border-border-gray bg-surface p-4 sm:p-5" @submit.prevent="submitReview" @focusin="editingReview = true" @focusout="editingReview = false">
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-semibold text-ink">{{ $t('product.your_review') }}</h3>
              <button type="button" class="btn-ghost min-h-11" @click="writeReviewOpen = false">{{ $t('actions.close') }}</button>
            </div>
            <p class="mt-1 text-sm text-muted">{{ $t('product.review_eligibility') }}</p>
            <fieldset class="mt-4">
              <legend class="text-sm font-semibold text-ink">{{ $t('product.rating') }}</legend>
              <div class="mt-1 flex flex-wrap gap-1">
                <label v-for="star in 5" :key="star" class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center border text-sm font-semibold focus-within:ring-2 focus-within:ring-primary" :class="reviewRating === star ? 'border-primary bg-primary/10 text-primary' : 'border-border-gray text-muted hover:border-primary'">
                  <input v-model="reviewRating" type="radio" name="review-rating" :value="star" class="sr-only" />
                  <span>{{ star }} <span aria-hidden="true">★</span><span class="sr-only"> {{ $t('product.stars') }}</span></span>
                </label>
              </div>
            </fieldset>
            <label class="mt-4 block text-sm font-medium text-ink">{{ $t('product.review_title_placeholder') }}<input v-model="reviewTitle" type="text" class="input mt-1" :placeholder="$t('product.review_title_placeholder')" /></label>
            <label class="mt-3 block text-sm font-medium text-ink">{{ $t('product.review_body_placeholder') }}<textarea v-model="reviewBody" class="textarea mt-1" rows="4" :placeholder="$t('product.review_body_placeholder')"></textarea></label>
            <p v-if="writeReviewError" role="alert" class="mt-3 text-sm text-danger">{{ writeReviewError }}</p>
            <button type="submit" class="btn-primary mt-4 min-h-11" :disabled="reviewSubmitting" :aria-busy="reviewSubmitting">{{ reviewSubmitting ? $t('product.review_submitting') : $t('product.review_submit') }}</button>
          </form>

          <div v-if="reviewsLoading" role="status" :aria-label="$t('common.loading')" class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
            <div v-for="i in 2" :key="i" class="animate-pulse border border-border-gray bg-surface p-5"><div class="h-5 w-1/2 bg-surface-muted"></div><div class="mt-4 h-3 w-full bg-surface-muted"></div><div class="mt-3 h-3 w-3/4 bg-surface-muted"></div></div>
          </div>
          <div v-else-if="reviewsError" role="alert" class="mt-5 border border-border-gray bg-surface p-4">
            <p class="text-sm text-danger">{{ $t('product.reviews_unavailable') }}</p>
            <button type="button" class="btn-secondary mt-3 min-h-11" @click="fetchReviews(Number(product.id))">{{ $t('actions.retry') }}</button>
          </div>
          <div v-else-if="!reviews.length" class="mt-5 border border-border-gray bg-surface px-5 py-6 text-center">
            <MessageSquareText class="mx-auto h-7 w-7 text-primary" aria-hidden="true" />
            <h3 class="mt-3 font-semibold text-ink">{{ $t('product.no_reviews_title') }}</h3>
            <p class="mt-1 text-sm text-muted">{{ $t('product.no_reviews_description') }}</p>
            <button v-if="!writeReviewOpen && !reviewSubmitted" type="button" class="btn-primary mt-4 min-h-11" @click="openWriteReview">{{ $t('product.write_a_review') }}</button>
          </div>
          <div v-else class="mt-5 grid min-w-0 gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] lg:gap-6">
            <div class="h-fit border border-border-gray bg-surface p-5">
              <div class="text-3xl font-bold text-ink">{{ product.rating.toFixed(1) }}</div>
              <StarRating class="mt-2" role="img" :value="product.rating" :aria-label="$t('product.rating_out_of', { rating: product.rating.toFixed(1), max: 5 })" />
              <p class="mt-1 text-sm text-muted">{{ $t('product.based_on_reviews', { count: product.reviewCount }) }}</p>
              <div class="mt-5 space-y-2">
                <div v-for="b in ratingBreakdown" :key="b.star" class="flex items-center gap-2 text-xs text-muted" :aria-label="$t('product.rating_distribution', { rating: b.star, count: b.count })">
                  <span class="w-7">{{ b.star }} ★</span><div aria-hidden="true" class="h-2 flex-1 bg-surface-muted"><div class="h-full bg-primary" :style="{ width: bucketPercent(b.count) + '%' }"></div></div><span class="w-6 text-right tabular-nums">{{ b.count }}</span>
                </div>
              </div>
            </div>
            <div class="min-w-0 divide-y divide-border-gray border border-border-gray bg-surface">
              <article v-for="r in reviews" :key="r.id" class="min-w-0 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="flex min-w-0 flex-wrap items-center gap-2"><span class="break-words text-sm font-semibold text-ink">{{ r.author }}</span><span v-if="r.verified" class="inline-flex items-center gap-1 text-xs font-medium text-primary"><BadgeCheck class="h-4 w-4" aria-hidden="true" />{{ $t('home.verified_buyer') }}</span></div>
                  <time class="text-xs text-muted" :datetime="r.date">{{ formatDate(r.date) }}</time>
                </div>
                <StarRating class="mt-2" role="img" :value="r.rating" size="sm" :aria-label="$t('product.rating_out_of', { rating: r.rating, max: 5 })" />
                <h3 v-if="r.title" class="mt-2 break-words font-semibold text-ink">{{ r.title }}</h3>
                <p v-if="r.body" class="mt-1 whitespace-pre-line break-words text-sm text-text-secondary">{{ r.body }}</p>
              </article>
            </div>
          </div>
        </section>
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

    <div v-if="product && !loading && !error && !editingReview" data-testid="mobile-purchase-bar" class="fixed inset-x-0 bottom-[calc(3.75rem+env(safe-area-inset-bottom))] z-30 border-t border-border-gray bg-surface px-3 py-2 shadow-md lg:hidden">
      <p v-if="cartActionError || checkoutNavigationFailed" role="alert" class="mx-auto mb-1 max-w-lg text-xs text-danger">{{ cartActionError || $t('cart.checkout_navigation_error') }}</p>
      <div class="mx-auto flex max-w-lg gap-2">
        <button type="button" class="btn-primary min-w-0 flex-1 !px-2 !text-xs" :disabled="cartActionPending || stockStatus === 'out' || !selectedVariant || !hasCombination" :aria-busy="pendingCartAction === 'add'" @click="addToCart">{{ pendingCartAction === 'add' ? $t('cart.adding') : $t('actions.add_to_cart') }}</button>
        <button type="button" class="btn-secondary min-w-0 flex-1 !px-2 !text-xs" :disabled="cartActionPending || stockStatus === 'out' || !selectedVariant || !hasCombination" :aria-busy="pendingCartAction === 'buy'" @click="buyNow">{{ pendingCartAction === 'buy' ? $t('cart.preparing') : $t('actions.buy_now') }}</button>
      </div>
    </div>
    <BaseModal v-model="lightboxOpen" :title="product?.title ?? ''" size="xl" sharp>
      <AppImage :src="mainImage?.url" :alt="mainImage?.alt || product?.title || ''" fit="contain" class="mx-auto h-[min(65dvh,40rem)] w-full" />
    </BaseModal>
  </div>
</template>
