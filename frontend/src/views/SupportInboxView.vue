<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { MessageSquare, Plus } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import type { PaginatedResponse } from '@/api/catalog'
import { extractErrorMessage } from '@/api/errors'
import BasePagination from '@/components/BasePagination.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import AccountState from '@/components/account/AccountState.vue'

const props = withDefaults(defineProps<{ admin?: boolean }>(), { admin: false })
interface Ticket { id: number; email: string; subject: string; message: string; status: string; reply: string | null }
const { t } = useI18n()
const tickets = ref<Ticket[]>([])
const error = ref('')
const busy = ref(false)
const loading = ref(true)
const replies = ref<Record<number, string>>({})
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
async function load(page = 1) {
  loading.value = true; error.value = ''
  try {
    const { data } = await apiClient.get<PaginatedResponse<Ticket>>(props.admin ? '/admin/support' : '/support', { params: { page } })
    tickets.value = data.data; meta.value = data.meta
  } catch (cause) { error.value = extractErrorMessage(cause, t('account.ui.load_error')) }
  finally { loading.value = false }
}
async function reply(ticket: Ticket) {
  if (busy.value) return
  busy.value = true; error.value = ''
  try { await apiClient.put('/admin/support/' + ticket.id, { reply: replies.value[ticket.id], status: 'answered' }); await load(meta.value.current_page) }
  catch (cause) { error.value = extractErrorMessage(cause, t('account.ui.save_error')) }
  finally { busy.value = false }
}
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <h1 v-if="admin" class="text-2xl font-bold">{{ t('support.inbox') }}</h1>
    <div v-else class="flex justify-end"><RouterLink to="/support" class="btn-primary"><Plus class="h-4 w-4" />{{ t('account.ui.new_request') }}</RouterLink></div>
    <AccountState :loading="loading" :error="error" @retry="load(meta.current_page)">
      <EmptyState v-if="!tickets.length" :title="t('support.empty')" :description="t('account.ui.support_help')"><template #icon><MessageSquare class="h-7 w-7" /></template></EmptyState>
      <section v-for="ticket in tickets" :key="ticket.id" class="card overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border-gray p-5"><div class="min-w-0"><p class="text-xs text-muted">#{{ ticket.id }}</p><h2 class="mt-1 break-words text-base font-semibold">{{ ticket.subject }}</h2><p v-if="admin" class="mt-1 break-all text-xs text-muted">{{ ticket.email }}</p></div><BaseBadge :variant="ticket.reply ? 'success' : 'neutral'">{{ t(ticket.status === 'closed' ? 'account.ui.closed' : ticket.reply ? 'account.ui.answered' : 'account.ui.waiting_reply') }}</BaseBadge></div>
        <div class="space-y-4 p-5"><p class="whitespace-pre-line break-words text-sm leading-relaxed text-muted">{{ ticket.message }}</p>
          <div v-if="ticket.reply" class="rounded-xl border border-primary/10 bg-primary/5 p-4"><p class="mb-2 flex items-center gap-2 text-xs font-semibold text-primary"><MessageSquare class="h-4 w-4" />{{ t('account.ui.team_reply') }}</p><p class="whitespace-pre-line break-words text-sm leading-relaxed">{{ ticket.reply }}</p></div>
          <form v-if="admin" class="space-y-3" @submit.prevent="reply(ticket)"><textarea v-model="replies[ticket.id]" class="textarea" :aria-label="t('support.reply')" required minlength="5" maxlength="5000" rows="3"></textarea><button class="btn-primary" :disabled="busy">{{ t('support.reply') }}</button></form>
        </div>
      </section>
      <BasePagination :page="meta.current_page" :page-count="meta.last_page" :total-items="meta.total" :page-size="meta.per_page" @update:page="load" />
    </AccountState>
  </div>
</template>
