<script setup lang="ts">
import { useRouter } from 'vue-router'
import { MapPin, Pencil, Phone, Plus, Trash2 } from 'lucide-vue-next'
import { addressesApi } from '@/api/addresses'
import BaseBadge from '@/components/BaseBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import BaseModal from '@/components/BaseModal.vue'
import AccountState from '@/components/account/AccountState.vue'
import { useI18n } from 'vue-i18n'
import { onMounted, ref } from 'vue'
import type { Address } from '@/types'

const { t } = useI18n()
const router = useRouter()

const addresses = ref<Address[]>([])
const loading = ref(true)
const deleteError = ref('')
const deletingId = ref<string | null>(null)
const error = ref('')
const pendingDelete = ref<Address | null>(null)

function mapAddress(raw: { id: number; label: string | null; full_name: string; phone: string | null; address_line1: string; address_line2: string | null; city: string; state: string; postal_code: string; country: string; is_default: boolean }): Address {
  return {
    id: String(raw.id),
    label: raw.label ?? '',
    fullName: raw.full_name,
    line1: raw.address_line1,
    line2: raw.address_line2 ?? undefined,
    city: raw.city,
    state: raw.state,
    postalCode: raw.postal_code,
    country: raw.country,
    phone: raw.phone ?? '',
    isDefault: raw.is_default
  }
}

async function fetchAddresses() {
  loading.value = true
  error.value = ''
  try {
    const res = await addressesApi.list()
    addresses.value = (res.data.data ?? []).map(mapAddress)
  } catch {
    error.value = t('account.ui.load_error')
  } finally {
    loading.value = false
  }
}

onMounted(fetchAddresses)

async function removeAddress(address: Address) {
  if (deletingId.value) return
  deleteError.value = ''
  deletingId.value = address.id
  try {
    await addressesApi.remove(Number(address.id))
    pendingDelete.value = null
    await fetchAddresses()
  } catch {
    deleteError.value = t('account.ui.action_error')
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-end">
      <router-link :to="{ name: 'account-address-create' }" class="btn-primary btn-sm w-fit">
        <Plus class="h-4 w-4" />
        {{ $t('checkout.add_new_address') }}
      </router-link>
    </div>

    <AccountState :loading="loading" :error="error" @retry="fetchAddresses">
    <div v-if="addresses.length" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
      <div v-for="a in addresses" :key="a.id" class="card flex flex-col p-5" :class="{ '!border-primary/40': a.isDefault }">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <MapPin class="h-5 w-5" />
          </span>
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="a.label" class="chip">{{ a.label }}</span>
            <BaseBadge v-if="a.isDefault" variant="success" dot>{{ $t('account.default') }}</BaseBadge>
          </div>
        </div>
        <p class="mt-3 font-semibold text-ink">{{ a.fullName }}</p>
        <div class="mt-1 space-y-0.5 text-sm text-gray-600 dark:text-muted">
          <p>{{ a.line1 }}</p>
          <p v-if="a.line2">{{ a.line2 }}</p>
          <p>{{ a.city }}, {{ a.state }} {{ a.postalCode }}</p>
          <p>{{ a.country }}</p>
        </div>
        <p v-if="a.phone" class="mt-2 flex items-center gap-1.5 text-sm text-gray-500 dark:text-muted">
          <Phone class="h-3.5 w-3.5 shrink-0" />
          {{ a.phone }}
        </p>
        <div class="mt-4 flex justify-end gap-1 border-t border-border-gray pt-3">
          <button type="button" class="btn-ghost btn-sm" @click="router.push({ name: 'account-address-edit', params: { id: a.id } })">
            <Pencil class="h-4 w-4" />
            {{ $t('actions.edit') }}
          </button>
          <button
            type="button"
            class="btn-ghost btn-sm text-red-500 hover:text-red-600"
            :disabled="deletingId !== null"
            @click="pendingDelete = a; deleteError = ''"
          >
            <Trash2 class="h-4 w-4" />
            {{ deletingId === a.id ? $t('common.loading') : $t('actions.delete') }}
          </button>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('account.ui.no_addresses')"
      :description="$t('account.ui.no_addresses_help')"
      :cta-label="$t('checkout.add_new_address')"
      @cta="router.push({ name: 'account-address-create' })"
    >
      <template #icon>
        <Plus class="h-10 w-10 text-gray-300" />
      </template>
    </EmptyState>
    </AccountState>
    <BaseModal :model-value="!!pendingDelete" :title="$t('account.ui.delete_address')" size="sm" @update:model-value="!deletingId && (pendingDelete = null)">
      <p class="text-sm text-muted">{{ $t('account.ui.delete_address_help') }}</p><p class="mt-3 text-sm font-semibold">{{ pendingDelete?.fullName }}</p><p class="mt-1 text-sm text-muted">{{ pendingDelete?.line1 }}</p>
      <p v-if="deleteError" role="alert" class="mt-3 text-sm text-red-500">{{ deleteError }}</p>
      <template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary" :disabled="!!deletingId" @click="pendingDelete = null">{{ $t('actions.cancel') }}</button><button type="button" class="btn-danger" :disabled="!!deletingId" @click="pendingDelete && removeAddress(pendingDelete)">{{ $t(deletingId ? 'common.loading' : 'actions.delete') }}</button></div></template>
    </BaseModal>
  </div>
</template>
