<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { categoriesApi } from '@/api'

const route = useRoute()
const { locale } = useI18n()

const navCategories = ref<{ id: number; name: string; slug: string }[]>([])
const railRef = ref<HTMLUListElement | null>(null)
const canScrollBack = ref(false)
const canScrollForward = ref(false)
let resizeObserver: ResizeObserver | undefined

watch(locale, async () => { await nextTick(); updateScrollControls() })

onMounted(async () => {
  try {
    const { data } = await categoriesApi.getTree()
    navCategories.value = data.data
  } catch {
    navCategories.value = []
  }

  await nextTick()
  updateScrollControls()
  resizeObserver = new ResizeObserver(updateScrollControls)
  if (railRef.value) resizeObserver.observe(railRef.value)
})

onBeforeUnmount(() => resizeObserver?.disconnect())

function isActive(slug: string): boolean {
  return route.query.category === slug
}

function updateScrollControls(): void {
  const rail = railRef.value
  if (!rail) return
  canScrollBack.value = rail.scrollLeft > 1
  canScrollForward.value = rail.scrollLeft + rail.clientWidth < rail.scrollWidth - 1
}

function scrollRail(direction: -1 | 1): void {
  const amount = Math.max(240, Math.floor((railRef.value?.clientWidth ?? 480) * 0.65))
  railRef.value?.scrollBy({ left: direction * amount, behavior: 'smooth' })
}
</script>

<template>
  <nav data-testid="storefront-category-nav" :aria-label="$t('nav.shop_by_category')" class="bg-surface">
    <div class="container-app flex h-11 min-w-0 items-stretch lg:h-12 lg:gap-2">
      <button
        type="button"
        class="category-scroll-control hidden lg:inline-flex"
        :class="{ 'invisible pointer-events-none': !canScrollBack }"
        :aria-label="$t('pagination.previous')"
        @click="scrollRail(-1)"
      >
        <ChevronLeft :size="18" aria-hidden="true" />
      </button>
      <ul
        ref="railRef"
        class="scrollbar-none flex min-w-0 flex-1 items-stretch gap-0.5 overflow-x-auto whitespace-nowrap text-sm font-medium"
        @scroll.passive="updateScrollControls"
      >
        <li class="flex shrink-0">
          <RouterLink to="/shop" class="category-nav-link" exact-active-class="category-nav-link--active">
            {{ $t('nav.all_products') }}
          </RouterLink>
        </li>
        <li class="flex shrink-0"><RouterLink to="/shops" class="category-nav-link" exact-active-class="category-nav-link--active">{{ $t('storefront.title') }}</RouterLink></li>
        <li v-for="category in navCategories" :key="category.id" class="flex shrink-0">
          <RouterLink
            :to="{ name: 'shop', query: { category: category.slug } }"
            class="category-nav-link"
            :class="isActive(category.slug) ? 'category-nav-link--active' : ''"
          >
            {{ $te(`categories.${category.slug.replace(/-/g, '_')}`) ? $t(`categories.${category.slug.replace(/-/g, '_')}`) : category.name }}
          </RouterLink>
        </li>
      </ul>
      <button
        type="button"
        class="category-scroll-control hidden lg:inline-flex"
        :class="{ 'invisible pointer-events-none': !canScrollForward }"
        :aria-label="$t('pagination.next')"
        @click="scrollRail(1)"
      >
        <ChevronRight :size="18" aria-hidden="true" />
      </button>
    </div>
  </nav>
</template>

<style scoped>
.category-scroll-control {
  width: 2rem;
  height: 2rem;
  flex: 0 0 2rem;
  align-items: center;
  justify-content: center;
  align-self: center;
  border: 1px solid rgb(var(--color-border));
  background: rgb(var(--color-surface));
  color: rgb(var(--color-muted));
  transition: color .15s ease, background-color .15s ease, border-color .15s ease;
}
.category-scroll-control:hover { border-color: rgb(var(--color-primary)); color: rgb(var(--color-primary)); background: rgb(var(--color-surface-active)); }
.category-scroll-control:active { background: rgb(var(--color-primary) / .12); }
.category-nav-link {
  position: relative;
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  padding: 0 .8rem;
  color: rgb(var(--color-ink));
  font-weight: 650;
  transition: color .15s ease, background-color .15s ease;
}
.category-nav-link:hover { background: rgb(var(--color-canvas)); color: rgb(var(--color-primary)); }
.category-nav-link:active { background: rgb(var(--color-surface-active)); }
.category-nav-link--active { color: rgb(var(--color-primary)); }
.category-nav-link--active::after { position: absolute; right: 0; bottom: 0; left: 0; height: 2px; background: rgb(var(--color-primary)); content: ''; }
</style>
