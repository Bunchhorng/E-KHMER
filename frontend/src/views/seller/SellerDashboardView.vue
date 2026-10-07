<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronDown, Package, ShoppingBag, Store, Warehouse } from 'lucide-vue-next'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { sellerApi, type SellerDashboard, type SellerShop } from '@/api/seller'
import { extractErrorMessage } from '@/api/errors'

const route = useRoute()
const router = useRouter()
const dashboard = ref<SellerDashboard | null>(null)
const shops = ref<SellerShop[]>([])
const loading = ref(true)
const error = ref('')
const shopId = computed(() => Number(route.params.id) || shops.value[0]?.id || 0)

async function load() {
  loading.value = true
  error.value = ''

  try {
    shops.value = (await sellerApi.listShops()).data.data
    dashboard.value = shopId.value ? (await sellerApi.dashboardForShop(shopId.value)).data.data : null
  } catch (cause) {
    error.value = extractErrorMessage(cause, 'Your shop could not be loaded.')
  } finally {
    loading.value = false
  }
}

function selectShop(event: Event) {
  void router.push({ name: 'seller-shop-dashboard', params: { id: Number((event.target as HTMLSelectElement).value) } })
}

watch(() => route.params.id, load)
onMounted(load)
</script>

<template>
  <div class="container-app mx-auto py-10">
    <header class="mb-7 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold text-primary">SELLER CENTER</p>
        <h1 class="mt-1 text-3xl font-bold text-ink">{{ dashboard?.shop.name ?? 'My shops' }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted">Manage each shop independently.</p>
      </div>
      <div class="flex items-center gap-2">
        <div v-if="shops.length > 1" class="relative">
          <Store class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
          <select :value="shopId" class="select h-9 min-w-52 pl-9 text-sm" @change="selectShop">
            <option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option>
          </select>
          <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2" />
        </div>
        <RouterLink to="/seller/application" class="btn-secondary btn-sm">Add a shop</RouterLink>
      </div>
    </header>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-500">Loading shop dashboard…</div>
    <div v-else-if="error" class="card p-8 text-center">
      <p class="text-red-600">{{ error }}</p>
      <button class="btn-secondary btn-sm mt-4" @click="load">Retry</button>
    </div>
    <div v-else-if="!shops.length" class="card p-10 text-center">
      <Store class="mx-auto h-10 w-10 text-gray-300" />
      <h2 class="mt-3 font-semibold text-ink">No active shops yet</h2>
      <RouterLink to="/seller/application" class="btn-primary btn-sm mt-4">Create shop application</RouterLink>
    </div>

    <template v-else-if="dashboard">
      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="item in [
          { label: 'Products', value: dashboard.metrics.products },
          { label: 'Active products', value: dashboard.metrics.active_products },
          { label: 'Inactive products', value: dashboard.metrics.inactive_products },
          { label: 'Low stock', value: dashboard.metrics.low_stock }
        ]" :key="item.label" class="card p-5">
          <p class="text-sm text-gray-500">{{ item.label }}</p>
          <p class="mt-2 text-3xl font-bold text-ink">{{ item.value }}</p>
        </div>
      </section>

      <section class="mt-6 grid gap-4 md:grid-cols-3">
        <RouterLink :to="{ name: 'seller-shop-products', params: { id: shopId } }" class="card block p-6 transition hover:border-primary/40 hover:shadow-card">
          <Package class="h-6 w-6 text-primary" />
          <h2 class="mt-3 font-bold text-ink">Manage products</h2>
          <p class="mt-1 text-sm text-gray-500">View the catalog items that belong only to {{ dashboard.shop.name }}.</p>
        </RouterLink>
        <RouterLink :to="{ name: 'seller-shop-inventory', params: { id: shopId } }" class="card block p-6 transition hover:border-primary/40 hover:shadow-card">
          <Warehouse class="h-6 w-6 text-primary" />
          <h2 class="mt-3 font-bold text-ink">Manage inventory</h2>
          <p class="mt-1 text-sm text-gray-500">Adjust on-hand stock and inspect the shop inventory ledger.</p>
        </RouterLink>
        <RouterLink :to="{ name: 'seller-shop-orders', params: { id: shopId } }" class="card block p-6 transition hover:border-primary/40 hover:shadow-card">
          <ShoppingBag class="h-6 w-6 text-primary" />
          <h2 class="mt-3 font-bold text-ink">View shop orders</h2>
          <p class="mt-1 text-sm text-gray-500">Review orders and customer allocations for this shop.</p>
        </RouterLink>
      </section>
    </template>
  </div>
</template>
