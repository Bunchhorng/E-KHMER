<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const authStore = useAuthStore()
const cartStore = useCartStore()

onMounted(async () => {
  await cartStore.initialize()
})

watch(
  () => authStore.isAuthenticated,
  async () => {
    await cartStore.fetch()
  }
)
</script>

<template>
  <router-view />
  <div v-if="cartStore.mutationError" role="alert" class="fixed inset-x-4 bottom-4 z-[70] mx-auto flex max-w-lg items-center gap-4 rounded-xl border border-red-300 bg-white p-4 text-sm text-red-700 shadow-lg dark:bg-surface dark:text-red-300">
    <p class="flex-1">{{ cartStore.mutationError }}</p>
    <button type="button" class="shrink-0 underline" @click="cartStore.mutationError = ''">{{ $t('cart.dismiss') }}</button>
  </div>
</template>
