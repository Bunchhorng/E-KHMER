<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ShoppingBag } from 'lucide-vue-next'
import { ordersApi, type ApiOrderListItem } from '@/api/orders'
import EmptyState from '@/components/EmptyState.vue'
import BasePagination from '@/components/BasePagination.vue'
import AccountOrderList from '@/components/account/AccountOrderList.vue'
import AccountState from '@/components/account/AccountState.vue'

const router = useRouter()
const { t } = useI18n()
const orders = ref<ApiOrderListItem[]>([])
const loading = ref(true)
const error = ref('')
const status = ref('')
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']
let requestId = 0
async function loadOrders(page = 1) {
  const current = ++requestId
  loading.value = true
  error.value = ''
  try {
    const { data } = await ordersApi.list(status.value || undefined, page)
    if (current !== requestId) return
    orders.value = data.data
    meta.value = data.meta
  } catch {
    if (current === requestId) error.value = t('account.ui.load_error')
  } finally {
    if (current === requestId) loading.value = false
  }
}
function resetFilter() { status.value = ''; void loadOrders() }
onMounted(loadOrders)
</script>

<template>
  <div class="space-y-5">
    <div class="account-toolbar">
      <div class="flex items-center gap-3">
        <label for="order-status" class="sr-only">{{ $t('order.status') }}</label>
        <select id="order-status" v-model="status" class="select w-auto min-w-40" @change="loadOrders()"><option value="">{{ $t('account.ui.all_statuses') }}</option><option v-for="item in statuses" :key="item" :value="item">{{ $t('status.' + item) }}</option></select>
      </div>
      <span v-if="!loading && !error" class="chip">{{ $t('order.count_orders', { count: meta.total }, meta.total) }}</span>
    </div>
    <AccountState :loading="loading" :error="error" @retry="loadOrders(meta.current_page)">
      <template v-if="orders.length">
        <AccountOrderList :orders="orders" />
        <BasePagination :page="meta.current_page" :page-count="meta.last_page" :total-items="meta.total" :page-size="meta.per_page" @update:page="loadOrders" />
      </template>
      <EmptyState v-else :title="$t(status ? 'account.ui.filtered_orders_empty' : 'account.no_orders_title')" :description="$t(status ? 'account.ui.filtered_orders_help' : 'account.no_orders_description')" :cta-label="$t(status ? 'account.ui.reset_filter' : 'account.start_shopping')" @cta="status ? resetFilter() : router.push('/shop')"><template #icon><ShoppingBag class="h-7 w-7" /></template></EmptyState>
    </AccountState>
  </div>
</template>
