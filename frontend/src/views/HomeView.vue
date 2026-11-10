<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  ArrowRight,
  ChevronRight,
  Headphones,
  RefreshCcw,
  Quote,
  ShieldCheck,
  Sparkles,
  Truck,
} from 'lucide-vue-next'
import StarRating from '@/components/StarRating.vue'
import ProductRail from '@/components/ProductRail.vue'
import ProductGrid from '@/components/ProductGrid.vue'
import type { Product } from '@/types'
import { mapCatalogProduct } from '@/utils/product'
import { getCategoryIcon } from '@/utils/categoryIcon'
import { useWishlistStore } from '@/stores/wishlist'
import { catalogApi, brandsApi, categoriesApi, reviewsApi } from '@/api'
import type { CatalogProduct, ApiBrand, ApiCategory } from '@/api'

const wishlistStore = useWishlistStore()
const route = useRoute()

function handleWishlist(productId: string): void {
  wishlistStore.toggle(productId)
}

function isActiveCategory(category: ApiCategory): boolean {
  return route.query.category === category.slug
}

const featured = ref<Product[]>([])
const heroPrimary = ref<CatalogProduct | null>(null)
const heroAccent = ref<CatalogProduct | null>(null)
const bestSellers = ref<Product[]>([])
const newArrivals = ref<Product[]>([])
const brandsList = ref<ApiBrand[]>([])
const categoriesList = ref<ApiCategory[]>([])
const failedBrandLogoIds = ref(new Set<number>())

function hasBrandLogo(brand: ApiBrand): boolean {
  return Boolean(brand.logo) && !failedBrandLogoIds.value.has(brand.id)
}

function markBrandLogoFailed(brandId: number): void {
  failedBrandLogoIds.value = new Set(failedBrandLogoIds.value).add(brandId)
}

function brandInitial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || 'B'
}

onMounted(async () => {
  try {
    const [featuredRes, bestRes, newRes, brandsRes, catsRes] = await Promise.all([
      catalogApi.getFeatured(8),
      catalogApi.getProducts({ sort: 'rating', perPage: 8 }),
      catalogApi.getProducts({ sort: 'newest', perPage: 8 }),
      brandsApi.getAll(),
      categoriesApi.getTree()
    ])
    featured.value = featuredRes.data.data.slice(0, 4).map(mapCatalogProduct)
    bestSellers.value = bestRes.data.data.map(mapCatalogProduct)
    newArrivals.value = newRes.data.data.slice(0, 4).map(mapCatalogProduct)
    brandsList.value = brandsRes.data.data
    categoriesList.value = catsRes.data.data
    if (featuredRes.data.data.length > 0) heroPrimary.value = featuredRes.data.data[0]
    if (featuredRes.data.data.length > 1) heroAccent.value = featuredRes.data.data[1]
    await loadTestimonials()
  } catch {
    /* API may not be available */
  }
})

const testimonials = ref<{ id: number; name: string; quote: string; rating: number; verified: boolean }[]>([])

async function loadTestimonials() {
  const candidates = [...featured.value, ...bestSellers.value]
  const seenProducts = new Set<string>()
  const seenReviewers = new Set<number>()
  const results: { id: number; name: string; quote: string; rating: number; verified: boolean }[] = []
  for (const product of candidates) {
    if (results.length >= 3) break
    if (seenProducts.has(product.id)) continue
    seenProducts.add(product.id)
    try {
      const res = await reviewsApi.getProductReviews(Number(product.id))
      const review = res.data.data.find((candidate) => candidate.body?.trim() && !seenReviewers.has(candidate.user.id))
      if (review?.body?.trim()) {
        seenReviewers.add(review.user.id)
        results.push({
          id: review.id,
          name: review.user.name,
          quote: review.body.trim(),
          rating: review.rating,
          verified: review.verified
        })
      }
    } catch {
      /* skip products without public reviews */
    }
  }
  testimonials.value = results
}

function initialsOf(name: string): string {
  return (
    name
      .split(' ')
      .map((w) => w[0]?.toUpperCase() ?? '')
      .join('')
      .slice(0, 2) || 'U'
  )
}

const benefits = [
  { icon: Truck, titleKey: 'home.benefit_shipping_title', textKey: 'home.benefit_shipping_text' },
  { icon: RefreshCcw, titleKey: 'home.benefit_returns_title', textKey: 'home.benefit_returns_text' },
  { icon: ShieldCheck, titleKey: 'home.benefit_secure_title', textKey: 'home.benefit_secure_text' },
  { icon: Headphones, titleKey: 'home.benefit_support_title', textKey: 'home.benefit_support_text' }
]

</script>

<template>
  <div>
    <!-- ===================== HERO ===================== -->
    <section class="hero-gradient relative overflow-hidden text-white">
      <div class="pointer-events-none absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
      <div class="pointer-events-none absolute -bottom-28 right-4 h-96 w-96 rounded-full bg-accent/20 blur-3xl"></div>
      <div class="pointer-events-none absolute right-1/3 top-10 h-40 w-40 rounded-full bg-primary/40 blur-2xl"></div>

      <div class="container-app relative py-6 sm:py-12 lg:py-20">
        <div class="grid items-center gap-6 lg:gap-14 lg:grid-cols-2">
          <div class="max-w-xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider backdrop-blur">
              <Sparkles :size="14" class="text-accent" />
              {{ $t('home.hero_badge') }}
            </div>

            <h1 class="mt-3 text-2xl font-extrabold leading-tight sm:mt-6 sm:text-5xl lg:text-[3.4rem]">
              {{ $t('home.hero_title_1') }}<br />
              <span class="bg-gradient-to-r from-accent to-amber-300 bg-clip-text text-transparent">{{ $t('home.hero_title_2') }}</span>
            </h1>

            <p class="mt-3 max-w-lg text-sm leading-relaxed text-blue-100 sm:mt-5 sm:text-lg">
              {{ $t('home.hero_subtitle') }}
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-3 sm:mt-8 sm:gap-4">
              <RouterLink to="/shop" class="btn-accent min-h-11 gap-2 !px-5 sm:min-h-12 sm:px-8 sm:py-3 sm:text-base">
                {{ $t('home.shop_now') }}
                <ArrowRight :size="18" />
              </RouterLink>
              <RouterLink
                to="/shop?sort=featured"
                class="group inline-flex min-h-11 items-center gap-2 rounded-full border-2 border-white/30 px-5 py-2 text-sm font-semibold text-white transition hover:border-white hover:bg-white/10 sm:px-6 sm:py-3"
              >
                {{ $t('home.explore_offers') }}
                <ChevronRight :size="16" class="transition-transform group-hover:translate-x-0.5" />
              </RouterLink>
            </div>

          </div>

          <div class="relative hidden lg:block">
            <div class="relative mx-auto h-[440px] max-w-md">
              <div class="absolute -inset-6 rounded-[2.5rem] bg-gradient-to-tr from-white/10 to-transparent"></div>

              <img
                v-if="heroPrimary?.cover_image"
                :src="heroPrimary.cover_image"
                :alt="heroPrimary.name"
                class="absolute right-0 top-6 h-72 w-72 rotate-3 rounded-3xl object-cover shadow-2xl ring-1 ring-white/20"
              />
              <img
                v-if="heroAccent?.cover_image"
                :src="heroAccent.cover_image"
                :alt="heroAccent.name"
                class="absolute bottom-0 left-0 h-56 w-56 -rotate-6 rounded-3xl object-cover shadow-2xl ring-1 ring-white/20"
              />

              <div class="absolute left-10 top-0 flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-ink shadow-2xl dark:bg-surface dark:text-ink">
                <Truck :size="18" class="text-primary" />
                  <div class="text-xs">
                  <div class="font-bold">{{ $t('home.benefit_shipping_title') }}</div>
                  <div class="text-gray-500 dark:text-muted">{{ $t('home.benefit_shipping_text') }}</div>
                </div>
              </div>

              <div class="absolute bottom-14 right-2 rounded-2xl bg-white p-4 text-ink shadow-2xl dark:bg-surface dark:text-ink">
                <div class="flex items-center gap-1">
                  <StarRating :value="5" :size="14" />
                </div>
                <div class="mt-1 text-sm font-bold">{{ heroPrimary?.name ?? 'Product' }}</div>
                <div class="text-xs text-gray-500 dark:text-muted">{{ $t('home.loved_by_shoppers') }}</div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== BENEFITS STRIP ===================== -->
    <section class="border-b border-border-gray bg-white dark:border-border-gray dark:bg-canvas">
      <div class="container-app grid grid-cols-2 gap-3 py-4 sm:gap-6 sm:py-6 lg:grid-cols-4">
        <div v-for="b in benefits" :key="b.titleKey" class="flex items-center gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
            <component :is="b.icon" :size="20" />
          </div>
          <div>
            <div class="text-sm font-bold text-ink dark:text-ink">{{ $t(b.titleKey) }}</div>
            <div class="text-xs text-gray-500 dark:text-muted">{{ $t(b.textKey) }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================== CATEGORIES ===================== -->
    <section id="categories" class="container-app py-8 sm:py-16">
      <div class="flex items-end justify-between gap-4">
        <div>
          <div class="section-eyebrow">{{ $t('home.browse') }}</div>
          <h2 class="section-title">{{ $t('home.shop_by_category') }}</h2>
          <p class="section-subtitle">{{ $t('home.category_subtitle') }}</p>
        </div>
        <RouterLink
          to="/shop"
          class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-primary transition-colors hover:text-primary-dark sm:flex"
        >
          {{ $t('home.all_categories') }}
          <ArrowRight :size="16" />
        </RouterLink>
      </div>

      <div class="mt-5 grid grid-cols-2 gap-3 min-[360px]:grid-cols-3 sm:mt-8 sm:grid-cols-4 sm:gap-4 lg:grid-cols-6">
        <RouterLink
          v-for="cat in categoriesList"
          :key="cat.id"
          :to="`/shop?category=${cat.slug}`"
          class="feature-glow group relative flex min-h-32 min-w-0 flex-col overflow-hidden rounded-2xl border border-border-gray bg-surface shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-canvas sm:min-h-40"
          :class="isActiveCategory(cat) ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : ''"
        >
          <div class="relative flex h-20 shrink-0 items-center justify-center overflow-hidden bg-surface-muted sm:h-24">
            <img
              v-if="cat.image"
              :src="cat.image"
              :alt="cat.name"
              loading="lazy"
              class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
            <div
              v-else
              class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary/10 to-accent/15 text-primary"
            >
              <component :is="getCategoryIcon(cat)" class="h-9 w-9" />
            </div>
          </div>
          <div class="flex min-h-12 flex-1 items-center justify-center px-2 py-2 text-center sm:min-h-14 sm:px-3">
            <span class="line-clamp-2 text-xs font-bold leading-4 text-ink group-hover:text-primary sm:text-sm sm:leading-5">
              {{ $te(`categories.${cat.slug.replace(/-/g, '_')}`) ? $t(`categories.${cat.slug.replace(/-/g, '_')}`) : cat.name }}
            </span>
          </div>
        </RouterLink>
      </div>
    </section>

    <!-- ===================== FEATURED ===================== -->
    <section class="container-app pb-4">
      <ProductRail
        :title="$t('home.featured_title')"
        :subtitle="$t('home.featured_subtitle')"
        :products="featured"
        view-all-link="/shop"
      />
    </section>

    <!-- ===================== BEST SELLERS ===================== -->
    <section class="container-app pb-12">
      <ProductRail
        :title="$t('home.best_sellers_title')"
        :subtitle="$t('home.best_sellers_subtitle')"
        :products="bestSellers"
        view-all-link="/shop?sort=rating"
      />
    </section>

    <!-- ===================== BRANDS ===================== -->
    <section class="border-y border-border-gray bg-surface py-6 dark:border-border-gray dark:bg-canvas sm:py-8">
      <div class="container-app">
        <div class="mb-4 flex items-end justify-between gap-4 sm:mb-5">
          <div>
            <div class="section-eyebrow">{{ $t('home.browse') }}</div>
            <h2 class="section-title">{{ $t('admin.brands.title') }}</h2>
          </div>
          <RouterLink
            to="/shop"
            class="inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-semibold text-primary transition-colors hover:text-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-canvas"
          >
            <span>{{ $t('actions.view_all') }} {{ $t('admin.brands.title') }}</span>
            <ArrowRight :size="16" />
          </RouterLink>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4 lg:grid-cols-5">
          <RouterLink
            v-for="brand in brandsList"
            :key="brand.id"
            :to="`/shop?brand=${brand.slug}`"
            class="group flex min-h-32 min-w-0 flex-col items-center justify-center rounded-2xl border border-border-gray bg-canvas/60 px-3 py-3 text-center shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/60 hover:bg-primary/5 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 dark:focus-visible:ring-offset-canvas sm:min-h-36 sm:px-4"
            :aria-label="`${$t('actions.view_all')}: ${brand.name}`"
          >
            <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-border-gray bg-surface p-1.5 shadow-sm sm:h-[4.5rem] sm:w-[4.5rem]">
              <img v-if="hasBrandLogo(brand)" :src="brand.logo!" :alt="brand.name" class="h-full w-full object-contain" @error="markBrandLogoFailed(brand.id)" />
              <span v-else class="flex h-full w-full items-center justify-center rounded-xl bg-gradient-to-br from-primary/15 to-accent/25 text-xl font-extrabold text-primary sm:text-2xl">{{ brandInitial(brand.name) }}</span>
            </span>
            <span class="mt-2 line-clamp-2 min-h-10 text-sm font-bold leading-5 text-ink transition-colors group-hover:text-primary">{{ brand.name }}</span>
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- ===================== NEW ARRIVALS ===================== -->
    <section class="container-app py-10 sm:py-14">
      <div class="mb-5 flex items-end justify-between gap-4">
        <div>
          <div class="section-eyebrow">{{ $t('home.just_landed') }}</div>
          <h2 class="section-title">{{ $t('home.new_arrivals') }}</h2>
          <p class="section-subtitle">{{ $t('home.new_arrivals_subtitle') }}</p>
        </div>
        <RouterLink
          to="/shop?sort=featured"
          class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-primary transition-colors hover:text-primary-dark sm:flex"
        >
          {{ $t('actions.view_all') }}
          <ArrowRight :size="16" />
        </RouterLink>
      </div>
      <ProductGrid :products="newArrivals" @wishlist-toggle="handleWishlist" />
    </section>

    <!-- ===================== TESTIMONIALS ===================== -->
    <section v-if="testimonials.length" class="bg-canvas py-10 dark:bg-canvas sm:py-14">
      <div class="container-app">
        <div class="mx-auto max-w-2xl text-center">
          <div class="section-eyebrow">{{ $t('home.testimonials') }}</div>
          <h2 class="section-title">{{ $t('home.testimonials_title') }}</h2>
          <p class="section-subtitle">{{ $t('home.testimonials_subtitle') }}</p>
        </div>

        <div class="mt-6 grid gap-3 sm:mt-8 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
          <div
            v-for="t in testimonials"
            :key="t.id"
            class="card feature-glow relative flex min-w-0 flex-col justify-between rounded-2xl p-4 transition-shadow duration-300 hover:shadow-lg sm:p-5"
          >
            <div>
              <Quote :size="20" class="text-primary/50" aria-hidden="true" />
              <StarRating :value="t.rating" />
              <p class="mt-3 line-clamp-4 text-sm leading-relaxed text-text-secondary dark:text-muted">{{ t.quote }}</p>
            </div>
            <div class="mt-5 flex items-center gap-3 border-t border-border-gray pt-4">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                {{ initialsOf(t.name) }}
              </div>
              <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-ink dark:text-ink">{{ t.name }}</div>
                <div v-if="t.verified" class="flex items-center gap-1 text-xs text-muted">
                  <BadgeCheck :size="13" class="text-primary" />
                  {{ $t('home.verified_buyer') }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </div>
</template>
