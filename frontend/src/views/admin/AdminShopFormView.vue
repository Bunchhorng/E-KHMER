<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi, type AdminShop } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const editing = computed(() => id.value > 0)
const loading = ref(editing.value)
const saving = ref(false)
const error = ref('')
const form = ref<Partial<AdminShop>>({
  name: '', code: '', description: '', logo: '', banner: '', email: '', phone: '', branch_type: '', address_line: '',
  mall: '', city: '', province: '', postal_code: '', country: 'KH', commission_rate: 0, status: 'pending',
})

async function load() {
  if (!editing.value) return
  loading.value = true
  try {
    const { data } = await adminApi.getShop(id.value)
    form.value = data.data
  } catch (e) {
    error.value = extractErrorMessage(e, 'Could not load the shop.')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.value.name?.trim() || saving.value) return
  saving.value = true
  error.value = ''
  try {
    const payload = { ...form.value, name: form.value.name.trim(), country: form.value.country?.toUpperCase() }
    const { data } = editing.value ? await adminApi.updateShop(id.value, payload) : await adminApi.createShop(payload)
    router.replace({ name: 'admin-shop-edit', params: { id: data.data.id } })
  } catch (e) {
    error.value = extractErrorMessage(e, 'Could not save the shop.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-4xl space-y-6">
    <div class="flex items-end justify-between"><div><p class="text-sm font-semibold text-primary">MARKETPLACE</p><h1 class="mt-1 text-2xl font-bold text-ink">{{ editing ? 'Edit shop' : 'Create shop' }}</h1><p class="mt-1 text-sm text-gray-500">Set the shop profile, contact details, location, and marketplace status.</p></div><RouterLink :to="{ name: 'admin-shops' }" class="btn-secondary">Back</RouterLink></div>
    <div v-if="loading" class="card p-10 text-center">Loading...</div>
    <form v-else class="card grid gap-4 p-6 sm:grid-cols-2" @submit.prevent="save">
      <label><span class="label">Shop name</span><input v-model="form.name" class="input" required /></label>
      <label><span class="label">Shop code</span><input v-model="form.code" class="input" placeholder="Generated if empty" /></label>
      <label><span class="label">Branch type</span><input v-model="form.branch_type" class="input" placeholder="Online, flagship, outlet..." /></label>
      <label><span class="label">Commission rate (%)</span><input v-model.number="form.commission_rate" class="input" type="number" min="0" max="100" step="0.01" /></label>
      <label><span class="label">Logo URL</span><input v-model="form.logo" class="input" type="url" placeholder="https://..." /></label>
      <label><span class="label">Banner URL</span><input v-model="form.banner" class="input" type="url" placeholder="https://..." /></label>
      <label class="sm:col-span-2"><span class="label">Description</span><textarea v-model="form.description" class="textarea" rows="4"></textarea></label>
      <label><span class="label">Email</span><input v-model="form.email" class="input" type="email" /></label>
      <label><span class="label">Phone</span><input v-model="form.phone" class="input" /></label>
      <label class="sm:col-span-2"><span class="label">Address</span><input v-model="form.address_line" class="input" /></label>
      <label><span class="label">Mall / building</span><input v-model="form.mall" class="input" /></label>
      <label><span class="label">City</span><input v-model="form.city" class="input" /></label>
      <label><span class="label">Province</span><input v-model="form.province" class="input" /></label>
      <label><span class="label">Postal code</span><input v-model="form.postal_code" class="input" /></label>
      <label><span class="label">Country code</span><input v-model="form.country" class="input" maxlength="2" placeholder="KH" /></label>
      <label><span class="label">Status</span><select v-model="form.status" class="select"><option value="pending">Pending</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="rejected">Rejected</option><option value="closed">Closed</option></select></label>
      <p v-if="error" class="sm:col-span-2 text-sm text-red-600">{{ error }}</p>
      <div class="sm:col-span-2"><button class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save shop' }}</button></div>
    </form>
  </div>
</template>
