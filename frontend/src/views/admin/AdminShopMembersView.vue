<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Search, Store, Users } from 'lucide-vue-next'
import { adminApi, type AdminShopMember } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'
import BasePagination from '@/components/BasePagination.vue'
import DataTableSkeleton from '@/components/DataTableSkeleton.vue'
import EmptyState from '@/components/EmptyState.vue'
import StatusTag from '@/components/StatusTag.vue'
import { formatDate } from '@/utils/format'

const props = defineProps<{ role: 'owner' | 'manager' }>()

const members = ref<AdminShopMember[]>([])
const loading = ref(true)
const error = ref('')
const query = ref('')
const page = ref(1)
const pageCount = ref(1)
const total = ref(0)

const isOwners = computed(() => props.role === 'owner')
const title = computed(() => isOwners.value ? 'Shop Owners' : 'Shop Admins')
const description = computed(() => isOwners.value
  ? 'View the verified owners responsible for each marketplace shop.'
  : 'View shop managers with administrative access to their shop operations.')

async function load(target = page.value) {
  loading.value = true
  error.value = ''
  try {
    const { data } = await adminApi.listShopMembers({ role: props.role, q: query.value.trim() || undefined, page: target })
    members.value = data.data
    page.value = data.meta.current_page
    pageCount.value = data.meta.last_page
    total.value = data.meta.total
  } catch (cause) {
    error.value = extractErrorMessage(cause, `Could not load ${title.value.toLowerCase()}.`)
  } finally {
    loading.value = false
  }
}

function search() {
  void load(1)
}

watch(() => props.role, () => {
  query.value = ''
  void load(1)
})

onMounted(() => void load())
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-4 sm:space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-[27px]">{{ title }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ description }}</p>
      </div>
      <div class="relative w-full sm:w-72">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input v-model="query" class="input h-9 w-full pl-9 text-xs" :placeholder="`Search ${title.toLowerCase()} or shops...`" @keyup.enter="search" />
      </div>
    </section>

    <DataTableSkeleton v-if="loading" :rows="7" :columns="5" />
    <div v-else-if="error" class="card p-8 text-center">
      <p class="text-red-600">{{ error }}</p>
      <button class="btn-secondary btn-sm mt-4" @click="load()">Retry</button>
    </div>
    <section v-else-if="members.length" class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-sm">
          <thead class="border-b border-border-gray bg-canvas/60 text-left text-[11px] uppercase tracking-wide text-gray-500 dark:text-muted">
            <tr><th class="px-4 py-3">{{ isOwners ? 'Owner' : 'Admin' }}</th><th class="px-4 py-3">Shop</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Membership</th><th class="px-4 py-3">Joined</th></tr>
          </thead>
          <tbody class="divide-y divide-border-gray">
            <tr v-for="member in members" :key="member.id" class="transition-colors hover:bg-canvas/50">
              <td class="px-4 py-3"><div class="flex items-center gap-3"><img v-if="member.user.avatar" :src="member.user.avatar" :alt="member.user.name" class="h-9 w-9 rounded-full border border-border-gray object-cover" /><div v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{{ member.user.name.charAt(0) }}</div><p class="font-semibold text-ink">{{ member.user.name }}</p></div></td>
              <td class="px-4 py-3"><div class="flex items-center gap-2.5"><img v-if="member.shop.logo" :src="member.shop.logo" :alt="member.shop.name" class="h-8 w-8 rounded-lg border border-border-gray object-cover" /><div v-else class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary"><Store class="h-4 w-4" /></div><div><p class="font-medium text-ink">{{ member.shop.name }}</p><p class="text-xs text-gray-500 dark:text-muted">{{ member.shop.slug }}</p></div></div></td>
              <td class="px-4 py-3"><p class="text-xs font-medium text-ink">{{ member.user.email }}</p><p class="text-xs text-gray-500 dark:text-muted">{{ member.user.phone || '—' }}</p></td>
              <td class="px-4 py-3"><StatusTag :status="member.status" /></td>
              <td class="px-4 py-3 text-xs text-gray-500 dark:text-muted">{{ formatDate(member.joined_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="border-t border-border-gray p-4"><BasePagination :page="page" :page-count="pageCount" :total-items="total" :page-size="20" @update:page="load" /></div>
    </section>
    <EmptyState v-else :title="`No ${title.toLowerCase()} found`" description="Try changing your search term."><template #icon><Users class="h-10 w-10 text-gray-300" /></template></EmptyState>
  </div>
</template>
