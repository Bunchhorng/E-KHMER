<script setup lang="ts">
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowRight,
  ArrowLeft,
  Plus,
  Lock,
  Check,
  Banknote,
  CreditCard,
  Landmark,
  Wallet
} from 'lucide-vue-next'
import type { Address, ShippingMethod, PaymentMethod } from '@/types'
import { addressesApi, type ApiAddress } from '@/api/addresses'
import { shippingApi, type ApiShippingMethod } from '@/api/shipping'
import { checkoutApi } from '@/api/checkout'
import { useCartStore } from '@/stores/cart'
import BaseModal from '@/components/BaseModal.vue'
import EmptyState from '@/components/EmptyState.vue'
import { formatPrice } from '@/utils/format'
import { extractErrorMessage } from '@/api/errors'

const router = useRouter()
const cartStore = useCartStore()

const step = ref(1)
const steps = [
  { id: 1, label: 'Shipping' },
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

const shipping = ref<ShippingMethod[]>([])
const selectedShippingId = ref<string | null>(null)
const selectedShipping = ref<ShippingMethod | null>(null)
const loadingShipping = ref(true)
watch(selectedShippingId, (id) => {
  const found = shipping.value.find((s) => s.id === id)
  if (found) selectedShipping.value = found
})

const paymentOptions = [
  { id: 'cod', label: 'Cash on Delivery', desc: 'Pay when your order arrives', icon: Banknote },
  { id: 'card', label: 'Credit / Debit Card', desc: 'Visa, Mastercard, Amex', icon: CreditCard },
  { id: 'bank', label: 'Bank Transfer', desc: 'Direct bank transfer instructions', icon: Landmark },
  { id: 'gateway', label: 'Online Gateway', desc: 'PayPal, Apple Pay, Google Pay', icon: Wallet }
]
const paymentMethod = ref<PaymentMethod>('cod')

const placing = ref(false)
const checkoutError = ref('')

const selectedAddress = computed(
  () => addresses.value.find((a) => a.id === selectedAddressId.value) ?? addresses.value[0]
)
const shippingPrice = computed(() => (step.value > 1 && selectedShipping.value ? selectedShipping.value.price : 0))
const totalWithShipping = computed(() => cartStore.totalAmount + shippingPrice.value)

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
  loadingAddresses.value = true
  try {
    const { data } = await addressesApi.list()
    addresses.value = data.data.map(mapAddressFromApi)
    if (addresses.value.length > 0 && selectedAddressId.value === null) {
      const def = addresses.value.find((a) => a.isDefault)
      selectedAddressId.value = def ? def.id : addresses.value[0].id
    }
  } catch {
    addresses.value = []
  } finally {
    loadingAddresses.value = false
  }
}

async function fetchShipping() {
  loadingShipping.value = true
  try {
    const { data } = await shippingApi.getActive()
    shipping.value = data.data.map(mapShippingFromApi)
    if (shipping.value.length > 0 && selectedShippingId.value === null) {
      selectedShippingId.value = shipping.value[0].id
      selectedShipping.value = shipping.value[0]
    }
  } catch {
    shipping.value = []
  } finally {
    loadingShipping.value = false
  }
}

onMounted(() => {
  fetchAddresses()
  fetchShipping()
})

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
  if (!newAddress.fullName.trim() || !newAddress.line1.trim() || !newAddress.city.trim()) return
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
  } catch {
    /* handle silently */
  }
}

async function placeOrder() {
  if (!selectedAddress.value || !selectedShipping.value || placing.value) return
  placing.value = true
  checkoutError.value = ''
  try {
    const { data } = await checkoutApi.begin({
      shipping_method_id: Number(selectedShipping.value.id),
      payment_method: paymentMethod.value,
      coupon_code: cartStore.appliedCoupon?.code,
      address_id: Number(selectedAddress.value.id)
    })
    await checkoutApi.confirm(data.data.order_number)
    // Confirmation clears the cart on the server. Refresh instead of issuing a
    // second destructive request, so a transient refresh failure cannot make a
    // successful order look failed to the customer.
    await cartStore.fetch()
    router.push({ name: 'order-success', params: { orderId: data.data.order_number } })
  } catch (error) {
    checkoutError.value = extractErrorMessage(error, 'We could not place your order. Your cart has been kept so you can review it.')
  } finally {
    placing.value = false
  }
}
</script>

<template>
  <div class="container-app py-8">
    <div v-if="cartStore.isEmpty" class="mx-auto max-w-md">
      <EmptyState
        title="Your cart is empty"
        description="Add some items before checking out."
        ctaLabel="Back to shop"
        @cta="router.push('/shop')"
      />
    </div>

    <template v-else>
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

            <div v-if="loadingAddresses" class="text-sm text-gray-500 dark:text-muted">Loading addresses...</div>

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

            <div class="flex justify-end">
              <button
                type="button"
                class="btn-primary"
                :disabled="!stepAllowed()"
                @click="step = 2"
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

            <div v-else class="space-y-3">
              <label
                v-for="method in shipping"
                :key="method.id"
                class="block cursor-pointer"
              >
                <input
                  v-model="selectedShippingId"
                  type="radio"
                  name="shipping"
                  :value="method.id"
                  class="peer sr-only"
                />
                <div
                  class="flex items-center justify-between gap-4 rounded-xl border-2 border-border-gray dark:border-border-gray p-4 transition-colors peer-checked:border-primary peer-checked:bg-primary/5"
                >
                  <div class="flex items-center gap-3">
                    <span
                      class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 dark:border-gray-600"
                      :class="{ 'border-primary': selectedShippingId === method.id }"
                    >
                      <span v-if="selectedShippingId === method.id" class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                    </span>
                    <div>
                      <p class="font-semibold text-ink dark:text-ink">{{ method.name }}</p>
                      <p class="text-sm text-gray-600 dark:text-muted">{{ method.description }}</p>
                      <span class="chip mt-1">{{ method.etaDays }}–{{ method.etaDays + 1 }} days</span>
                    </div>
                  </div>
                  <div class="text-right">
                    <span v-if="method.price === 0" class="chip bg-accent !text-ink dark:!text-gray-900">Free</span>
                    <span v-else class="font-semibold text-ink dark:text-ink">{{ formatPrice(method.price) }}</span>
                  </div>
                </div>
              </label>
            </div>

            <div class="flex items-center justify-between">
              <button type="button" class="btn-secondary" @click="step = 1">
                <ArrowLeft class="h-4 w-4" />
                Back
              </button>
              <button type="button" class="btn-primary" @click="step = 3">
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

            <div v-if="paymentMethod === 'card'" class="space-y-4 rounded-xl bg-canvas p-4 dark:bg-canvas">
              <div>
                <label class="label" for="card-name">Name on card</label>
                <input id="card-name" type="text" class="input" placeholder="Alex Morgan" />
              </div>
              <div>
                <label class="label" for="card-number">Card number</label>
                <input id="card-number" type="text" class="input" placeholder="1234 5678 9012 3456" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label" for="card-expiry">Expiry MM/YY</label>
                  <input id="card-expiry" type="text" class="input" placeholder="12/28" />
                </div>
                <div>
                  <label class="label" for="card-cvc">CVC</label>
                  <input id="card-cvc" type="text" class="input" placeholder="123" />
                </div>
              </div>
            </div>

            <div v-else-if="paymentMethod === 'bank'" class="rounded-xl bg-canvas p-4 text-sm text-gray-600 dark:text-muted dark:bg-canvas">
              After placing your order, transfer to: A/C 1234-5678-90 · E-KHMER Inc. · Swift code SVUS33
            </div>

            <div v-else-if="paymentMethod === 'gateway'" class="rounded-xl bg-canvas p-4 text-sm text-gray-600 dark:text-muted dark:bg-canvas">
              You'll be redirected to our secure payment partner to complete checkout.
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

            <button type="button" class="btn-primary btn-lg w-full" :disabled="placing" @click="placeOrder">
              <Lock class="h-4 w-4" />
              {{ placing ? 'Placing order…' : `Place Order · ${formatPrice(totalWithShipping)}` }}
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

          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600 dark:text-muted">{{ $t('checkout.subtotal') }}</span>
              <span class="font-medium text-ink dark:text-ink">{{ formatPrice(cartStore.subtotal) }}</span>
            </div>
            <div v-if="cartStore.discountAmount > 0" class="flex justify-between">
              <span class="text-gray-600 dark:text-muted">{{ $t('checkout.discount') }}</span>
              <span class="font-medium text-red-500">−{{ formatPrice(cartStore.discountAmount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600 dark:text-muted">{{ $t('checkout.tax') }}</span>
              <span class="font-medium text-ink dark:text-ink">{{ formatPrice(cartStore.taxAmount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600 dark:text-muted">{{ $t('checkout.shipping') }}</span>
              <span v-if="step === 1" class="text-gray-400 dark:text-gray-500 dark:text-muted">Calculated later</span>
              <span v-else-if="shippingPrice === 0" class="font-medium text-emerald-600">Free</span>
              <span v-else class="font-medium text-ink dark:text-ink">{{ formatPrice(shippingPrice) }}</span>
            </div>
          </div>

          <div class="my-4 border-t border-border-gray dark:border-border-gray"></div>

          <div class="flex justify-between">
            <span class="font-semibold text-ink dark:text-ink">{{ $t('checkout.total') }}</span>
            <span class="text-xl font-bold text-ink dark:text-ink">
              {{ step === 1 ? formatPrice(cartStore.totalAmount) : formatPrice(totalWithShipping) }}
            </span>
          </div>

          <p class="mt-4 text-xs text-gray-500 dark:text-muted">
            {{ cartStore.totalItemCount }} {{ cartStore.totalItemCount === 1 ? 'item' : 'items' }} · Free shipping on orders over $100
          </p>
        </div>
      </div>
    </template>

    <BaseModal
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
        :disabled="!newAddress.fullName.trim() || !newAddress.line1.trim() || !newAddress.city.trim()"
        @click="saveAddress"
      >
        Save Address
      </button>

      <template #footer>
        <button type="button" class="btn-secondary w-full" @click="addressModalOpen = false">
          Cancel
        </button>
      </template>
    </BaseModal>
  </div>
</template>
