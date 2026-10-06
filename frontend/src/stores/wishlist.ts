import { defineStore } from 'pinia'
import { wishlistApi } from '@/api'
import type { CatalogProduct } from '@/api'

const LOCAL_STORAGE_KEY = 'ekhmer_wishlist'

function load(): number[] {
  try {
    const raw = localStorage.getItem(LOCAL_STORAGE_KEY)
    return raw ? (JSON.parse(raw) as number[]) : []
  } catch {
    return []
  }
}

function save(ids: number[]) {
  try {
    localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(ids))
  } catch {
    /* ignore */
  }
}

export const useWishlistStore = defineStore('wishlist', {
  state: () => ({
    productIds: load() as number[],
    products: [] as CatalogProduct[],
    loading: false
  }),

  getters: {
    count(state): number {
      return state.productIds.length
    },
    isWishlisted: (state) => (productId: string): boolean => {
      const numId = Number(productId)
      return state.productIds.includes(numId)
    }
  },

  actions: {
    async fetchProducts() {
      this.loading = true
      try {
        const ids = this.productIds
        if (ids.length === 0) {
          this.products = []
          return
        }
        const { data } = await wishlistApi.list()
        this.products = data.data
      } catch {
        this.products = []
      } finally {
        this.loading = false
      }
    },

    async toggle(productId: string) {
      const numId = Number(productId)
      const idx = this.productIds.indexOf(numId)
      const previous = [...this.productIds]

      try {
        const response = idx >= 0
          ? await wishlistApi.remove(numId)
          : await wishlistApi.add(numId)

        // Treat the API response as authoritative. Optimistic local-only state
        // previously made an item appear saved even after a rejected request.
        this.productIds = response.data.data
        save(this.productIds)
        await this.fetchProducts()
        return idx < 0
      } catch {
        this.productIds = previous
        save(this.productIds)
        return idx < 0 ? false : true
      }
    },

    async remove(productId: string) {
      const numId = Number(productId)
      const previous = [...this.productIds]
      try {
        const { data } = await wishlistApi.remove(numId)
        this.productIds = data.data
        save(this.productIds)
        await this.fetchProducts()
      } catch {
        this.productIds = previous
        save(this.productIds)
      }
    },

    clear() {
      this.productIds = []
      this.products = []
      save(this.productIds)
    }
  }
})
