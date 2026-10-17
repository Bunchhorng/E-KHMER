<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import type { PaginatedResponse } from '@/api/catalog'
import { extractErrorMessage } from '@/api/errors'
import BasePagination from '@/components/BasePagination.vue'
const props = withDefaults(defineProps<{ admin?: boolean }>(), { admin: false })
interface Ticket { id: number; email: string; subject: string; message: string; status: string; reply: string | null }
const { t } = useI18n()
const tickets = ref<Ticket[]>([]); const error = ref(''); const busy = ref(false); const replies = ref<Record<number, string>>({})
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
async function load(page = 1) {
  try { const data = (await apiClient.get<PaginatedResponse<Ticket>>(props.admin ? '/admin/support' : '/support', { params: { page } })).data; tickets.value = data.data; meta.value = data.meta }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
}
async function reply(ticket: Ticket) {
  if (busy.value) return
  busy.value = true; error.value = ''
  try { await apiClient.put(`/admin/support/${ticket.id}`, { reply: replies.value[ticket.id], status: 'answered' }); await load(meta.value.current_page) }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busy.value = false }
}
onMounted(load)
</script>
<template><main class="mx-auto max-w-5xl space-y-5 p-5"><h1 class="text-2xl font-bold">{{ t('support.inbox') }}</h1><p v-if="error" role="alert" class="text-red-600">{{ error }}</p><p v-if="!tickets.length" class="card p-6 text-sm text-muted">{{ t('support.empty') }}</p><section v-for="ticket in tickets" :key="ticket.id" class="card space-y-3 p-5"><h2 class="font-semibold">#{{ ticket.id }} · {{ ticket.subject }}</h2><p class="text-xs text-muted">{{ ticket.email }} · {{ ticket.status }}</p><p class="whitespace-pre-line text-sm">{{ ticket.message }}</p><p v-if="ticket.reply" class="rounded-xl bg-primary/5 p-4 text-sm">{{ ticket.reply }}</p><form v-if="admin" class="space-y-3" @submit.prevent="reply(ticket)"><textarea v-model="replies[ticket.id]" class="textarea w-full" :aria-label="t('support.reply')" required minlength="5" maxlength="5000"></textarea><button class="btn-primary" :disabled="busy">{{ t('support.reply') }}</button></form></section><BasePagination :page="meta.current_page" :page-count="meta.last_page" :total-items="meta.total" :page-size="meta.per_page" @update:page="load" /></main></template>

