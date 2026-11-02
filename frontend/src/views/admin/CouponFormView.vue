<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Save } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'

const { t } = useI18n()

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => Boolean(route.params.id))
const couponId = computed(() => (isEdit.value ? Number(route.params.id) : null))

const form = reactive({
  code: '',
  type: 'percentage' as 'percentage' | 'fixed',
  value: '',
  minOrderAmount: '',
  maxDiscountAmount: '',
  usageLimit: '',
  perUserLimit: '1',
  expiresAt: '',
  status: 'Active'
})

const errors = reactive<Record<string, string>>({})

const formTitle = computed(() =>
  isEdit.value
    ? t('admin.coupons.edit_coupon')
    : t('admin.coupons.create_coupon')
)

const saving = ref(false)
const loading = ref(isEdit.value)
const originalExpiry = ref<string | null>(null)
const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined

function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

onMounted(async () => {
  if (!isEdit.value || !couponId.value) return
  try {
    const { data: resp } = await adminApi.getCoupon(couponId.value)
    const coupon = resp.data
    form.code = coupon.code
    form.type = coupon.type
    form.value = String(coupon.value)
    form.minOrderAmount = coupon.min_order_amount != null ? String(coupon.min_order_amount) : ''
    form.maxDiscountAmount = coupon.max_discount_amount != null ? String(coupon.max_discount_amount) : ''
    form.usageLimit = coupon.usage_limit != null ? String(coupon.usage_limit) : ''
    form.perUserLimit = coupon.per_user_limit === null ? '' : String(coupon.per_user_limit ?? 1)
    form.expiresAt = coupon.expires_at ? coupon.expires_at.slice(0, 10) : ''
    originalExpiry.value = coupon.expires_at
    form.status = coupon.is_active ? 'Active' : 'Draft'
  } catch {
    if (isEdit.value) {
      showToast(t('admin.coupons.toast_load_error'))
      router.replace({ name: 'admin-coupons' })
    }
  } finally { loading.value = false }
})
onBeforeUnmount(() => { if (toastTimer) clearTimeout(toastTimer) })

function validate(): boolean {
  Object.keys(errors).forEach(key => { delete errors[key] })
  const value = Number(form.value)
  if (!form.code.trim()) {
    errors.code = t('admin.coupons.toast_fill_required')
  } else {
    errors.code = ''
  }
  errors.value = Number.isFinite(value) && value > 0 ? '' : t('admin.coupons.toast_fill_required')
  errors.per_user_limit = !form.perUserLimit || (Number.isInteger(Number(form.perUserLimit)) && Number(form.perUserLimit) >= 1) ? '' : 'Enter a positive whole number or leave blank.'
  return Object.values(errors).every((v) => v === '')
}

async function save() {
  if (saving.value || loading.value) return
  if (!validate()) {
    showToast(t('admin.coupons.toast_fill_required'))
    return
  }

  const payload: Record<string, unknown> = {
    code: form.code.trim().toUpperCase(),
    type: form.type,
    value: Number(form.value),
    min_order_amount: form.minOrderAmount ? Number(form.minOrderAmount) : null,
    max_discount_amount: form.maxDiscountAmount ? Number(form.maxDiscountAmount) : null,
    usage_limit: form.usageLimit ? Number(form.usageLimit) : null,
    per_user_limit: form.perUserLimit ? Number(form.perUserLimit) : null,
    expires_at: originalExpiry.value && form.expiresAt === originalExpiry.value.slice(0, 10) ? originalExpiry.value : form.expiresAt || null,
    is_active: form.status === 'Active'
  }

  try {
    saving.value = true
    const targetId = couponId.value

    if (isEdit.value && targetId) {
      await adminApi.updateCoupon(targetId, payload)
      showToast(t('admin.coupons.toast_updated', { code: form.code }))
    } else {
      await adminApi.createCoupon(payload)
      showToast(t('admin.coupons.toast_created'))
    }
    router.push({ name: 'admin-coupons' })
  } catch (error) {
    Object.assign(errors, extractFieldErrors(error))
    showToast(extractErrorMessage(error, t('admin.coupons.toast_update_error')))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="space-y-6 pb-16" @submit.prevent="save()">
    <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="section-eyebrow">{{ $t('admin.nav.group_marketing') }}</div>
        <h1 class="text-xl font-bold text-ink">{{ formTitle }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn-secondary btn-sm" type="button" @click="router.push({ name: 'admin-coupons' })">{{ $t('actions.cancel') }}</button>
        <button class="btn-primary btn-sm" type="submit" :disabled="saving || loading">
          <Save class="h-4 w-4" />
          {{ $t(saving ? 'common.saving' : 'admin.coupons.save_coupon') }}
        </button>
      </div>
    </div>

    <fieldset class="card p-6" :disabled="saving || loading">
      <div class="section-eyebrow">{{ $t('admin.products.step_1') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.basic_information') }}</h2>

      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="label" for="cp-per-customer">Uses per customer</label>
          <input id="cp-per-customer" v-model="form.perUserLimit" type="number" min="1" step="1" class="input" />
          <p class="mt-1 text-sm text-muted">A limit requires sign-in. Leave blank to allow guest checkout without a per-customer limit. Other coupon rules still apply.</p>
          <p v-if="errors.per_user_limit" role="alert" class="mt-1 text-xs text-red-600">{{ errors.per_user_limit }}</p>
        </div>
        <div>
          <label class="label" for="cp-code">{{ $t('admin.coupons.code_label') }}</label>
          <input
            id="cp-code"
            v-model="form.code"
            class="input"
            :class="{ 'input-error': errors.code }"
            maxlength="50"
            :aria-invalid="Boolean(errors.code)"
            :aria-describedby="errors.code ? 'cp-code-error' : undefined"
            placeholder="SUMMER30"
          />
          <p v-if="errors.code" id="cp-code-error" role="alert" class="mt-1 text-xs text-red-600">{{ errors.code }}</p>
        </div>

        <div>
          <label class="label" for="cp-status">{{ $t('admin.products.status_label') }}</label>
          <select id="cp-status" v-model="form.status" class="select">
            <option value="Active">{{ $t('status.active') }}</option>
            <option value="Draft">{{ $t('status.draft') }}</option>
          </select>
        </div>

        <div>
          <label class="label" for="cp-type">{{ $t('admin.coupons.type_label') }}</label>
          <select id="cp-type" v-model="form.type" class="select">
            <option value="percentage">{{ $t('admin.coupons.type_percentage') }}</option>
            <option value="fixed">{{ $t('admin.coupons.type_fixed_option') }}</option>
          </select>
        </div>

        <div>
          <label class="label" for="cp-value">{{ $t('admin.coupons.value_label') }}</label>
          <input
            id="cp-value"
            v-model="form.value"
            class="input"
            :class="{ 'input-error': errors.value }"
            type="number"
            min="0"
            step="0.01"
            placeholder="10"
          />
          <p v-if="errors.value" role="alert" class="mt-1 text-xs text-red-600">{{ errors.value }}</p>
        </div>

        <div>
          <label class="label" for="cp-min">{{ $t('admin.coupons.min_order_label') }}</label>
          <input id="cp-min" v-model="form.minOrderAmount" class="input" type="number" min="0" step="0.01" placeholder="50" />
          <p v-if="errors.min_order_amount" role="alert" class="mt-1 text-xs text-red-600">{{ errors.min_order_amount }}</p>
        </div>

        <div>
          <label class="label" for="cp-max">{{ $t('admin.coupons.max_discount_label') }}</label>
          <input id="cp-max" v-model="form.maxDiscountAmount" class="input" type="number" min="0" step="0.01" />
          <p v-if="errors.max_discount_amount" role="alert" class="mt-1 text-xs text-red-600">{{ errors.max_discount_amount }}</p>
        </div>

        <div>
          <label class="label" for="cp-limit">{{ $t('admin.coupons.usage_limit_label') }}</label>
          <input id="cp-limit" v-model="form.usageLimit" class="input" type="number" min="1" placeholder="1000" />
          <p v-if="errors.usage_limit" role="alert" class="mt-1 text-xs text-red-600">{{ errors.usage_limit }}</p>
        </div>

        <div>
          <label class="label" for="cp-expires">{{ $t('admin.coupons.expires_label') }}</label>
          <input id="cp-expires" v-model="form.expiresAt" class="input" type="date" />
          <p v-if="errors.expires_at" role="alert" class="mt-1 text-xs text-red-600">{{ errors.expires_at }}</p>
        </div>
      </div>
    </fieldset>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <button class="btn-secondary" type="button" @click="router.push({ name: 'admin-coupons' })">{{ $t('actions.cancel') }}</button>
      <button class="btn-primary" type="submit" :disabled="saving || loading">
        <Save class="h-4 w-4" />
        {{ $t(saving ? 'common.saving' : 'admin.coupons.save_coupon') }}
      </button>
    </div>

    <transition name="fade">
      <div
        v-if="toast"
        class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover"
      >
        {{ toast }}
      </div>
    </transition>
  </form>
</template>
