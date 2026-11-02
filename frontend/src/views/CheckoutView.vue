<script setup lang="ts">
import { ref, computed, reactive, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { isAxiosError } from 'axios'
import {
  ArrowRight,
  ArrowLeft,
  Plus,
  Lock,
  Check,
  Banknote,
  Landmark,
} from 'lucide-vue-next'
import type { Address, ShippingMethod, PaymentMethod } from '@/types'
import { addressesApi, type ApiAddress } from '@/api/addresses'
import { shippingApi, type ApiShippingMethod } from '@/api/shipping'
import { checkoutApi, type CheckoutAddress, type CheckoutPayload } from '@/api/checkout'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/BaseModal.vue'
import EmptyState from '@/components/EmptyState.vue'
import CartLoadState from '@/components/CartLoadState.vue'
import QuoteSummary from '@/components/QuoteSummary.vue'
import { formatPrice } from '@/utils/format'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'

const router = useRouter()
const { t } = useI18n()
const cartStore = useCartStore()
const authStore = useAuthStore()
const isGuest = computed(() => !authStore.isAuthenticated)
const guestAddress = reactive<CheckoutAddress>({ full_name: '', phone: '', address_line1: '', address_line2: '', city: '', state: '', postal_code: '', country: 'KH' })
const guestEmail = ref('')
const fieldErrors = ref<Record<string, string>>({})
const guestFields: { key: keyof CheckoutAddress; label: string; autocomplete: string; max: number; required: boolean }[] = [
  { key: 'full_name', label: 'Full name', autocomplete: 'name', max: 255, required: true },
  { key: 'phone', label: 'Phone number', autocomplete: 'tel', max: 30, required: true },
  { key: 'address_line1', label: 'Address line 1', autocomplete: 'shipping address-line1', max: 255, required: true },
  { key: 'address_line2', label: 'Address line 2 (optional)', autocomplete: 'shipping address-line2', max: 255, required: false },
  { key: 'city', label: 'City', autocomplete: 'shipping address-level2', max: 100, required: true },
  { key: 'state', label: 'Province / State', autocomplete: 'shipping address-level1', max: 100, required: true },
  { key: 'postal_code', label: 'Postal code', autocomplete: 'shipping postal-code', max: 20, required: true },
  { key: 'country', label: 'Country', autocomplete: 'shipping country', max: 100, required: true },
]
const addressError = ref('')
const addressSaveError = ref('')
const savingAddress = ref(false)
const shippingError = ref('')

const step = ref(1)
const steps = [
  { id: 1, label: 'Contact & Shipping' },
  { id: 2, label: 'Delivery' },
  { id: 3, label: 'Payment' },
  { id: 4, label: 'Review' }
]

const addresses = ref<Address[]>([])
const selectedAddressId = ref<string | null>(null)
const addressModalOpen = ref(false)
const loadingAddresses = ref(true)
const newAddress = reactive<Omit<Address, 'id' | 'isDefault'>>({
  label: '',
  fullName: '',
  line1: '',
  line2: '',
  city: '',
  state: '',
  postalCode: '',
  country: '',
  phone: ''
})

const shipping = ref<(ShippingMethod & { shopId: number | null })[]>([])
const loadingShipping = ref(true)
const shippingSelections = computed(() => cartStore.shippingSelections)
const shopGroups = computed(() => [...new Map(cartStore.availableItems.map(item => [item.shopId ?? 0, { id: item.shopId ?? 0, name: item.shopName || 'E-KHMER' }])).values()])
function optionsFor(shopId: number) { return shipping.value.filter(method => method.shopId === null || method.shopId === shopId) }
const shippingReady = computed(() => Boolean(cartStore.quote?.shipping_complete) && shopGroups.value.length > 0 && shopGroups.value.every(group => optionsFor(group.id).some(method => Number(method.id) === shippingSelections.value[group.id])))
const selectedShipping = computed(() => shippingReady.value && cartStore.quote ? {
  id: cartStore.quote.shipping_methods[0]?.id,
  name: cartStore.quote.shipping_methods.map(method => method.name).join(', '),
  price: cartStore.quote.shipping_amount
} : null)

const paymentOptions = [
  { id: 'cod', label: 'Cash on Delivery', desc: 'Pay when your order arrives', icon: Banknote },
  { id: 'bakong', label: 'Bakong / KHQR', desc: 'Scan a QR to pay each seller directly in USD', icon: Landmark }
]
const paymentMethod = ref<PaymentMethod>('cod')

const placing = ref(false)
const checkoutError = ref('')
let volatileAttempt: { signature: string; key: string } | null = null
async function requestKey(payload: CheckoutPayload): Promise<string> {
  // Persist only a digest, never the customer's contact/address details.
  const signatureInput = JSON.stringify([authStore.user?.id ?? null, payload])
  const key = typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : Array.from(crypto.getRandomValues(new Uint8Array(24)), byte => byte.toString(16).padStart(2, '0')).join('')
  // Web Crypto digests require HTTPS (or localhost). Keep retries in memory
  // when developing over a plain HTTP LAN address.
  if (!crypto.subtle) {
    if (volatileAttempt?.signature === signatureInput) return volatileAttempt.key
    volatileAttempt = { signature: signatureInput, key }
    return key
  }
  const bytes = new TextEncoder().encode(signatureInput)
  const signature = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)), byte => byte.toString(16).padStart(2, '0')).join('')
  if (volatileAttempt?.signature === signature) return volatileAttempt.key
  try {
    const previous = JSON.parse(sessionStorage.getItem('ekhmer_checkout_attempt') ?? 'null') as { signature: string; key: string } | null
    if (previous?.signature === signature) {
      volatileAttempt = previous
      return previous.key
    }
  } catch { /* A fresh key is safe if session storage is unavailable. */ }
  volatileAttempt = { signature, key }
  try { sessionStorage.setItem('ekhmer_checkout_attempt', JSON.stringify({ signature, key })) } catch { /* Optional persistence. */ }
  return key
}

const selectedAddress = computed(
  () => isGuest.value ? {
    id: '', label: 'Shipping', fullName: guestAddress.full_name, phone: guestAddress.phone ?? '',
    line1: guestAddress.address_line1, line2: guestAddress.address_line2 ?? '', city: guestAddress.city,
    state: guestAddress.state, postalCode: guestAddress.postal_code, country: guestAddress.country ?? '', isDefault: false
  } : addresses.value.find((a) => a.id === selectedAddressId.value) ?? addresses.value[0]
)

const paymentLabel = computed(() => {
  const option = paymentOptions.find((o) => o.id === paymentMethod.value)
  return option ? option.label : paymentMethod.value
})

function mapAddressFromApi(a: ApiAddress): Address {
  return {
    id: String(a.id),
    label: a.label ?? 'Address',
    fullName: a.full_name,
    line1: a.address_line1,
    line2: a.address_line2 ?? '',
    city: a.city,
    state: a.state,
    postalCode: a.postal_code,
    country: a.country,
    phone: a.phone ?? '',
    isDefault: a.is_default
  }
}

function mapShippingFromApi(s: ApiShippingMethod): ShippingMethod {
  return {
    id: String(s.id),
    name: s.name,
    description: s.description ?? '',
    etaDays: s.estimated_days_min ?? 1,
    price: s.price
  }
}

async function fetchAddresses() {
  if (isGuest.value) {
    addresses.value = []
    selectedAddressId.value = null
    loadingAddresses.value = false
    return
  }
  loadingAddresses.value = true
  addressError.value = ''
  try {
    const { data } = await addressesApi.list()
    addresses.value = data.data.map(mapAddressFromApi)
    if (addresses.value.length > 0 && selectedAddressId.value === null) {
      const def = addresses.value.find((a) => a.isDefault)
      selectedAddressId.value = def ? def.id : addresses.value[0].id
    }
  } catch (error) {
    addresses.value = []
    addressError.value = extractErrorMessage(error, 'We could not load your saved addresses. Please try again.')
  } finally {
    loadingAddresses.value = false
  }
}

async function fetchShipping() {
  loadingShipping.value = true
  shippingError.value = ''
  try {
    const { data } = await shippingApi.getActive()
    shipping.value = data.data.map(method => ({ ...mapShippingFromApi(method), shopId: method.shop_id ?? null }))

  } catch (error) {
    shipping.value = []
    shippingError.value = extractErrorMessage(error, 'We could not load delivery methods. Please try again.')
  } finally {
    loadingShipping.value = false
  }
}

onMounted(() => {
  cartStore.fetch()
  fetchAddresses()
  fetchShipping()
})

watch(() => authStore.user?.id, () => {
  step.value = 1
  fieldErrors.value = {}
  fetchAddresses()
  cartStore.fetch()
})

function continueToDelivery() {
  fieldErrors.value = {}
  checkoutError.value = ''
  if (isGuest.value) {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail.value.trim()) || guestEmail.value.length > 255) fieldErrors.value.email = 'Please enter a valid email address.'
    for (const field of guestFields) {
      if (field.required && !guestAddress[field.key]?.trim()) fieldErrors.value[`address.${field.key}`] = `${field.label} is required.`
    }
    if (Object.keys(fieldErrors.value).length) return
  }
  if (stepAllowed()) step.value = 2
}

function stepAllowed(): boolean {
  if (step.value === 1) {
    if (!selectedAddress.value) return false
    return Boolean(
      selectedAddress.value.fullName.trim() &&
        selectedAddress.value.line1.trim() &&
        selectedAddress.value.city.trim()
    )
  }
  return true
}

async function saveAddress() {
  if (isGuest.value || savingAddress.value) return
  addressSaveError.value = ''
  savingAddress.value = true
  try {
    const { data } = await addressesApi.create({
      full_name: newAddress.fullName,
      address_line1: newAddress.line1,
      address_line2: newAddress.line2 || undefined,
      city: newAddress.city,
      state: newAddress.state,
      postal_code: newAddress.postalCode,
      country: newAddress.country || undefined,
      phone: newAddress.phone || undefined,
      label: newAddress.label || undefined
    })
    const mapped = mapAddressFromApi(data.data)
    addresses.value.push(mapped)
    selectedAddressId.value = mapped.id
    newAddress.label = ''
    newAddress.fullName = ''
    newAddress.line1 = ''
    newAddress.line2 = ''
    newAddress.city = ''
    newAddress.state = ''
    newAddress.postalCode = ''
    newAddress.country = ''
    newAddress.phone = ''
    addressModalOpen.value = false
  } catch (error) {
    addressSaveError.value = extractErrorMessage(error, 'We could not save this address. Please check the fields and try again.')
  } finally {
    savingAddress.value = false
  }
}

async function placeOrder() {
  if (!selectedAddress.value || !selectedShipping.value || placing.value || !cartStore.canCheckout) return
  placing.value = true
  checkoutError.value = ''
  try {
    const payload: CheckoutPayload = {
      shipping_method_id: Number(selectedShipping.value.id),
      shipping_methods: { ...cartStore.shippingSelections },
      payment_method: paymentMethod.value,
      coupon_code: cartStore.appliedCoupon?.code,
      quote_fingerprint: cartStore.quote?.fingerprint,
      ...(isGuest.value ? { email: guestEmail.value.trim(), address: { ...guestAddress } } : { address_id: Number(selectedAddress.value.id) })
    }
    const { data } = await checkoutApi.begin(payload, await requestKey(payload))
    if (['cancelled', 'refunded'].includes(data.data.status)) {
      try { sessionStorage.removeItem('ekhmer_checkout_attempt') } catch { /* Optional persistence. */ }
      checkoutError.value = t('checkout.expired_attempt')
      return
    }
    if (paymentMethod.value === 'bakong' && data.data.payment_status !== 'paid') {
      await router.push({ name: 'bakong-payment', params: { orderNumber: data.data.order_number } })
      return
    }
    if (data.data.status === 'pending') await checkoutApi.confirm(data.data.order_number)
    // Confirmation clears the cart on the server. Refresh instead of issuing a
    // second destructive request, so a transient refresh failure cannot make a
    // successful order look failed to the customer.
    await cartStore.fetch()
    router.push({ name: 'order-success', params: { orderId: data.data.order_number } })
  } catch (error) {
    checkoutError.value = extractErrorMessage(error, 'We could not place your order. Your cart has been kept so you can review it.')
    fieldErrors.value = extractFieldErrors(error)
    if (Object.keys(fieldErrors.value).some(key => key === 'email' || key === 'address' || key === 'address_id' || key.startsWith('address.'))) step.value = 1
    if (isAxiosError(error) && [409, 422].includes(error.response?.status ?? 0)) await cartStore.fetch()
  } finally {
    placing.value = false
  }
}
</script>

<template>
  <div class="py-8">
    <CartLoadState />
    <div v-if="cartStore.isEmpty" class="mx-auto max-w-md">
      <EmptyState
        title="Your cart is empty"
        description="Add some items before checking out."
        ctaLabel="Back to shop"
        @cta="router.push('/shop')"
      />
    </div>

    <div v-else-if="cartStore.unavailableItems.length" class="mx-auto max-w-lg space-y-4 rounded-xl border border-amber-300 p-5">
      <h1 class="text-xl font-semibold">{{ $t('cart.needs_attention') }}</h1>
      <p role="alert">{{ $t('cart.checkout_blocked', { count: cartStore.unavailableItems.length }) }}</p>
      <RouterLink to="/cart" class="btn-primary">{{ $t('checkout.back_to_cart') }}</RouterLink>
    </div>

    <template v-else-if="cartStore.loaded && cartStore.items.length">
      <h1 class="mb-6 text-2xl font-bold text-ink dark:text-ink sm:text-3xl">{{ $t('nav.checkout') }}</h1>

      <div class="mb-8 flex items-center">
        <template v-for="(s, i) in steps" :key="s.id">
          <div class="flex items-center gap-2">
            <div
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold"
              :class="
                step === s.id
                  ? 'bg-primary text-white'
                  : step > s.id
                    ? 'bg-success text-white'
                    : 'bg-gray-200 text-gray-500 dark:text-muted dark:bg-surface-hover'
              "
            >
              <Check v-if="step > s.id" class="h-4 w-4" />
              <span v-else>{{ s.id }}</span>
            </div>
            <span class="hidden text-sm font-medium sm:block" :class="step >= s.id ? 'text-ink' : 'text-gray-400 dark:text-gray-500 dark:text-muted'">
              {{ s.label }}
            </span>
          </div>
          <div
            v-if="i < steps.length - 1"
            class="mx-3 h-0.5 flex-1 rounded"
            :class="step > s.id ? 'bg-success' : 'bg-gray-200 dark:bg-surface-hover'"
          ></div>
        </template>
      </div>

      <div class="grid gap-8 lg:grid-cols-[1fr_380px]">
        <div class="card space-y-6 p-6">
          <template v-if="step === 1">
            <div>
              <h2 class="text-lg font-bold text-ink dark:text-ink">{{ $t('checkout.shipping_address') }}</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-muted">Where should we deliver your order?</p>
            </div>

            <form v-if="isGuest" id="guest-details" class="space-y-5" novalidate @submit.prevent="continueToDelivery">
              <div class="rounded-xl bg-primary/5 p-4 text-sm">
                <p>Check out as a guest. No account is required.</p>
                <p class="mt-2">Already have an account?
                  <RouterLink :to="{ name: 'login', query: { redirect: '/checkout' } }" class="font-semibold text-primary">Sign in</RouterLink>
                  or <RouterLink :to="{ name: 'register', query: { redirect: '/checkout' } }" class="font-semibold text-primary">Create Account</RouterLink>
                </p>
              </div>
              <h3 class="font-semibold">Contact information</h3>
              <div>
                <label for="guest-email" class="label">Email *</label>
                <input id="guest-email" v-model.trim="guestEmail" class="input" type="email" autocomplete="email" required maxlength="255" :aria-invalid="Boolean(fieldErrors.email)" :aria-describedby="fieldErrors.email ? 'guest-email-error' : undefined" />
                <p v-if="fieldErrors.email" id="guest-email-error" class="mt-1 text-sm text-red-600" role="alert">{{ fieldErrors.email }}</p>
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <template v-for="field in guestFields" :key="field.key">
                  <h3 v-if="field.key === 'address_line1'" class="font-semibold sm:col-span-2">Shipping address</h3>
                  <div :class="field.key.startsWith('address_line') ? 'sm:col-span-2' : ''">
                    <label :for="`guest-${field.key}`" class="label">{{ field.label }}{{ field.required ? ' *' : '' }}</label>
                    <input :id="`guest-${field.key}`" v-model.trim="guestAddress[field.key]" class="input" :type="field.key === 'phone' ? 'tel' : 'text'" :autocomplete="field.autocomplete" :required="field.required" :maxlength="field.max" :aria-invalid="Boolean(fieldErrors[`address.${field.key}`])" :aria-describedby="fieldErrors[`address.${field.key}`] ? `guest-${field.key}-error` : undefined" />
                    <p v-if="fieldErrors[`address.${field.key}`]" :id="`guest-${field.key}-error`" class="mt-1 text-sm text-red-600" role="alert">{{ fieldErrors[`address.${field.key}`] }}</p>
                  </div>
                </template>
              </div>
              <p v-if="fieldErrors.address" class="text-sm text-red-600" role="alert">{{ fieldErrors.address }}</p>
            </form>

            <div v-else-if="loadingAddresses" class="text-sm text-gray-500 dark:text-muted">Loading addresses...</div>

            <div v-else class="grid gap-4 sm:grid-cols-2">
              <label
                v-for="address in addresses"
                :key="address.id"
                class="block cursor-pointer"
              >
                <input
                  v-model="selectedAddressId"
                  type="radio"
                  name="address"
                  :value="address.id"
                  class="peer sr-only"
                />
                <div
                  class="rounded-xl border-2 border-border-gray dark:border-border-gray p-4 transition-colors peer-checked:border-primary peer-checked:bg-primary/5"
                >
                  <div class="mb-1 flex items-center gap-2">
                    <span class="chip">{{ address.label }}</span>
                    <span
                      v-if="address.isDefault"
                      class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                    >
                      Default
                    </span>
                  </div>
                  <p class="font-semibold text-ink dark:text-ink">{{ address.fullName }}</p>
                  <p class="text-sm text-gray-600 dark:text-muted">{{ address.line1 }}</p>
                  <p class="text-sm text-gray-600 dark:text-muted">
                    {{ address.city }}, {{ address.state }} {{ address.postalCode }}
                  </p>
                  <p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ address.phone }}</p>
                  <RouterLink :to="{ name: 'account-address-edit', params: { id: address.id }, query: { redirect: '/checkout' } }" class="mt-2 inline-block text-sm text-primary">Edit address</RouterLink>
                </div>
              </label>

              <button
                type="button"
                class="flex min-h-[120px] items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border-gray dark:border-border-gray p-4 text-sm font-medium text-gray-500 dark:text-muted transition-colors hover:border-primary hover:text-primary"
                @click="addressModalOpen = true"
              >
                <Plus class="h-4 w-4" />
                Add New Address
              </button>
            </div>

            <div v-if="addressError" role="alert" class="text-sm text-red-600">
              {{ addressError }} <button type="button" class="underline" @click="fetchAddresses">Retry</button>
            </div>
            <p v-if="checkoutError" role="alert" class="text-sm text-red-600">{{ checkoutError }}</p>

            <div class="flex justify-end">
              <button
                type="button"
                class="btn-primary"
                :disabled="!isGuest && !stepAllowed()"
                @click="continueToDelivery"
              >
                Continue to Delivery
                <ArrowRight class="h-4 w-4" />
              </button>
            </div>
          </template>

          <template v-else-if="step === 2">
            <div>
              <h2 class="text-lg font-bold text-ink dark:text-ink">{{ $t('checkout.shipping_method') }}</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-muted">Choose a delivery speed that works for you.</p>
            </div>

            <div v-if="loadingShipping" class="text-sm text-gray-500 dark:text-muted">Loading shipping methods...</div>
            <div v-else-if="shippingError" role="alert" class="text-sm text-red-600">{{ shippingError }} <button type="button" class="underline" @click="fetchShipping">Retry</button></div>

            <div v-else class="space-y-6">
              <fieldset v-for="group in shopGroups" :key="group.id" class="space-y-3 rounded-xl border border-border-gray p-4">
                <legend class="px-2 font-semibold">{{ group.name }}</legend>
                <p v-if="!optionsFor(group.id).length" class="text-sm text-red-600">This shop has no available delivery method. Contact support before ordering.</p>
                <label v-for="method in optionsFor(group.id)" :key="method.id" class="flex cursor-pointer items-center gap-3 rounded-xl border border-border-gray p-4" :class="shippingSelections[group.id] === Number(method.id) ? 'border-primary bg-primary/5' : ''">
                  <input type="radio" :name="'shipping-' + group.id" :value="method.id" :checked="shippingSelections[group.id] === Number(method.id)" @change="cartStore.selectShipping(group.id, Number(method.id))" />
                  <span class="flex-1"><span class="block font-semibold">{{ method.name }}</span><span class="text-xs text-muted">{{ method.description }} · {{ method.etaDays }} days</span></span>
                  <span class="font-semibold">{{ formatPrice(method.price) }}</span>
                </label>
              </fieldset>
            </div>

            <div class="flex items-center justify-between">
              <button type="button" class="btn-secondary" @click="step = 1">
                <ArrowLeft class="h-4 w-4" />
                Back
              </button>
              <button type="button" class="btn-primary" :disabled="loadingShipping || !shippingReady || cartStore.quoteLoading" @click="step = 3">
                Continue to Payment
                <ArrowRight class="h-4 w-4" />
              </button>
            </div>
          </template>

          <template v-else-if="step === 3">
            <div>
              <h2 class="text-lg font-bold text-ink dark:text-ink">{{ $t('checkout.payment_method') }}</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-muted">How would you like to pay?</p>
            </div>

            <div class="space-y-3">
              <label
                v-for="option in paymentOptions"
                :key="option.id"
                class="block cursor-pointer"
              >
                <input
                  v-model="paymentMethod"
                  type="radio"
                  name="payment"
                  :value="option.id"
                  class="peer sr-only"
                />
                <div
                  class="flex items-center justify-between gap-4 rounded-xl border-2 border-border-gray dark:border-border-gray p-4 transition-colors peer-checked:border-primary peer-checked:bg-primary/5"
                >
                  <div class="flex items-center gap-3">
                    <span
                      class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 dark:border-gray-600"
                      :class="{ 'border-primary': paymentMethod === option.id }"
                    >
                      <span v-if="paymentMethod === option.id" class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                    </span>
                    <component :is="option.icon" class="h-5 w-5 text-gray-500 dark:text-muted" />
                    <div>
                      <p class="font-semibold text-ink dark:text-ink">{{ option.label }}</p>
                      <p class="text-sm text-gray-600 dark:text-muted">{{ option.desc }}</p>
                    </div>
                  </div>
                </div>
              </label>
            </div>

            <div class="flex items-center justify-between">
              <button type="button" class="btn-secondary" @click="step = 2">
                <ArrowLeft class="h-4 w-4" />
                Back
              </button>
              <button type="button" class="btn-primary" @click="step = 4">
                Review Order
                <ArrowRight class="h-4 w-4" />
              </button>
            </div>

            <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-muted">
              <Lock class="h-3.5 w-3.5" />
              Payments are encrypted and securely processed.
            </p>
          </template>

          <template v-else>
            <div>
              <h2 class="text-lg font-bold text-ink dark:text-ink">Review &amp; Place Order</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-muted">Confirm your details before placing the order.</p>
            </div>

            <div class="space-y-4">
              <div v-if="selectedAddress" class="flex items-start justify-between gap-4 rounded-xl border border-border-gray dark:border-border-gray p-4">
                <div class="flex items-start gap-3">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 dark:text-muted">Ship to</p>
                    <p class="font-semibold text-ink dark:text-ink">{{ selectedAddress.fullName }}</p>
                    <p class="text-sm text-gray-600 dark:text-muted">{{ selectedAddress.line1 }}</p>
                    <p class="text-sm text-gray-600 dark:text-muted">
                      {{ selectedAddress.city }}, {{ selectedAddress.state }} {{ selectedAddress.postalCode }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-muted">{{ selectedAddress.phone }}</p>
                  </div>
                </div>
                <button type="button" class="btn-ghost btn-sm shrink-0" @click="step = 1">Edit</button>
              </div>

              <div v-if="selectedShipping" class="flex items-center justify-between rounded-xl border border-border-gray dark:border-border-gray p-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 dark:text-muted">Delivery</p>
                  <p class="font-semibold text-ink dark:text-ink">{{ selectedShipping.name }}</p>
                  <p class="text-sm text-gray-600 dark:text-muted">
                    {{ selectedShipping.price === 0 ? 'Free' : formatPrice(selectedShipping.price) }}
                  </p>
                </div>
                <button type="button" class="btn-ghost btn-sm shrink-0" @click="step = 2">Edit</button>
              </div>

              <div class="flex items-center justify-between rounded-xl border border-border-gray dark:border-border-gray p-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 dark:text-muted">Payment</p>
                  <p class="font-semibold text-ink dark:text-ink">{{ paymentLabel }}</p>
                </div>
                <button type="button" class="btn-ghost btn-sm shrink-0" @click="step = 3">Edit</button>
              </div>

              <div class="divide-y divide-border-gray rounded-xl border border-border-gray dark:divide-border-gray dark:border-border-gray">
                <div v-for="item in cartStore.items" :key="item.id" class="flex items-center gap-3 p-4">
                  <img :src="item.image" :alt="item.title" class="h-14 w-12 rounded-lg object-cover" />
                  <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-ink dark:text-ink">{{ item.title }}</p>
                    <p class="text-xs text-gray-500 dark:text-muted">
                      Qty {{ item.quantity }} · {{ item.variant?.attributes ? item.variant.attributes.map((a) => a.value).join(', ') : '' }}
                    </p>
                  </div>
                  <p class="shrink-0 font-medium text-ink dark:text-ink">{{ formatPrice(item.unitPrice * item.quantity) }}</p>
                </div>
              </div>
            </div>

            <p v-if="isGuest" class="text-sm text-muted">Contact: {{ guestEmail }} · {{ guestAddress.phone }}</p>
            <button type="button" class="btn-primary btn-lg w-full" :disabled="placing || !shippingReady || !cartStore.canCheckout" @click="placeOrder">
              <Lock class="h-4 w-4" />
              {{ placing ? 'Placing order…' : cartStore.quoteLoading ? $t('cart.updating_totals') : cartStore.quote ? `Place Order · ${formatPrice(cartStore.quote.total, cartStore.quote.currency)}` : 'Place Order' }}
            </button>
            <p v-if="checkoutError" class="text-center text-sm text-red-600" role="alert">{{ checkoutError }}</p>
          </template>
        </div>

        <div class="card h-fit p-6 lg:sticky lg:top-24">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="font-bold text-ink dark:text-ink">{{ $t('checkout.order_summary') }}</h3>
            <RouterLink to="/cart" class="text-sm font-medium text-primary hover:text-primary-dark">
              Edit
            </RouterLink>
          </div>

          <QuoteSummary />

          <p class="mt-4 text-xs text-gray-500 dark:text-muted">
            {{ cartStore.totalItemCount }} {{ cartStore.totalItemCount === 1 ? 'item' : 'items' }}
          </p>
        </div>
      </div>
    </template>

    <BaseModal
      v-if="!isGuest"
      v-model="addressModalOpen"
      title="Add New Address"
      @close="addressModalOpen = false"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="addr-label">Label</label>
          <input id="addr-label" v-model="newAddress.label" type="text" class="input" placeholder="Home" />
        </div>
        <div>
          <label class="label" for="addr-name">Full name</label>
          <input id="addr-name" v-model="newAddress.fullName" type="text" class="input" placeholder="Alex Morgan" />
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="addr-line1">Address line 1</label>
          <input id="addr-line1" v-model="newAddress.line1" type="text" class="input" placeholder="Street address" />
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="addr-line2">Address line 2 (optional)</label>
          <input id="addr-line2" v-model="newAddress.line2" type="text" class="input" placeholder="Apt, suite, unit" />
        </div>
        <div>
          <label class="label" for="addr-city">City</label>
          <input id="addr-city" v-model="newAddress.city" type="text" class="input" placeholder="City" />
        </div>
        <div>
          <label class="label" for="addr-state">State</label>
          <input id="addr-state" v-model="newAddress.state" type="text" class="input" placeholder="State" />
        </div>
        <div>
          <label class="label" for="addr-zip">Postal code</label>
          <input id="addr-zip" v-model="newAddress.postalCode" type="text" class="input" placeholder="ZIP" />
        </div>
        <div>
          <label class="label" for="addr-country">Country</label>
          <input id="addr-country" v-model="newAddress.country" type="text" class="input" placeholder="Country" />
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="addr-phone">Phone</label>
          <input id="addr-phone" v-model="newAddress.phone" type="text" class="input" placeholder="+1 (555) 000-0000" />
        </div>
      </div>

      <button
        type="button"
        class="btn-primary btn-sm mt-4 w-full"
        :disabled="savingAddress"
        @click="saveAddress"
      >
        {{ savingAddress ? 'Saving…' : 'Save Address' }}
      </button>
      <p v-if="addressSaveError" role="alert" class="mt-3 text-sm text-red-600">{{ addressSaveError }}</p>

      <template #footer>
        <button type="button" class="btn-secondary w-full" @click="addressModalOpen = false">
          Cancel
        </button>
      </template>
    </BaseModal>
  </div>
</template>
