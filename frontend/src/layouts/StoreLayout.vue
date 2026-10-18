<script setup lang="ts">
import AppHeader from '@/components/AppHeader.vue'
import CategoryNavBar from '@/components/CategoryNavBar.vue'
import AppFooter from '@/components/AppFooter.vue'
import CartDrawer from '@/components/CartDrawer.vue'
import ContentLayout from '@/layouts/ContentLayout.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <AppHeader />
    <CategoryNavBar />
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
