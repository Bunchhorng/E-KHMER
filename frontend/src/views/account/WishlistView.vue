<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { Heart } from 'lucide-vue-next'
import EmptyState from '@/components/EmptyState.vue'
import ProductGrid from '@/components/ProductGrid.vue'
import AccountState from '@/components/account/AccountState.vue'
import { useWishlistStore } from '@/stores/wishlist'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { mapCatalogProduct } from '@/utils/product'
import type { Product } from '@/types'

const router = useRouter()
const wishlist = useWishlistStore()
const { t } = useI18n()
const actionError = ref('')

const count = computed(() => wishlist.count)

const products = computed(() => wishlist.products.map(mapCatalogProduct))

async function removeAddedProduct(product: Product) {
  actionError.value = ''
  try {
    await wishlist.remove(product.id)
  } catch { actionError.value = t('account.ui.action_error') }
}
</script>

<template>
  <div class="space-y-6">
    <div class="account-toolbar">
      <span class="chip">{{ $t('account.ui.saved_count', { count }, count) }}</span>
      <RouterLink to="/shop" class="btn-outline btn-sm w-fit">{{ $t('account.add_from_collection') }}</RouterLink>
    </div>

    <p v-if="actionError" role="alert" class="text-sm text-red-500">{{ actionError }}</p>
    <AccountState :loading="wishlist.loading" :error="wishlist.error ? $t('account.ui.load_error') : ''" @retry="wishlist.fetchProducts()">
    <ProductGrid
      v-if="products.length"
      :products="products"
      @added-to-cart="removeAddedProduct($event.product)"
      @wishlist-toggle="wishlist.toggle($event)"
    />

    <EmptyState
      v-else
      :title="$t('account.wishlist_cta_title')"
      :description="$t('account.wishlist_cta_description')"
      :cta-label="$t('account.start_shopping')"
      @cta="router.push('/shop')"
    >
      <template #icon>
        <Heart class="h-10 w-10 text-gray-300" />
      </template>
    </EmptyState>
    </AccountState>
  </div>
</template>
