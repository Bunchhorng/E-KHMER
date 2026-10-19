<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Truck, Pencil, Plus } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { AdminShippingMethod } from '@/api/admin'
import { formatPrice } from '@/utils/format'

const { t } = useI18n()

const loading = ref(true)
const correctionsEnabled = ref(false)
const methods = ref<AdminShippingMethod[]>([])
const updatingIds = ref(new Set<number>())

async function loadMethods() {
  loading.value = true
  try {
    const { data: resp } = await adminApi.listShippingMethods()
    methods.value = resp.data
  } catch {
    showToast(t('admin.shipping.toast_load_error'))
  } finally {
    loading.value = false
  }
}

async function toggleEnabled(m: AdminShippingMethod) {
  if (updatingIds.value.has(m.id)) return
  const newState = !m.is_active
  updatingIds.value.add(m.id)
  try {
    const { data } = await adminApi.updateShippingMethod(m.id, {
      name: m.name,
      code: m.code,
      price: m.price,
      is_active: newState
    })
    Object.assign(m, data.data)
  } catch {
    showToast(t('admin.shipping.toast_update_error'))
  } finally {
    updatingIds.value.delete(m.id)
  }
}

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

onMounted(loadMethods)
</script>

<template>
  <div class="space-y-6">
    <section class="card space-y-3 border-primary/20 p-4"><p class="text-sm text-muted">{{ t('admin.shipping.activation_help') }}</p><label class="flex items-center gap-2 text-sm font-medium"><input v-model="correctionsEnabled" type="checkbox" class="accent-primary" />{{ t('admin.overview.enable_corrections') }}</label></section>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-ink">{{ $t('admin.shipping.title') }}</h1>
        <span class="chip">{{ $t('admin.shipping.total_count', { count: methods.length }) }}</span>
      </div>
      <router-link v-if="correctionsEnabled" :to="{ name: 'admin-shipping-create' }" class="btn-primary btn-sm w-fit">
        <Plus class="h-4 w-4" />
        {{ $t('admin.shipping.add_method') }}
      </router-link>
    </div>

    <div v-if="loading" class="card p-10 text-center">
      <p class="text-sm text-gray-500 dark:text-muted">{{ $t('common.loading') }}</p>
    </div>

    <div v-else-if="methods.length" class="grid gap-5 lg:grid-cols-3">
      <div v-for="m in methods" :key="m.id" class="card flex flex-col p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Truck class="h-5 w-5" />
          </div>
          <button
            type="button"
            role="switch"
            :disabled="updatingIds.has(m.id)"
            :aria-busy="updatingIds.has(m.id)"
            :aria-checked="m.is_active"
            :aria-label="`${$t('admin.shipping.toggle_aria_label')}: ${m.name}`"
            class="relative h-6 w-11 shrink-0 rounded-full transition-colors disabled:cursor-wait disabled:opacity-60"
            :class="m.is_active ? 'bg-success' : 'bg-gray-200 dark:bg-surface-hover'"
            @click="toggleEnabled(m)"
          >
            <span
              class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all"
              :class="m.is_active ? 'left-[22px]' : 'left-0.5'"
            ></span>
          </button>
        </div>

        <h3 class="mt-4 text-base font-semibold text-ink">{{ m.name }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ m.description }}</p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
          <span class="chip">{{ $t('admin.shipping.days', { count: m.estimated_days_max ?? 0 }) }}</span>
          <span
            v-if="m.price === 0"
            class="inline-flex items-center rounded-full bg-accent/20 px-3 py-1 text-xs font-semibold text-amber-700"
          >
            {{ $t('admin.shipping.free') }}
          </span>
          <span v-else class="text-sm font-semibold text-ink">{{ formatPrice(m.price) }}</span>
        </div>

        <div class="mt-auto flex items-center justify-between border-t border-border-gray pt-4">
          <span class="text-xs font-medium" :class="m.is_active ? 'text-emerald-600' : 'text-gray-400'">
            {{ m.is_active ? $t('admin.shipping.enabled') : $t('admin.shipping.disabled') }}
          </span>
          <router-link
            v-if="correctionsEnabled"
            class="btn-secondary btn-sm"
            :to="{ name: 'admin-shipping-edit', params: { id: m.id } }"
          >
            <Pencil class="h-4 w-4" />
            {{ $t('actions.edit') }}
          </router-link>
        </div>
      </div>
    </div>

    <transition name="fade">
      <div
        v-if="toast"
        class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover"
      >
        {{ toast }}
      </div>
    </transition>
  </div>
</template>
