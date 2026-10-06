<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { sellerApi, type SellerShop } from '@/api/seller'
import { extractErrorMessage } from '@/api/errors'

const shop = ref<SellerShop | null>(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const form = ref({ name: '', description: '', email: '', phone: '', address_line: '', city: '', province: '', postal_code: '', country: 'KH' })

async function load() {
  loading.value = true; error.value = ''
  try { const { data } = await sellerApi.getApplication(); shop.value = data.data ?? null } catch (e) { error.value = extractErrorMessage(e, 'Could not load your seller application.') } finally { loading.value = false }
}
async function submit() {
  if (!form.value.name.trim() || submitting.value) return
  submitting.value = true; error.value = ''
  try { const { data } = await sellerApi.apply({ ...form.value, name: form.value.name.trim() }); shop.value = data.data } catch (e) { error.value = extractErrorMessage(e, 'Could not submit your application.') } finally { submitting.value = false }
}
onMounted(load)
</script>

<template>
  <div class="container-app mx-auto max-w-3xl py-10">
    <div class="mb-7"><p class="text-sm font-semibold text-primary">SELLER CENTER</p><h1 class="mt-1 text-3xl font-bold text-ink">Become an E-KHMER seller</h1><p class="mt-2 text-gray-500">Submit your shop details. A marketplace administrator will review your application.</p></div>
    <div v-if="loading" class="card p-10 text-center text-sm text-gray-500">Loading your application…</div>
    <div v-else-if="shop" class="card p-6">
      <span class="chip" :class="shop.status === 'active' ? '!bg-emerald-100 !text-emerald-700' : shop.status === 'rejected' ? '!bg-red-100 !text-red-700' : ''">{{ shop.status }}</span>
      <h2 class="mt-3 text-xl font-bold text-ink">{{ shop.name }}</h2>
      <p class="mt-2 text-sm text-gray-600">{{ shop.status === 'active' ? 'Your shop is active. You can open Seller Center.' : shop.status === 'rejected' ? shop.rejection_reason : 'Your application is waiting for review.' }}</p>
      <RouterLink v-if="shop.status === 'active'" to="/seller" class="btn-primary mt-5">Open Seller Center</RouterLink>
    </div>
    <form v-else class="card grid gap-4 p-6 sm:grid-cols-2" @submit.prevent="submit">
      <label class="sm:col-span-2"><span class="label">Shop name</span><input v-model="form.name" class="input" required maxlength="190" /></label>
      <label class="sm:col-span-2"><span class="label">Description</span><textarea v-model="form.description" class="textarea" rows="4" maxlength="5000"></textarea></label>
      <label><span class="label">Public email</span><input v-model="form.email" class="input" type="email" /></label>
      <label><span class="label">Phone</span><input v-model="form.phone" class="input" /></label>
      <label class="sm:col-span-2"><span class="label">Address</span><input v-model="form.address_line" class="input" /></label>
      <label><span class="label">City</span><input v-model="form.city" class="input" /></label>
      <label><span class="label">Province</span><input v-model="form.province" class="input" /></label>
      <p v-if="error" class="sm:col-span-2 text-sm text-red-600">{{ error }}</p>
      <div class="sm:col-span-2"><button class="btn-primary" :disabled="submitting">{{ submitting ? 'Submitting…' : 'Submit application' }}</button></div>
    </form>
  </div>
</template>
