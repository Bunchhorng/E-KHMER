<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppHeader from '@/components/AppHeader.vue'
import CategoryNavBar from '@/components/CategoryNavBar.vue'
import PromotionBar from '@/components/PromotionBar.vue'
import AppFooter from '@/components/AppFooter.vue'
import CartDrawer from '@/components/CartDrawer.vue'
import ContentLayout from '@/layouts/ContentLayout.vue'

const scrolled = ref(false)

function updateScrollElevation(): void {
  scrolled.value = window.scrollY > 4
}

onMounted(() => {
  updateScrollElevation()
  window.addEventListener('scroll', updateScrollElevation, { passive: true })
})

onBeforeUnmount(() => window.removeEventListener('scroll', updateScrollElevation))
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <PromotionBar />
    <div data-testid="mobile-storefront-sticky" class="mobile-storefront-sticky lg:contents" :class="{ 'mobile-storefront-sticky--elevated': scrolled }">
      <AppHeader />
      <CategoryNavBar />
    </div>
    <router-view v-slot="{ Component, route }">
      <main v-if="route.name === 'home'" class="min-w-0 flex-1">
        <component :is="Component" />
      </main>
      <ContentLayout v-else as="main" class="flex-1">
        <component :is="Component" />
      </ContentLayout>
    </router-view>
    <AppFooter />
    <CartDrawer />
  </div>
</template>

<style scoped>
.mobile-storefront-sticky {
  position: sticky;
  top: 0;
  z-index: 40;
  background: rgb(var(--color-surface));
  border-bottom: 1px solid rgb(var(--color-border));
  transition: box-shadow .15s ease, border-color .15s ease;
}
.mobile-storefront-sticky--elevated {
  border-color: rgb(var(--color-border-strong));
  box-shadow: 0 2px 8px rgb(15 23 42 / .10);
}
@media (min-width: 1024px) {
  .mobile-storefront-sticky {
    position: static;
    border-bottom: 0;
    box-shadow: none;
  }
}
@media (prefers-reduced-motion: reduce) {
  .mobile-storefront-sticky { transition: none; }
}
</style>
