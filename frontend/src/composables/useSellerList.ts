import { onBeforeUnmount, ref, shallowRef } from 'vue'
import type { AxiosResponse } from 'axios'
import type { PaginatedResponse } from '@/api/catalog'
import { extractErrorMessage } from '@/api/errors'

/** Keep earlier searches and unmounted shop screens from replacing current data. */
export function useSellerList<T>(fetchPage: (page: number) => Promise<AxiosResponse<PaginatedResponse<T>>>, fallback: () => string) {
  const items = shallowRef<T[]>([])
  const loading = ref(true)
  const error = ref('')
  const page = ref(1)
  const pageCount = ref(1)
  const pageSize = ref(15)
  const total = ref(0)
  let request = 0
  onBeforeUnmount(() => { request++ })
  async function load(target = page.value) {
    const current = ++request
    loading.value = true
    error.value = ''
    try {
      const { data } = await fetchPage(target)
      if (current !== request) return
      items.value = data.data
      page.value = data.meta.current_page
      pageCount.value = data.meta.last_page
      pageSize.value = data.meta.per_page
      total.value = data.meta.total
    } catch (cause) {
      if (current === request) error.value = extractErrorMessage(cause, fallback())
    } finally {
      if (current === request) loading.value = false
    }
  }
  return { items, loading, error, page, pageCount, pageSize, total, load }
}
