<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { categoriesApi } from '@/api'

const route = useRoute()

const navCategories = ref<{ id: number; name: string; slug: string }[]>([])

onMounted(async () => {
  try {
    const { data } = await categoriesApi.getTree()
    navCategories.value = data.data
  } catch {
    navCategories.value = []
  }
})

function isActive(slug: string): boolean {
  return route.query.category === slug
}
</script>

<template>
  <nav class="border-b border-border-gray bg-white dark:bg-canvas">
    <div class="container-app">
      <ul
        class="flex items-center gap-1 overflow-x-auto whitespace-nowrap py-2.5 text-sm font-medium [scrollbar-width:none]"
      >
        <li>
          <RouterLink to="/shop" class="nav-link" exact-active-class="!bg-primary/10 !text-primary dark:!bg-primary/15">
            {{ $t('nav.all_products') }}
          </RouterLink>
        </li>
        <li><RouterLink to="/shops" class="nav-link" exact-active-class="!bg-primary/10 !text-primary">{{ $t('storefront.title') }}</RouterLink></li>
        <li v-for="category in navCategories" :key="category.id">
          <RouterLink
            :to="{ name: 'shop', query: { category: category.slug } }"
            class="nav-link"
            :class="isActive(category.slug) ? '!bg-primary/10 !text-primary dark:!bg-primary/15' : ''"
          >
            {{ $te(`categories.${category.slug.replace(/-/g, '_')}`) ? $t(`categories.${category.slug.replace(/-/g, '_')}`) : category.name }}
          </RouterLink>
        </li>
      </ul>
    </div>
  </nav>
</template>
