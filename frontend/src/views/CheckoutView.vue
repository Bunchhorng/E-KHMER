<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { isAxiosError } from 'axios'
import {
  ArrowLeft,
  ArrowRight,
  Banknote,
  Check,
  Landmark,
  Loader2,
  Lock,
  MapPin,
  Pencil,
  Plus,
  ShieldCheck,
  Store,
  Truck,
} from 'lucide-vue-next'
import type { Address, CartItem, PaymentMethod } from '@/types'
import { addressesApi, type ApiAddress } from '@/api/addresses'
import { shippingApi, type ApiShippingMethod } from '@/api/shipping'
import { checkoutApi, type CheckoutAddress, type CheckoutPayload } from '@/api/checkout'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import AssetImage from '@/components/AssetImage.vue'
import BaseModal from '@/components/BaseModal.vue'
import CartLoadState from '@/components/CartLoadState.vue'
import EmptyState from '@/components/EmptyState.vue'
import QuoteSummary from '@/components/QuoteSummary.vue'
import { formatPrice } from '@/utils/format'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'

interface CheckoutShippingOption {
  id: string
  name: string
  description: string
  price: number
  shopId: number | null
  estimatedDaysMin: number | null
  estimatedDaysMax: number | null
}

const router = useRouter()
const { t } = useI18n()
const cartStore = useCartStore()
const authStore = useAuthStore()

const isGuest = computed(() => !authStore.isAuthenticated)
const guestAddress = reactive<CheckoutAddress>({
  full_name: '',
  phone: '',
  address_line1: '',
  address_line2: '',
  city: '',
  state: '',
  postal_code: '',
  country: 'KH',
})
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
const steps = computed(() => [
  { id: 1, label: t('checkout.step_contact_shipping') },
  { id: 2, label: t('checkout.step_delivery') },
  { id: 3, label: t('checkout.step_payment') },
  { id: 4, label: t('checkout.step_review') },
])
const activeStep = computed(() => steps.value.find((s) => s.id === step.value) ?? steps.value[0])

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
  phone: '',
})

const shipping = ref<CheckoutShippingOption[]>([])
const loadingShipping = ref(true)
const shippingSelections = computed(() => cartStore.shippingSelections)

const shopGroups = computed(() => {
  const map = new Map<number, { id: number; name: string; items: CartItem[] }>()
  for (const item of cartStore.availableItems) {
    const id = item.shopId ?? 0
    const name = item.shopName || 'E-KHMER'
    const existing = map.get(id)
    if (existing) existing.items.push(item)
    else map.set(id, { id, name, items: [item] })
  }
  return [...map.values()]
})

function optionsFor(shopId: number): CheckoutShippingOption[] {
  return shipping.value.filter((method) => method.shopId === null || method.shopId === shopId)
}

function formatEstimate(method: CheckoutShippingOption): string {
  const { estimatedDaysMin: min, estimatedDaysMax: max } = method
  if (min == null && max == null) return ''
  if (min != null && max != null && min !== max) {
    return t('checkout.days_estimate', { min, max })
  }
  const days = min ?? max
  return t('checkout.day_estimate', { min: days })
}

const shippingReady = computed(
  () =>
    Boolean(cartStore.quote?.shipping_complete) &&
    shopGroups.value.length > 0 &&
    shopGroups.value.every((group) =>
      optionsFor(group.id).some((method) => Number(method.id) === shippingSelections.value[group.id])
    )
)

const selectedShipping = computed(() =>
  shippingReady.value && cartStore.quote
    ? {
        id: cartStore.quote.shipping_methods[0]?.id,
        name: cartStore.quote.shipping_methods.map((method) => method.name).join(', '),
        price: cartStore.quote.shipping_amount,
        methods: cartStore.quote.shipping_methods,
      }
    : null
)

const paymentOptions = computed(() => [
  {
    id: 'cod' as PaymentMethod,
    label: 'Cash on Delivery',
    desc: t('checkout.cod_description'),
    icon: Banknote,
  },
  {
    id: 'bakong' as PaymentMethod,
    label: 'Bakong / KHQR',
    desc: t('checkout.bakong_description'),
    icon: Landmark,
  },
])
const paymentMethod = ref<PaymentMethod>('cod')

const placing = ref(false)
const checkoutError = ref('')
let volatileAttempt: { signature: string; key: string } | null = null

async function requestKey(payload: CheckoutPayload): Promise<string> {
  // Persist only a digest, never the customer's contact/address details.
  const signatureInput = JSON.stringify([authStore.user?.id ?? null, payload])
  const key =
    typeof crypto.randomUUID === 'function'
      ? crypto.randomUUID()
      : Array.from(crypto.getRandomValues(new Uint8Array(24)), (byte) => byte.toString(16).padStart(2, '0')).join('')
  if (!crypto.subtle) {
    if (volatileAttempt?.signature === signatureInput) return volatileAttempt.key
    volatileAttempt = { signature: signatureInput, key }
    return key
  }
  const bytes = new TextEncoder().encode(signatureInput)
  const signature = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)), (byte) =>
    byte.toString(16).padStart(2, '0')
  ).join('')
  if (volatileAttempt?.signature === signature) return volatileAttempt.key
  try {
    const previous = JSON.parse(sessionStorage.getItem('ekhmer_checkout_attempt') ?? 'null') as {
      signature: string
      key: string
    } | null
    if (previous?.signature === signature) {
      volatileAttempt = previous
      return previous.key
    }
  } catch {
    /* A fresh key is safe if session storage is unavailable. */
  }
  volatileAttempt = { signature, key }
  try {
    sessionStorage.setItem('ekhmer_checkout_attempt', JSON.stringify({ signature, key }))
  } catch {
    /* Optional persistence. */
  }
  return key
}

const selectedAddress = computed(() =>
  isGuest.value
    ? {
        id: '',
        label: 'Shipping',
        fullName: guestAddress.full_name,
        phone: guestAddress.phone ?? '',
        line1: guestAddress.address_line1,
        line2: guestAddress.address_line2 ?? '',
        city: guestAddress.city,
        state: guestAddress.state,
        postalCode: guestAddress.postal_code,
        country: guestAddress.country ?? '',
        isDefault: false,
      }
    : addresses.value.find((a) => a.id === selectedAddressId.value) ?? addresses.value[0]
)

const paymentLabel = computed(() => {
  const option = paymentOptions.value.find((o) => o.id === paymentMethod.value)
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
    isDefault: a.is_default,
  }
}

function mapShippingFromApi(s: ApiShippingMethod): CheckoutShippingOption {
  return {
    id: String(s.id),
    name: s.name,
    description: s.description ?? '',
    price: s.price,
    shopId: s.shop_id ?? null,
    estimatedDaysMin: s.estimated_days_min ?? null,
    estimatedDaysMax: s.estimated_days_max ?? null,
  }
}

function formatVariantSummary(item: CartItem): string {
  if (item.variant?.attributes?.length) {
    return item.variant.attributes.map((a) => `${a.name}: ${a.value}`).join(' · ')
  }
  return item.variantName ?? ''
}

async function fetchAddresses(): Promise<void> {
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

async function fetchShipping(): Promise<void> {
  loadingShipping.value = true
  shippingError.value = ''
  try {
    const { data } = await shippingApi.getActive()
    shipping.value = data.data.map(mapShippingFromApi)
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

watch(
  () => authStore.user?.id,
  () => {
    step.value = 1
    fieldErrors.value = {}
    fetchAddresses()
    cartStore.fetch()
  }
)

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

function goToStep(targetStep: number): void {
  if (targetStep < step.value) {
    checkoutError.value = ''
    step.value = targetStep
  }
}

function continueToDelivery(): void {
  fieldErrors.value = {}
  checkoutError.value = ''
  if (isGuest.value) {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail.value.trim()) || guestEmail.value.length > 255) {
      fieldErrors.value.email = 'Please enter a valid email address.'
    }
    for (const field of guestFields) {
      if (field.required && !guestAddress[field.key]?.trim()) {
        fieldErrors.value[`address.${field.key}`] = `${field.label} is required.`
      }
    }
    if (Object.keys(fieldErrors.value).length) return
  } else if (!stepAllowed()) {
    checkoutError.value = t('checkout.address_incomplete')
    return
  }
  if (stepAllowed()) step.value = 2
}

async function saveAddress(): Promise<void> {
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
      label: newAddress.label || undefined,
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

async function placeOrder(): Promise<void> {
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
      ...(isGuest.value
        ? { email: guestEmail.value.trim(), address: { ...guestAddress } }
        : { address_id: Number(selectedAddress.value.id) }),
    }
    const { data } = await checkoutApi.begin(payload, await requestKey(payload))
    if (
      ['cancelled', 'refunded'].includes(data.data.status) ||
      (data.data.status === 'pending' &&
        data.reservation_expires_at &&
        Date.parse(data.reservation_expires_at) <= Date.now())
    ) {
      volatileAttempt = null
      try {
        sessionStorage.removeItem('ekhmer_checkout_attempt')
      } catch {
        /* Optional persistence. */
      }
      checkoutError.value = t('checkout.expired_attempt')
      return
    }
    if (paymentMethod.value === 'bakong' && data.data.payment_status !== 'paid') {
      await router.push({ name: 'bakong-payment', params: { orderNumber: data.data.order_number } })
      return
    }
    if (data.data.status === 'pending') await checkoutApi.confirm(data.data.order_number)
    await cartStore.fetch()
    router.push({ name: 'order-success', params: { orderId: data.data.order_number } })
  } catch (error) {
    checkoutError.value = extractErrorMessage(error, 'We could not place your order. Your cart has been kept so you can review it.')
    fieldErrors.value = extractFieldErrors(error)
    if (
      Object.keys(fieldErrors.value).some(
        (key) => key === 'email' || key === 'address' || key === 'address_id' || key.startsWith('address.')
      )
    ) {
      step.value = 1
    }
    if (isAxiosError(error) && [409, 422].includes(error.response?.status ?? 0)) await cartStore.fetch()
  } finally {
    placing.value = false
  }
}
</script>

<template>
  <div class="py-5 pb-[max(2.5rem,env(safe-area-inset-bottom))] sm:py-6 lg:pb-10">
    <CartLoadState />

    <div v-if="cartStore.isEmpty" class="mx-auto mt-4 max-w-md">
      <EmptyState
        :title="$t('cart.empty_title')"
        description="Add some items before checking out."
        ctaLabel="Back to shop"
        @cta="router.push('/shop')"
      />
    </div>

    <div
      v-else-if="cartStore.unavailableItems.length"
      class="mx-auto mt-4 max-w-lg space-y-4 rounded-none border border-amber-500/50 bg-amber-500/10 p-5"
    >
      <h1 class="text-xl font-bold text-ink">{{ $t('cart.needs_attention') }}</h1>
      <p role="alert" class="text-sm text-amber-900 dark:text-amber-300">
        {{ $t('cart.checkout_blocked', { count: cartStore.unavailableItems.length }) }}
      </p>
      <RouterLink to="/cart" class="btn-primary inline-flex rounded-none">{{ $t('checkout.back_to_cart') }}</RouterLink>
    </div>

    <template v-else-if="cartStore.loaded && cartStore.items.length">
      <header class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-border-gray pb-4">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $t('nav.checkout') }}</h1>
          <p class="mt-0.5 text-xs font-medium text-muted sm:hidden">
            {{ $t('checkout.step_counter', { current: step, total: steps.length }) }} · {{ activeStep.label }}
          </p>
        </div>
        <RouterLink
          to="/cart"
          class="inline-flex min-h-10 items-center gap-1.5 rounded-none border border-border-gray bg-surface px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:border-border-strong hover:bg-surface-hover hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus sm:text-sm"
        >
          <ArrowLeft :size="14" aria-hidden="true" />
          {{ $t('checkout.back_to_cart') }}
        </RouterLink>
      </header>

      <nav aria-label="Checkout progress" class="mb-6">
        <ol class="grid grid-cols-4 border border-border-gray bg-surface">
          <li
            v-for="s in steps"
            :key="s.id"
            class="border-r border-border-gray last:border-r-0"
          >
            <button
              type="button"
              class="flex h-full w-full items-center gap-2 px-2 py-2.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-border-focus sm:px-3.5 sm:py-3"
              :class="
                step === s.id
                  ? 'border-b-2 border-b-primary bg-primary/10 text-ink'
                  : step > s.id
                    ? 'cursor-pointer bg-surface text-ink hover:bg-surface-hover'
                    : 'cursor-default bg-surface-muted/50 text-muted'
              "
              :disabled="s.id >= step"
              :aria-current="step === s.id ? 'step' : undefined"
              @click="goToStep(s.id)"
            >
              <span
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-none border text-xs font-bold sm:h-7 sm:w-7"
                :class="
                  step === s.id
                    ? 'border-primary bg-primary text-white'
                    : step > s.id
                      ? 'border-emerald-700 bg-emerald-700 text-white dark:border-success dark:bg-success dark:text-canvas'
                      : 'border-border-strong bg-surface text-muted'
                "
              >
                <Check v-if="step > s.id" :size="14" aria-hidden="true" />
                <span v-else>{{ s.id }}</span>
              </span>
              <span class="hidden min-w-0 truncate text-xs font-semibold sm:block sm:text-sm">{{ s.label }}</span>
            </button>
          </li>
        </ol>
      </nav>

      <div class="grid gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(20rem,3fr)] lg:items-start">
        <div class="card min-w-0 space-y-5 rounded-none p-4 sm:p-6">
          <template v-if="step === 1">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border-gray pb-4">
              <div>
                <h2 class="text-lg font-bold text-ink">{{ $t('checkout.shipping_address') }}</h2>
                <p class="mt-0.5 text-sm text-muted">{{ $t('checkout.shipping_subtitle') }}</p>
              </div>
              <button
                v-if="!isGuest"
                type="button"
                class="btn-secondary btn-sm min-h-10 shrink-0 rounded-none"
                @click="addressModalOpen = true"
              >
                <Plus :size="14" aria-hidden="true" />
                {{ $t('checkout.add_new_address') }}
              </button>
            </div>

            <form v-if="isGuest" id="guest-details" class="space-y-5" novalidate @submit.prevent="continueToDelivery">
              <div class="rounded-none border border-primary/30 bg-primary/5 p-3.5 text-sm text-ink">
                <p>Check out as a guest. No account is required.</p>
                <p class="mt-1.5 text-xs text-text-secondary">
                  Already have an account?
                  <RouterLink :to="{ name: 'login', query: { redirect: '/checkout' } }" class="font-semibold text-primary underline underline-offset-2">Sign in</RouterLink>
                  or
                  <RouterLink :to="{ name: 'register', query: { redirect: '/checkout' } }" class="font-semibold text-primary underline underline-offset-2">Create Account</RouterLink>
                </p>
              </div>

              <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-text-secondary">Contact information</h3>
                <div>
                  <label for="guest-email" class="label">Email *</label>
                  <input
                    id="guest-email"
                    v-model.trim="guestEmail"
                    class="input min-h-11 rounded-none bg-canvas dark:bg-canvas"
                    :class="{ 'input-error border-danger': fieldErrors.email }"
                    type="email"
                    autocomplete="email"
                    required
                    maxlength="255"
                    :aria-invalid="Boolean(fieldErrors.email)"
                    :aria-describedby="fieldErrors.email ? 'guest-email-error' : undefined"
                  />
                  <p v-if="fieldErrors.email" id="guest-email-error" class="mt-1 text-xs font-medium text-danger" role="alert">
                    {{ fieldErrors.email }}
                  </p>
                </div>
              </div>

              <div class="grid gap-4 border-t border-border-gray pt-4 sm:grid-cols-2">
                <template v-for="field in guestFields" :key="field.key">
                  <h3 v-if="field.key === 'address_line1'" class="text-xs font-bold uppercase tracking-wider text-text-secondary sm:col-span-2">
                    Shipping address
                  </h3>
                  <div :class="field.key.startsWith('address_line') ? 'sm:col-span-2' : ''">
                    <label :for="`guest-${field.key}`" class="label">{{ field.label }}{{ field.required ? ' *' : '' }}</label>
                    <input
                      :id="`guest-${field.key}`"
                      v-model.trim="guestAddress[field.key]"
                      class="input min-h-11 rounded-none bg-canvas dark:bg-canvas"
                      :class="{ 'input-error border-danger': fieldErrors[`address.${field.key}`] }"
                      :type="field.key === 'phone' ? 'tel' : 'text'"
                      :autocomplete="field.autocomplete"
                      :required="field.required"
                      :maxlength="field.max"
                      :aria-invalid="Boolean(fieldErrors[`address.${field.key}`])"
                      :aria-describedby="fieldErrors[`address.${field.key}`] ? `guest-${field.key}-error` : undefined"
                    />
                    <p
                      v-if="fieldErrors[`address.${field.key}`]"
                      :id="`guest-${field.key}-error`"
                      class="mt-1 text-xs font-medium text-danger"
                      role="alert"
                    >
                      {{ fieldErrors[`address.${field.key}`] }}
                    </p>
                  </div>
                </template>
              </div>
              <p v-if="fieldErrors.address" class="border-l-2 border-danger bg-danger/10 px-3 py-2 text-xs font-medium text-danger" role="alert">
                {{ fieldErrors.address }}
              </p>
            </form>

            <div v-else-if="loadingAddresses" class="grid gap-3 sm:grid-cols-2">
              <div v-for="n in 2" :key="n" class="h-32 animate-pulse border border-border-gray bg-surface-muted"></div>
            </div>

            <div
              v-else-if="addresses.length === 0 && !addressError"
              class="rounded-none border border-dashed border-border-strong bg-surface-muted/50 p-6 text-center"
            >
              <MapPin :size="22" class="mx-auto text-muted" aria-hidden="true" />
              <p class="mt-2 text-sm font-medium text-ink">{{ $t('checkout.no_saved_addresses') }}</p>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2">
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
                  class="peer sr-only appearance-none rounded-none"
                />
                <div
                  class="flex h-full flex-col justify-between rounded-none border p-3.5 transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-border-focus"
                  :class="
                    selectedAddress?.id === address.id
                      ? 'border-primary bg-primary/5'
                      : 'border-border-gray bg-surface hover:border-border-strong'
                  "
                >
                  <div>
                    <div class="mb-2 flex items-center justify-between gap-2">
                      <div class="flex flex-wrap items-center gap-1.5">
                        <span class="border border-border-gray bg-surface-muted px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-text-secondary">
                          {{ address.label }}
                        </span>
                        <span
                          v-if="address.isDefault"
                          class="border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:border-success/40 dark:bg-success/10 dark:text-success"
                        >
                          {{ $t('checkout.default_badge') }}
                        </span>
                      </div>
                      <span
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded-none border"
                        :class="selectedAddress?.id === address.id ? 'border-primary bg-primary text-white' : 'border-border-strong bg-canvas'"
                      >
                        <Check v-if="selectedAddress?.id === address.id" :size="11" :stroke-width="3" aria-hidden="true" />
                      </span>
                    </div>
                    <p class="text-sm font-bold text-ink">{{ address.fullName }}</p>
                    <p v-if="address.phone" class="mt-0.5 text-xs font-medium text-text-secondary">{{ address.phone }}</p>
                    <p class="mt-1.5 text-xs leading-relaxed text-muted">
                      {{ address.line1 }}<span v-if="address.line2">, {{ address.line2 }}</span>
                      <br />
                      {{ address.city }}, {{ address.state }} {{ address.postalCode }}<span v-if="address.country"> · {{ address.country }}</span>
                    </p>
                  </div>

                  <div class="mt-3 border-t border-border-gray pt-2">
                    <RouterLink
                      :to="{ name: 'account-address-edit', params: { id: address.id }, query: { redirect: '/checkout' } }"
                      class="inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    >
                      <Pencil :size="12" aria-hidden="true" />
                      Edit address
                    </RouterLink>
                  </div>
                </div>
              </label>
            </div>

            <div v-if="addressError" role="alert" class="border border-danger/40 bg-danger/10 p-3 text-sm text-danger">
              {{ addressError }}
              <button type="button" class="ml-2 font-semibold underline" @click="fetchAddresses">{{ $t('actions.retry') }}</button>
            </div>
            <p v-if="checkoutError" role="alert" class="border-l-2 border-danger bg-danger/10 px-3 py-2 text-sm font-medium text-danger">
              {{ checkoutError }}
            </p>

            <div class="flex justify-end border-t border-border-gray pt-4">
              <button
                type="button"
                class="btn-primary min-h-11 w-full rounded-none sm:w-auto"
                :disabled="!isGuest && !stepAllowed()"
                @click="continueToDelivery"
              >
                Continue to Delivery
                <ArrowRight :size="16" aria-hidden="true" />
              </button>
            </div>
          </template>

          <template v-else-if="step === 2">
            <div class="border-b border-border-gray pb-4">
              <h2 class="text-lg font-bold text-ink">{{ $t('checkout.shipping_method') }}</h2>
              <p class="mt-0.5 text-sm text-muted">{{ $t('checkout.delivery_subtitle') }}</p>
            </div>

            <div v-if="loadingShipping" class="space-y-3">
              <div v-for="n in 2" :key="n" class="h-24 animate-pulse border border-border-gray bg-surface-muted"></div>
            </div>
            <div v-else-if="shippingError" role="alert" class="border border-danger/40 bg-danger/10 p-4 text-sm text-danger">
              {{ shippingError }}
              <button type="button" class="ml-2 font-semibold underline" @click="fetchShipping">{{ $t('actions.retry') }}</button>
            </div>

            <div v-else class="space-y-5">
              <fieldset
                v-for="group in shopGroups"
                :key="group.id"
                class="space-y-3 rounded-none border border-border-gray p-4"
              >
                <legend class="inline-flex items-center gap-1.5 px-2 text-sm font-bold text-ink">
                  <Store :size="14" class="text-info" aria-hidden="true" />
                  <span>{{ group.name }}</span>
                </legend>

                <p class="text-xs text-muted">
                  {{ group.items.map((item) => `${item.title} ×${item.quantity}`).join(' · ') }}
                </p>

                <p
                  v-if="!optionsFor(group.id).length"
                  role="alert"
                  class="border-l-2 border-danger bg-danger/10 px-3 py-2 text-sm font-medium text-danger"
                >
                  This shop has no available delivery method. Contact support before ordering.
                </p>

                <div class="space-y-2.5">
                  <label
                    v-for="method in optionsFor(group.id)"
                    :key="method.id"
                    class="flex min-h-11 cursor-pointer items-center gap-3 rounded-none border p-3.5 transition-colors"
                    :class="
                      shippingSelections[group.id] === Number(method.id)
                        ? 'border-primary bg-primary/5'
                        : 'border-border-gray bg-surface hover:border-border-strong'
                    "
                  >
                    <input
                      type="radio"
                      :name="'shipping-' + group.id"
                      :value="method.id"
                      :checked="shippingSelections[group.id] === Number(method.id)"
                      class="h-4 w-4 shrink-0 rounded-none accent-primary"
                      @change="cartStore.selectShipping(group.id, Number(method.id))"
                    />
                    <span class="min-w-0 flex-1">
                      <span class="block text-sm font-semibold text-ink">{{ method.name }}</span>
                      <span v-if="method.description || formatEstimate(method)" class="mt-0.5 block text-xs text-muted">
                        {{ [method.description, formatEstimate(method)].filter(Boolean).join(' · ') }}
                      </span>
                    </span>
                    <span class="shrink-0 text-sm font-bold text-ink">
                      {{ method.price === 0 ? 'Free' : formatPrice(method.price) }}
                    </span>
                  </label>
                </div>
              </fieldset>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-border-gray pt-4 sm:flex-row sm:items-center sm:justify-between">
              <button type="button" class="btn-secondary min-h-11 rounded-none" @click="step = 1">
                <ArrowLeft :size="16" aria-hidden="true" />
                {{ $t('actions.back') }}
              </button>
              <button
                type="button"
                class="btn-primary min-h-11 w-full rounded-none sm:w-auto"
                :disabled="loadingShipping || !shippingReady || cartStore.quoteLoading"
                @click="step = 3"
              >
                Continue to Payment
                <ArrowRight :size="16" aria-hidden="true" />
              </button>
            </div>
          </template>

          <template v-else-if="step === 3">
            <div class="border-b border-border-gray pb-4">
              <h2 class="text-lg font-bold text-ink">{{ $t('checkout.payment_method') }}</h2>
              <p class="mt-0.5 text-sm text-muted">{{ $t('checkout.payment_subtitle') }}</p>
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
                  class="peer sr-only appearance-none rounded-none"
                />
                <div
                  class="flex items-start gap-3.5 rounded-none border p-4 transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-border-focus"
                  :class="
                    paymentMethod === option.id
                      ? 'border-primary bg-primary/5'
                      : 'border-border-gray bg-surface hover:border-border-strong'
                  "
                >
                  <span
                    class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-none border"
                    :class="paymentMethod === option.id ? 'border-primary bg-primary text-white' : 'border-border-strong bg-canvas'"
                  >
                    <Check v-if="paymentMethod === option.id" :size="11" :stroke-width="3" aria-hidden="true" />
                  </span>
                  <component :is="option.icon" :size="20" class="mt-0.5 shrink-0 text-info" aria-hidden="true" />
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-ink">{{ option.label }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-text-secondary">{{ option.desc }}</p>
                  </div>
                </div>
              </label>
            </div>

            <p class="flex items-center gap-1.5 text-xs text-muted">
              <ShieldCheck :size="15" class="shrink-0 text-info" aria-hidden="true" />
              <span>{{ $t('checkout.payment_security') }}</span>
            </p>

            <div class="flex flex-col-reverse gap-3 border-t border-border-gray pt-4 sm:flex-row sm:items-center sm:justify-between">
              <button type="button" class="btn-secondary min-h-11 rounded-none" @click="step = 2">
                <ArrowLeft :size="16" aria-hidden="true" />
                {{ $t('actions.back') }}
              </button>
              <button type="button" class="btn-primary min-h-11 w-full rounded-none sm:w-auto" @click="step = 4">
                Review Order
                <ArrowRight :size="16" aria-hidden="true" />
              </button>
            </div>
          </template>

          <template v-else>
            <div class="border-b border-border-gray pb-4">
              <h2 class="text-lg font-bold text-ink">Review &amp; Place Order</h2>
              <p class="mt-0.5 text-sm text-muted">{{ $t('checkout.review_subtitle') }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <div v-if="selectedAddress" class="flex items-start justify-between gap-3 rounded-none border border-border-gray bg-surface-muted/40 p-3.5 sm:col-span-2">
                <div class="min-w-0">
                  <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Ship to</p>
                  <p class="mt-1 text-sm font-bold text-ink">{{ selectedAddress.fullName }}</p>
                  <p class="text-xs leading-relaxed text-text-secondary">
                    {{ selectedAddress.line1 }}<span v-if="selectedAddress.line2">, {{ selectedAddress.line2 }}</span>
                    · {{ selectedAddress.city }}, {{ selectedAddress.state }} {{ selectedAddress.postalCode }}
                  </p>
                  <p class="mt-0.5 text-xs text-muted">
                    <span v-if="isGuest">{{ guestEmail }} · </span>{{ selectedAddress.phone }}
                  </p>
                </div>
                <button type="button" class="btn-ghost btn-sm shrink-0 rounded-none" @click="step = 1">Edit</button>
              </div>

              <div v-if="selectedShipping" class="flex items-start justify-between gap-3 rounded-none border border-border-gray bg-surface-muted/40 p-3.5">
                <div class="min-w-0">
                  <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Delivery</p>
                  <p class="mt-1 text-sm font-bold text-ink">{{ selectedShipping.name }}</p>
                  <p class="text-xs text-text-secondary">
                    {{ selectedShipping.price === 0 ? 'Free' : formatPrice(selectedShipping.price, cartStore.quote?.currency) }}
                  </p>
                </div>
                <button type="button" class="btn-ghost btn-sm shrink-0 rounded-none" @click="step = 2">Edit</button>
              </div>

              <div class="flex items-start justify-between gap-3 rounded-none border border-border-gray bg-surface-muted/40 p-3.5">
                <div class="min-w-0">
                  <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Payment</p>
                  <p class="mt-1 text-sm font-bold text-ink">{{ paymentLabel }}</p>
                </div>
                <button type="button" class="btn-ghost btn-sm shrink-0 rounded-none" @click="step = 3">Edit</button>
              </div>
            </div>

            <div class="rounded-none border border-border-gray">
              <header class="flex items-center justify-between border-b border-border-gray bg-surface-muted/60 px-4 py-2.5">
                <span class="text-xs font-bold uppercase tracking-wider text-ink">{{ $t('checkout.order_items') }}</span>
                <span class="text-xs font-medium text-muted">{{ cartStore.totalItemCount }}</span>
              </header>
              <div v-for="group in shopGroups" :key="group.id" class="border-b border-border-gray last:border-b-0">
                <div class="flex items-center gap-1.5 border-b border-border-gray bg-surface-muted/30 px-4 py-1.5 text-xs font-semibold text-text-secondary">
                  <Store :size="12" class="text-info" aria-hidden="true" />
                  <span>{{ group.name }}</span>
                </div>
                <div class="divide-y divide-border-gray px-4">
                  <div v-for="item in group.items" :key="item.id" class="flex items-center gap-3 py-3">
                    <AssetImage :src="item.image" :alt="item.title" class="h-14 w-14 shrink-0 rounded-none border border-border-gray" />
                    <div class="min-w-0 flex-1">
                      <p class="line-clamp-1 text-sm font-semibold text-ink">{{ item.title }}</p>
                      <p class="mt-0.5 text-xs text-muted">
                        Qty {{ item.quantity }}<span v-if="formatVariantSummary(item)"> · {{ formatVariantSummary(item) }}</span>
                      </p>
                    </div>
                    <p class="shrink-0 text-sm font-bold text-ink">{{ formatPrice(item.unitPrice * item.quantity) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <p v-if="checkoutError" class="border-l-2 border-danger bg-danger/10 px-3 py-2 text-sm font-medium text-danger" role="alert">
              {{ checkoutError }}
            </p>

            <div class="space-y-2 border-t border-border-gray pt-4">
              <button
                type="button"
                class="btn-primary min-h-12 w-full rounded-none text-sm"
                :disabled="placing || !shippingReady || !cartStore.canCheckout"
                :aria-busy="placing"
                @click="placeOrder"
              >
                <Loader2 v-if="placing" :size="16" class="animate-spin" aria-hidden="true" />
                <Lock v-else :size="16" aria-hidden="true" />
                {{
                  placing
                    ? 'Placing order…'
                    : cartStore.quoteLoading
                      ? $t('cart.updating_totals')
                      : cartStore.quote
                        ? `Place Order · ${formatPrice(cartStore.quote.total, cartStore.quote.currency)}`
                        : 'Place Order'
                }}
              </button>
            </div>
          </template>
        </div>

        <section
          class="card h-fit space-y-4 rounded-none p-4 sm:p-5 lg:sticky lg:top-36"
          :aria-label="$t('checkout.order_summary')"
        >
          <div class="flex items-center justify-between border-b border-border-gray pb-3">
            <h3 class="text-base font-bold text-ink">{{ $t('checkout.order_summary') }}</h3>
            <RouterLink to="/cart" class="text-xs font-semibold text-primary hover:underline">
              Edit
            </RouterLink>
          </div>

          <div class="max-h-56 space-y-2.5 overflow-y-auto border-b border-border-gray pb-3">
            <div v-for="group in shopGroups" :key="group.id" class="space-y-1.5">
              <p class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-muted">
                <Truck :size="11" class="text-info" aria-hidden="true" />
                <span>{{ group.name }}</span>
              </p>
              <div v-for="item in group.items" :key="item.id" class="flex items-start justify-between gap-2 text-xs">
                <div class="min-w-0 flex-1">
                  <p class="truncate font-medium text-ink">{{ item.title }}</p>
                  <p class="text-[11px] text-muted">
                    ×{{ item.quantity }}<span v-if="formatVariantSummary(item)"> · {{ formatVariantSummary(item) }}</span>
                  </p>
                </div>
                <span class="shrink-0 font-semibold text-ink">{{ formatPrice(item.unitPrice * item.quantity) }}</span>
              </div>
            </div>
          </div>

          <QuoteSummary :estimated="step < 4" />

          <p v-if="step < 4" class="text-xs leading-relaxed text-muted">{{ $t('cart.shipping_note') }}</p>

          <p class="border-t border-border-gray pt-3 text-xs text-muted">
            {{ cartStore.totalItemCount }} {{ cartStore.totalItemCount === 1 ? 'item' : 'items' }}
          </p>
        </section>
      </div>
    </template>

    <BaseModal
      v-if="!isGuest"
      v-model="addressModalOpen"
      :title="$t('checkout.add_new_address')"
      sharp
      @close="addressModalOpen = false"
    >
      <div class="grid gap-3.5 sm:grid-cols-2">
        <div>
          <label class="label" for="addr-label">Label</label>
          <input id="addr-label" v-model="newAddress.label" type="text" class="input rounded-none" placeholder="Home" />
        </div>
        <div>
          <label class="label" for="addr-name">Full name</label>
          <input id="addr-name" v-model="newAddress.fullName" type="text" class="input rounded-none" placeholder="Alex Morgan" />
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="addr-line1">Address line 1</label>
          <input id="addr-line1" v-model="newAddress.line1" type="text" class="input rounded-none" placeholder="Street address" />
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="addr-line2">Address line 2 (optional)</label>
          <input id="addr-line2" v-model="newAddress.line2" type="text" class="input rounded-none" placeholder="Apt, suite, unit" />
        </div>
        <div>
          <label class="label" for="addr-city">City</label>
          <input id="addr-city" v-model="newAddress.city" type="text" class="input rounded-none" placeholder="City" />
        </div>
        <div>
          <label class="label" for="addr-state">State</label>
          <input id="addr-state" v-model="newAddress.state" type="text" class="input rounded-none" placeholder="State" />
        </div>
        <div>
          <label class="label" for="addr-zip">Postal code</label>
          <input id="addr-zip" v-model="newAddress.postalCode" type="text" class="input rounded-none" placeholder="ZIP" />
        </div>
        <div>
          <label class="label" for="addr-country">Country</label>
          <input id="addr-country" v-model="newAddress.country" type="text" class="input rounded-none" placeholder="Country" />
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="addr-phone">Phone</label>
          <input id="addr-phone" v-model="newAddress.phone" type="text" class="input rounded-none" placeholder="+855 12 345 678" />
        </div>
      </div>

      <p v-if="addressSaveError" role="alert" class="mt-3 border-l-2 border-danger bg-danger/10 px-3 py-2 text-sm text-danger">
        {{ addressSaveError }}
      </p>

      <template #footer>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" class="btn-secondary min-h-11 rounded-none sm:min-h-10" @click="addressModalOpen = false">
            {{ $t('actions.cancel') }}
          </button>
          <button
            type="button"
            class="btn-primary min-h-11 rounded-none sm:min-h-10"
            :disabled="savingAddress"
            @click="saveAddress"
          >
            {{ savingAddress ? $t('common.saving') : $t('checkout.save_address') }}
          </button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>
