<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { SlidersHorizontal, X, SearchX } from 'lucide-vue-next'
import ProductCard from '@/components/ProductCard.vue'
import BasePagination from '@/components/BasePagination.vue'
import EmptyState from '@/components/EmptyState.vue'
import StarRating from '@/components/StarRating.vue'
import { catalogApi } from '@/api/catalog'
import type { Facets } from '@/api/catalog'
import type { Product } from '@/types'
import { mapCatalogProduct } from '@/utils/product'
import { swatchColor } from '@/utils/color'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { useUiStore } from '@/stores/ui'

interface Filters {
  q?: string
  category?: string
  brand?: string
  colors: string[]
  sizes: string[]
  min?: number
  max?: number
  rating?: number
  inStockOnly: boolean
  sort: string
  page: number
}

const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const wishlist = useWishlistStore()
const ui = useUiStore()

const pageSize = 12
const PRICE_MAX = 450

const products = ref<Product[]>([])
const totalCount = ref(0)
const pageCount = ref(1)
const facets = ref<Facets>({ brands: [], categories: [], colors: [], sizes: [] })
const loading = ref(false)

const filters = reactive<Filters>({
  q: undefined,
  category: undefined,
  brand: undefined,
  colors: [],
  sizes: [],
  min: undefined,
  max: undefined,
  rating: undefined,
  inStockOnly: false,
  sort: 'featured',
  page: 1
})

let applying = false

function handleAdd(payload: { product: Product; variantId?: string }): void {
  if (!payload.variantId) return
  cart.addItem({ variantId: payload.variantId, quantity: 1 })
}

function toArray(v: unknown): string[] {
  if (Array.isArray(v)) return v.filter((x): x is string => typeof x === 'string')
  if (typeof v === 'string') return v ? [v] : []
  return []
}

function toNum(v: unknown): number | undefined {
  if (typeof v === 'string' && v !== '' && !Number.isNaN(Number(v))) return Number(v)
  return undefined
}

function applyFromQuery(q: Record<string, unknown>) {
  applying = true
  filters.q = typeof q.q === 'string' ? q.q : undefined
  filters.category = typeof q.category === 'string' ? q.category : undefined
  filters.brand = typeof q.brand === 'string' ? q.brand : undefined
  filters.colors = toArray(q.colors)
  filters.sizes = toArray(q.sizes)
  filters.min = toNum(q.min)
  filters.max = toNum(q.max)
  filters.rating = toNum(q.rating)
  filters.inStockOnly = q.stock === '1'
  filters.sort = typeof q.sort === 'string' ? q.sort : 'featured'
  const page = toNum(q.page)
  filters.page = page !== undefined && page > 0 ? page : 1
  applying = false
}

function pushRoute() {
  if (applying) return
  const query: Record<string, string | string[]> = {}
  if (filters.q) query.q = filters.q
  if (filters.category) query.category = filters.category
  if (filters.brand) query.brand = filters.brand
  if (filters.colors.length) query.colors = filters.colors
  if (filters.sizes.length) query.sizes = filters.sizes
  if (filters.min !== undefined) query.min = String(filters.min)
  if (filters.max !== undefined) query.max = String(filters.max)
  if (filters.rating !== undefined) query.rating = String(filters.rating)
  if (filters.inStockOnly) query.stock = '1'
  if (filters.sort !== 'featured') query.sort = filters.sort
  if (filters.page > 1) query.page = String(filters.page)
  router.replace({ query })
}

async function fetchProducts() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: filters.page,
      perPage: pageSize
    }
    if (filters.q) params.q = filters.q
    if (filters.category) params.category = filters.category
    if (filters.brand) params.brand = filters.brand
    if (filters.colors.length) params.colors = filters.colors
    if (filters.sizes.length) params.sizes = filters.sizes
    if (filters.min !== undefined) params.min = filters.min
    if (filters.max !== undefined) params.max = filters.max
    if (filters.rating !== undefined) params.rating = filters.rating
    if (filters.inStockOnly) params.stock = 1
    if (filters.sort && filters.sort !== 'featured') params.sort = filters.sort

    const { data } = await catalogApi.getProducts(params)
    products.value = data.data.map(mapCatalogProduct)
    totalCount.value = data.meta.total
    pageCount.value = data.meta.last_page
  } catch {
    products.value = []
    totalCount.value = 0
    pageCount.value = 1
  } finally {
    loading.value = false
  }
}

async function fetchFacets() {
  try {
    const { data } = await catalogApi.getFacets()
    facets.value = data.data
  } catch {
    facets.value = { brands: [], categories: [], colors: [], sizes: [] }
  }
}

function sameArray(v: unknown, arr: string[]): boolean {
  const a = toArray(v)
  if (a.length !== arr.length) return false
  const set = new Set(arr)
  return a.every((x) => set.has(x))
}

function sameAsFilters(q: Record<string, unknown>): boolean {
  return (
    (typeof q.q === 'string' ? q.q : undefined) === (filters.q ?? undefined) &&
    (typeof q.category === 'string' ? q.category : undefined) === (filters.category ?? undefined) &&
    (typeof q.brand === 'string' ? q.brand : undefined) === (filters.brand ?? undefined) &&
    sameArray(q.colors, filters.colors) &&
    sameArray(q.sizes, filters.sizes) &&
    (toNum(q.min) ?? undefined) === (filters.min ?? undefined) &&
    (toNum(q.max) ?? undefined) === (filters.max ?? undefined) &&
    (toNum(q.rating) ?? undefined) === (filters.rating ?? undefined) &&
    (q.stock === '1') === filters.inStockOnly &&
    (typeof q.sort === 'string' && q.sort !== '' ? q.sort : 'featured') === filters.sort &&
    (toNum(q.page) ?? 1) === filters.page
  )
}

watch(filters, () => {
  if (applying) return
  if (sameAsFilters(route.query)) return
  pushRoute()
  fetchProducts()
}, { deep: true })

watch(() => route.query, (q) => {
  if (applying) return
  if (sameAsFilters(q)) return
  applyFromQuery(q)
  fetchProducts()
})

onMounted(() => {
  applyFromQuery(route.query)
  fetchProducts()
  fetchFacets()
})

/**
 * The catalog filter matches on the exact attribute value, so the chip carries
 * `value` for the query string and `swatch` only for rendering. Previously the
 * slug was used for both, which meant `Light Blue` was sent as `light-blue`
 * (matching nothing) and painted as an invalid CSS colour.
 */
const colorOptions = computed(() =>
  facets.value.colors.map((c) => ({
    slug: c.slug,
    value: c.value ?? c.name,
    name: c.name,
    count: c.count,
    swatch: swatchColor(c.swatch_color, c.name)
  }))
)

const activeCategoryName = computed(() => {
  if (!filters.category) return ''
  const c = facets.value.categories.find((x) => x.slug === filters.category)
  return c ? c.name : ''
})

const summaryChips = computed(() => {
  const chips: { key: string; label: string }[] = []
  if (filters.q) chips.push({ key: 'q', label: `"${filters.q}"` })
  if (filters.category) {
    const c = facets.value.categories.find((x) => x.slug === filters.category)
    chips.push({ key: 'category', label: c ? c.name : filters.category })
  }
  if (filters.brand) {
    const b = facets.value.brands.find((x) => x.slug === filters.brand)
    chips.push({ key: 'brand', label: b ? b.name : filters.brand })
  }
  return chips
})

function setSort(v: string) {
  filters.page = 1
  filters.sort = v
}

function removeChip(key: string) {
  filters.page = 1
  if (key === 'q') filters.q = undefined
  if (key === 'category') filters.category = undefined
  if (key === 'brand') filters.brand = undefined
}

function toggleColor(value: string) {
  filters.page = 1
  const idx = filters.colors.indexOf(value)
  if (idx >= 0) filters.colors.splice(idx, 1)
  else filters.colors.push(value)
}

function toggleSize(size: string) {
  filters.page = 1
  const idx = filters.sizes.indexOf(size)
  if (idx >= 0) filters.sizes.splice(idx, 1)
  else filters.sizes.push(size)
}

function setMin(value: number) {
  filters.page = 1
  filters.min = value
  if (filters.max !== undefined && filters.min !== undefined && filters.min > filters.max) {
    filters.max = filters.min
  }
}

function setMax(value: number) {
  filters.page = 1
  filters.max = value
  if (filters.min !== undefined && filters.max !== undefined && filters.max < filters.min) {
    filters.min = filters.max
  }
}

function setRating(value: number | undefined) {
  filters.page = 1
  filters.rating = value
}

function setCategory(slug: string | undefined) {
  filters.page = 1
  filters.category = slug
}

function setBrand(name: string | undefined) {
  filters.page = 1
  filters.brand = name
}

function setInStock(value: boolean) {
  filters.page = 1
  filters.inStockOnly = value
}

function resetFilters() {
  applying = true
  filters.q = undefined
  filters.category = undefined
  filters.brand = undefined
  filters.colors = []
  filters.sizes = []
  filters.min = undefined
  filters.max = undefined
  filters.rating = undefined
  filters.inStockOnly = false
  filters.sort = 'featured'
  filters.page = 1
  applying = false
  router.replace({ query: {} })
}

function clearAll() {
  resetFilters()
}

function setPage(p: number) {
  filters.page = p
}
</script>

<template>
  <div class="container-app py-6">
    <nav class="text-xs text-gray-500 dark:text-muted dark:text-gray-500">
      <RouterLink to="/" class="hover:text-primary">{{ $t('nav.home') }}</RouterLink>
      <span class="mx-1">/</span>
      <RouterLink to="/shop" class="hover:text-primary">{{ $t('nav.shop') }}</RouterLink>
      <span v-if="filters.category" class="mx-1">/</span>
      <span v-if="filters.category" class="font-medium text-ink dark:text-ink">{{ activeCategoryName }}</span>
      <template v-else-if="filters.q">
        <span class="mx-1">/</span>
        <span class="font-medium text-ink dark:text-ink">{{ $t('shop.search_results') }}</span>
      </template>
    </nav>

    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-ink dark:text-ink sm:text-3xl">{{ $t('shop.shop_products') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted dark:text-gray-500">
          <template v-if="filters.q || filters.category || filters.brand || filters.colors.length || filters.sizes.length || filters.min !== undefined || filters.max !== undefined || filters.rating !== undefined || filters.inStockOnly">
            {{ $t('shop.showing_of', { shown: products.length, total: totalCount }) }}
          </template>
          <template v-else>
            {{ $t('shop.count_products', { count: totalCount }) }}
          </template>
        </p>
      </div>
    </div>

    <div class="card mt-5 flex items-center justify-between gap-3 p-3">
      <div class="flex flex-wrap items-center gap-2">
        <button class="btn-secondary btn-sm lg:hidden" @click="ui.openMobileFilters()">
          <SlidersHorizontal class="h-4 w-4" />
          {{ $t('shop.filter') }}
        </button>
        <div v-if="summaryChips.length" class="flex flex-wrap items-center gap-2">
          <span
            v-for="chip in summaryChips"
            :key="chip.key"
            class="chip"
          >
            {{ chip.label }}
            <button class="ml-1 text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-200" @click="removeChip(chip.key)">
              <X class="h-3 w-3" />
            </button>
          </span>
        </div>
      </div>
      <select class="select w-auto" :value="filters.sort" @change="setSort(($event.target as HTMLSelectElement).value)">
        <option value="featured">{{ $t('shop.sort_featured') }}</option>
        <option value="price-asc">{{ $t('shop.sort_price_asc') }}</option>
        <option value="price-desc">{{ $t('shop.sort_price_desc') }}</option>
        <option value="rating">{{ $t('shop.sort_rating') }}</option>
      </select>
    </div>

    <div class="mt-6 lg:grid lg:grid-cols-[260px_1fr] lg:gap-8">
      <aside class="hidden lg:block">
        <div class="card space-y-6 p-5">
          <div>
            <div class="label">{{ $t('shop.category') }}</div>
            <div class="space-y-2">
              <label v-for="c in facets.categories" :key="c.slug" class="flex cursor-pointer items-center justify-between text-sm text-gray-700 dark:text-muted hover:text-primary">
                <span class="flex items-center gap-2">
                  <input
                    type="radio"
                    name="category"
                    :checked="filters.category === c.slug"
                    class="h-4 w-4 accent-primary"
                    @change="setCategory(filters.category === c.slug ? undefined : c.slug)"
                  />
                  {{ c.name }}
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ c.count }}</span>
              </label>
            </div>
          </div>

          <div>
            <div class="label">{{ $t('shop.brand') }}</div>
            <div class="space-y-2">
              <label v-for="b in facets.brands" :key="b.slug" class="flex cursor-pointer items-center justify-between text-sm text-gray-700 dark:text-muted hover:text-primary">
                <span class="flex items-center gap-2">
                  <input
                    type="radio"
                    name="brand"
                    :checked="filters.brand === b.slug"
                    class="h-4 w-4 accent-primary"
                    @change="setBrand(filters.brand === b.slug ? undefined : b.slug)"
                  />
                  {{ b.name }}
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ b.count }}</span>
              </label>
            </div>
          </div>

          <div>
            <div class="label">{{ $t('shop.price_range') }}</div>
            <div class="px-1">
              <input type="range" min="0" :max="PRICE_MAX" step="5" :value="filters.min ?? 0" class="w-full accent-primary" @input="setMin(Number(($event.target as HTMLInputElement).value))" />
              <input type="range" min="0" :max="PRICE_MAX" step="5" :value="filters.max ?? PRICE_MAX" class="w-full accent-primary" @input="setMax(Number(($event.target as HTMLInputElement).value))" />
            </div>
            <div class="mt-2 flex items-center justify-between text-xs font-medium text-gray-600">
              <span class="chip">${{ filters.min ?? 0 }}</span>
              <span>–</span>
              <span class="chip">${{ filters.max ?? PRICE_MAX }}</span>
            </div>
          </div>

          <div>
            <div class="label">{{ $t('shop.rating') }}</div>
            <div class="space-y-2">
              <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                <input type="radio" name="rating" :checked="filters.rating === 4.5" class="h-4 w-4 accent-primary" @change="setRating(filters.rating === 4.5 ? undefined : 4.5)" />
                <StarRating :value="4.5" size="sm" />
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('shop.up') }}</span>
              </label>
              <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                <input type="radio" name="rating" :checked="filters.rating === 4.0" class="h-4 w-4 accent-primary" @change="setRating(filters.rating === 4.0 ? undefined : 4.0)" />
                <StarRating :value="4.0" size="sm" />
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('shop.up') }}</span>
              </label>
              <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                <input type="radio" name="rating" :checked="filters.rating === 3.5" class="h-4 w-4 accent-primary" @change="setRating(filters.rating === 3.5 ? undefined : 3.5)" />
                <StarRating :value="3.5" size="sm" />
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('shop.up') }}</span>
              </label>
              <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                <input type="radio" name="rating" :checked="filters.rating === undefined" class="h-4 w-4 accent-primary" @change="setRating(undefined)" />
                  <span>{{ $t('shop.any') }}</span>
              </label>
            </div>
          </div>

          <div>
            <div class="label">{{ $t('shop.color') }}</div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="c in colorOptions"
                :key="c.slug"
                type="button"
                class="h-8 w-8 rounded-full border-2 transition"
                :class="filters.colors.includes(c.value) ? 'border-primary ring-2 ring-primary/30' : 'border-gray-300 hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500'"
                :style="{ backgroundColor: c.swatch }"
                :title="c.name"
                @click="toggleColor(c.value)"
              ></button>
            </div>
          </div>

          <div>
            <div class="label">{{ $t('shop.size') }}</div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="s in facets.sizes"
                :key="s.slug"
                type="button"
                class="rounded-lg border px-3 py-1.5 text-sm transition"
                :class="filters.sizes.includes(s.value ?? s.name) ? 'border-primary bg-primary/5 text-primary' : 'border-border-gray text-gray-600 hover:border-gray-300 dark:border-border-gray dark:text-muted dark:hover:border-gray-500'"
                @click="toggleSize(s.value ?? s.name)"
              >
                {{ s.name }}
              </button>
            </div>
          </div>

          <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
            <input type="checkbox" :checked="filters.inStockOnly" class="h-4 w-4 accent-primary" @change="setInStock(($event.target as HTMLInputElement).checked)" />
            {{ $t('shop.in_stock_only') }}
          </label>

          <button class="btn-outline w-full" @click="clearAll">{{ $t('shop.clear_all') }}</button>
        </div>
      </aside>

      <div class="mt-6 lg:mt-0">
        <div v-if="loading" class="flex items-center justify-center py-20">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
        </div>
        <template v-else>
          <div v-if="products.length" class="grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-4">
            <ProductCard
              v-for="p in products"
              :key="p.id"
              :product="p"
              @add-to-cart="handleAdd"
              @wishlist-toggle="wishlist.toggle($event)"
            />
          </div>

          <EmptyState
            v-else
            :title="$t('shop.no_match_title')"
            :description="$t('shop.no_match_description')"
            :cta-label="$t('shop.clear_filters')"
            @cta="clearAll()"
          >
            <template #icon>
              <SearchX class="h-10 w-10 text-gray-300 dark:text-gray-500" />
            </template>
          </EmptyState>
        </template>

        <BasePagination
          :page="filters.page"
          :page-count="pageCount"
          :total-items="totalCount"
          :page-size="pageSize"
          @update:page="setPage"
        />
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="ui.mobileFiltersOpen"
        class="fixed inset-0 z-50 lg:hidden"
      >
        <div class="absolute inset-0 bg-black/50" @click="ui.closeMobileFilters()"></div>
        <div class="absolute inset-y-0 right-0 w-80 max-w-[85vw] overflow-y-auto bg-white p-5 shadow-2xl dark:bg-surface">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-ink dark:text-ink">{{ $t('shop.filter') }}</h3>
            <button class="btn-icon" @click="ui.closeMobileFilters()">
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="space-y-6">
            <div>
              <div class="label">{{ $t('shop.category') }}</div>
              <div class="space-y-2">
                <label v-for="c in facets.categories" :key="c.slug" class="flex cursor-pointer items-center justify-between text-sm text-gray-700 dark:text-muted">
                  <span class="flex items-center gap-2">
                    <input type="radio" name="m-category" :checked="filters.category === c.slug" class="h-4 w-4 accent-primary" @change="setCategory(filters.category === c.slug ? undefined : c.slug)" />
                    {{ c.name }}
                  </span>
                  <span class="text-xs text-gray-400 dark:text-gray-500">{{ c.count }}</span>
                </label>
              </div>
            </div>

            <div>
              <div class="label">{{ $t('shop.brand') }}</div>
              <div class="space-y-2">
                <label v-for="b in facets.brands" :key="b.slug" class="flex cursor-pointer items-center justify-between text-sm text-gray-700 dark:text-muted">
                  <span class="flex items-center gap-2">
                    <input type="radio" name="m-brand" :checked="filters.brand === b.slug" class="h-4 w-4 accent-primary" @change="setBrand(filters.brand === b.slug ? undefined : b.slug)" />
                    {{ b.name }}
                  </span>
                  <span class="text-xs text-gray-400 dark:text-gray-500">{{ b.count }}</span>
                </label>
              </div>
            </div>

            <div>
              <div class="label">{{ $t('shop.price_range') }}</div>
              <input type="range" min="0" :max="PRICE_MAX" step="5" :value="filters.min ?? 0" class="w-full accent-primary" @input="setMin(Number(($event.target as HTMLInputElement).value))" />
              <input type="range" min="0" :max="PRICE_MAX" step="5" :value="filters.max ?? PRICE_MAX" class="w-full accent-primary" @input="setMax(Number(($event.target as HTMLInputElement).value))" />
            <div class="mt-2 flex items-center justify-between text-xs font-medium text-gray-600 dark:text-muted">
                <span class="chip">${{ filters.min ?? 0 }}</span>
                <span>–</span>
                <span class="chip">${{ filters.max ?? PRICE_MAX }}</span>
              </div>
            </div>

            <div>
              <div class="label">{{ $t('shop.rating') }}</div>
              <div class="space-y-2">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                  <input type="radio" name="m-rating" :checked="filters.rating === 4.5" class="h-4 w-4 accent-primary" @change="setRating(filters.rating === 4.5 ? undefined : 4.5)" />
                  <StarRating :value="4.5" size="sm" /> <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('shop.up') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                  <input type="radio" name="m-rating" :checked="filters.rating === 4.0" class="h-4 w-4 accent-primary" @change="setRating(filters.rating === 4.0 ? undefined : 4.0)" />
                  <StarRating :value="4.0" size="sm" /> <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('shop.up') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                  <input type="radio" name="m-rating" :checked="filters.rating === 3.5" class="h-4 w-4 accent-primary" @change="setRating(filters.rating === 3.5 ? undefined : 3.5)" />
                  <StarRating :value="3.5" size="sm" /> <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('shop.up') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
                  <input type="radio" name="m-rating" :checked="filters.rating === undefined" class="h-4 w-4 accent-primary" @change="setRating(undefined)" />
                  <span>{{ $t('shop.any') }}</span>
                </label>
              </div>
            </div>

            <div>
              <div class="label">{{ $t('shop.color') }}</div>
              <div class="flex flex-wrap gap-2">
                <button v-for="c in colorOptions" :key="c.slug" type="button" class="h-8 w-8 rounded-full border-2 transition" :class="filters.colors.includes(c.slug) ? 'border-primary ring-2 ring-primary/30' : 'border-gray-300 dark:border-gray-600' " :style="{ backgroundColor: c.slug }" :title="c.name" @click="toggleColor(c.slug)"></button>
              </div>
            </div>

            <div>
              <div class="label">{{ $t('shop.size') }}</div>
              <div class="flex flex-wrap gap-2">
                <button v-for="s in facets.sizes" :key="s.slug" type="button" class="rounded-lg border px-3 py-1.5 text-sm" :class="filters.sizes.includes(s.value ?? s.name) ? 'border-primary bg-primary/5 text-primary' : 'border-border-gray text-gray-600 dark:border-border-gray dark:text-muted'" @click="toggleSize(s.value ?? s.name)">{{ s.name }}</button>
              </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-muted">
              <input type="checkbox" :checked="filters.inStockOnly" class="h-4 w-4 accent-primary" @change="setInStock(($event.target as HTMLInputElement).checked)" />
              {{ $t('shop.in_stock_only') }}
            </label>

            <div class="flex gap-2">
              <button class="btn-outline flex-1" @click="clearAll">{{ $t('shop.clear_all') }}</button>
              <button class="btn-primary flex-1 lg:hidden" @click="ui.closeMobileFilters()">{{ $t('actions.apply') }}</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
