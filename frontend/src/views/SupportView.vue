<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import { extractErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
const { t } = useI18n()
const form = reactive({ email: useAuthStore().user?.email ?? '', subject: '', message: '' })
const busy = ref(false); const error = ref(''); const ticket = ref<number | null>(null)
async function submit() {
  if (busy.value) return
  busy.value = true; error.value = ''
  try { ticket.value = (await apiClient.post<{ data: { id: number } }>('/support', form)).data.data.id; form.message = ''; form.subject = '' }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busy.value = false }
}
</script>
<template>
  <main class="container-app max-w-2xl space-y-6 py-10"><h1 class="text-3xl font-bold">{{ t('support.title') }}</h1><p class="text-sm text-muted">{{ t('support.help') }}</p><p v-if="ticket" role="status" class="card p-5 text-emerald-700">{{ t('support.sent', { id: ticket }) }}</p><p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p><form class="card space-y-5 p-6" @submit.prevent="submit"><label class="block"><span class="label">{{ t('shop_settings.email') }}</span><input v-model="form.email" type="email" class="input w-full" required maxlength="190" /></label><label class="block"><span class="label">{{ t('support.subject') }}</span><input v-model="form.subject" class="input w-full" required maxlength="190" /></label><label class="block"><span class="label">{{ t('support.message') }}</span><textarea v-model="form.message" class="textarea w-full" rows="6" required minlength="10" maxlength="5000"></textarea></label><button class="btn-primary" :disabled="busy">{{ t('support.send') }}</button></form></main>
</template>

