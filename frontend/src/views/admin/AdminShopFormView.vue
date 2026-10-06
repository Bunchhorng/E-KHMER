<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Image, Link, MapPin, Save, Settings2, Store, Upload, UserRound } from 'lucide-vue-next'
import { adminApi, type AdminShop } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'
import { mediaApi } from '@/api/uploads'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const editing = computed(() => id.value > 0)
const loading = ref(editing.value)
const saving = ref(false)
const error = ref('')
const logoSource = ref<'upload' | 'url'>('url')
const bannerSource = ref<'upload' | 'url'>('url')
const uploading = ref<'logo' | 'banner' | null>(null)
const form = ref<Partial<AdminShop>>({
  name: '', slug: '', code: '', description: '', logo: '', banner: '', email: '', phone: '', branch_type: '', address_line: '',
  mall: '', city: '', province: '', postal_code: '', country: 'KH', commission_rate: 0, status: 'pending'
})

async function load() {
  if (!editing.value) return
  loading.value = true
  try { form.value = (await adminApi.getShop(id.value)).data.data } catch (e) { error.value = extractErrorMessage(e, 'Could not load the shop.') } finally { loading.value = false }
}
async function save() {
  if (!form.value.name?.trim() || saving.value) return
  saving.value = true; error.value = ''
  try {
    const payload = { ...form.value, name: form.value.name.trim(), country: form.value.country?.toUpperCase() }
    if (!payload.slug?.trim()) delete payload.slug
    else payload.slug = payload.slug.trim()
    const { data } = editing.value ? await adminApi.updateShop(id.value, payload) : await adminApi.createShop(payload)
    router.replace({ name: 'admin-shop-edit', params: { id: data.data.id } })
  } catch (e) { error.value = extractErrorMessage(e, 'Could not save the shop.') } finally { saving.value = false }
}
async function uploadMedia(kind: 'logo' | 'banner', event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploading.value = kind
  error.value = ''
  try {
    const { data } = await mediaApi.uploadImage(file, 'shops')
    form.value[kind] = data.data.path
  } catch (e) { error.value = extractErrorMessage(e, `Could not upload the ${kind} image.`) } finally {
    uploading.value = null
    ;(event.target as HTMLInputElement).value = ''
  }
}
onMounted(load)
</script>

<template>
  <div class="mx-auto max-w-[1320px] space-y-4 sm:space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4"><div><div class="mb-1 text-xs text-gray-400 dark:text-gray-500"><RouterLink :to="{ name: 'admin-shops' }" class="hover:text-primary">Shops</RouterLink><span class="mx-2">›</span>{{ editing ? 'Update Shop' : 'Create Shop' }}</div><h1 class="text-2xl font-bold tracking-tight text-ink">{{ editing ? 'Update Shop' : 'Create Shop' }}</h1><p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ editing ? 'Edit shop information and settings. Changes will be saved immediately.' : 'Add a new shop to the marketplace. Fill in the information below.' }}</p></div><RouterLink :to="{ name: 'admin-shops' }" class="btn-secondary !px-4 !py-2">Cancel</RouterLink></section>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-500 dark:text-muted">Loading shop information...</div>
    <form v-else class="space-y-4" @submit.prevent="save">
      <section class="grid gap-4 lg:grid-cols-2">
        <article class="card p-4 sm:p-5"><div class="mb-4 flex items-start gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"><Store class="h-4 w-4" /></div><div><h2 class="text-sm font-semibold text-ink">Shop Information</h2><p class="text-xs text-gray-500 dark:text-muted">Basic information about the shop.</p></div></div><div class="space-y-3"><label class="block"><span class="label">Shop Name <span class="text-red-500">*</span></span><input v-model="form.name" class="input" placeholder="Enter shop name" required /></label><label class="block"><span class="label">Shop Slug</span><input v-model="form.slug" class="input" placeholder="e.g. tech-store-cambodia" /><span class="mt-1 block text-[11px] text-gray-400">URL-friendly version of the shop name.</span></label><label class="block"><span class="label">Shop Code</span><input v-model="form.code" class="input" placeholder="Generated if empty" /></label><label class="block"><span class="label">Description</span><textarea v-model="form.description" class="textarea min-h-28" maxlength="2000" placeholder="Describe the shop..." /><span class="mt-1 block text-right text-[11px] text-gray-400">{{ form.description?.length ?? 0 }}/2000</span></label></div></article>

        <article class="card p-4 sm:p-5"><div class="mb-4 flex items-start gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"><UserRound class="h-4 w-4" /></div><div><h2 class="text-sm font-semibold text-ink">Contact Information</h2><p class="text-xs text-gray-500 dark:text-muted">Contact details used for shop management.</p></div></div><div class="space-y-3"><label class="block"><span class="label">Email</span><input v-model="form.email" class="input" type="email" placeholder="owner@example.com" /></label><label class="block"><span class="label">Phone</span><input v-model="form.phone" class="input" placeholder="+855 10 000 000" /></label><label class="block"><span class="label">Branch Type</span><input v-model="form.branch_type" class="input" placeholder="Online, flagship, outlet..." /></label><label class="block"><span class="label">Commission Rate (%)</span><input v-model.number="form.commission_rate" class="input" type="number" min="0" max="100" step="0.01" /></label></div></article>
      </section>

      <section class="grid gap-4 lg:grid-cols-2">
        <article class="card p-4 sm:p-5">
          <div class="mb-4 flex items-start gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"><Image class="h-4 w-4" /></div><div><h2 class="text-sm font-semibold text-ink">Branding / Media</h2><p class="text-xs text-gray-500 dark:text-muted">Upload an image or add a public URL for the shop logo and cover image.</p></div></div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div><span class="label">Shop Logo</span><div class="mt-1 flex h-24 items-center justify-center overflow-hidden rounded-lg border border-dashed border-border-gray bg-canvas/50"><img v-if="form.logo" :src="form.logo" alt="Shop logo preview" class="h-full w-full object-cover" /><Image v-else class="h-5 w-5 text-gray-400" /></div><div class="mt-2 grid grid-cols-2 rounded-lg bg-canvas p-1"><button type="button" class="rounded-md px-2 py-1.5 text-xs font-semibold" :class="logoSource === 'upload' ? 'bg-surface text-primary shadow-sm' : 'text-gray-500 dark:text-muted'" @click="logoSource = 'upload'"><Upload class="mr-1 inline h-3.5 w-3.5" />Upload</button><button type="button" class="rounded-md px-2 py-1.5 text-xs font-semibold" :class="logoSource === 'url' ? 'bg-surface text-primary shadow-sm' : 'text-gray-500 dark:text-muted'" @click="logoSource = 'url'"><Link class="mr-1 inline h-3.5 w-3.5" />URL</button></div><label v-if="logoSource === 'upload'" class="btn-secondary mt-2 w-full cursor-pointer !px-3 !py-2 text-xs"><Upload class="h-4 w-4" />{{ uploading === 'logo' ? 'Uploading...' : 'Choose logo image' }}<input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp,image/gif" :disabled="uploading !== null" @change="uploadMedia('logo', $event)" /></label><input v-else v-model="form.logo" class="input mt-2" type="url" placeholder="https://..." /></div>
            <div><span class="label">Cover Image</span><div class="mt-1 flex h-24 items-center justify-center overflow-hidden rounded-lg border border-dashed border-border-gray bg-canvas/50"><img v-if="form.banner" :src="form.banner" alt="Cover image preview" class="h-full w-full object-cover" /><Image v-else class="h-5 w-5 text-gray-400" /></div><div class="mt-2 grid grid-cols-2 rounded-lg bg-canvas p-1"><button type="button" class="rounded-md px-2 py-1.5 text-xs font-semibold" :class="bannerSource === 'upload' ? 'bg-surface text-primary shadow-sm' : 'text-gray-500 dark:text-muted'" @click="bannerSource = 'upload'"><Upload class="mr-1 inline h-3.5 w-3.5" />Upload</button><button type="button" class="rounded-md px-2 py-1.5 text-xs font-semibold" :class="bannerSource === 'url' ? 'bg-surface text-primary shadow-sm' : 'text-gray-500 dark:text-muted'" @click="bannerSource = 'url'"><Link class="mr-1 inline h-3.5 w-3.5" />URL</button></div><label v-if="bannerSource === 'upload'" class="btn-secondary mt-2 w-full cursor-pointer !px-3 !py-2 text-xs"><Upload class="h-4 w-4" />{{ uploading === 'banner' ? 'Uploading...' : 'Choose cover image' }}<input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp,image/gif" :disabled="uploading !== null" @change="uploadMedia('banner', $event)" /></label><input v-else v-model="form.banner" class="input mt-2" type="url" placeholder="https://..." /></div>
          </div>
        </article>

        <article class="card p-4 sm:p-5"><div class="mb-4 flex items-start gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"><Settings2 class="h-4 w-4" /></div><div><h2 class="text-sm font-semibold text-ink">Status & Settings</h2><p class="text-xs text-gray-500 dark:text-muted">Set the initial status and marketplace settings.</p></div></div><div class="grid gap-3 sm:grid-cols-2"><label class="block sm:col-span-2"><span class="label">Status <span class="text-red-500">*</span></span><select v-model="form.status" class="select"><option value="pending">Pending</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="rejected">Rejected</option><option value="closed">Closed</option></select></label><label class="block"><span class="label">Country Code</span><input v-model="form.country" class="input uppercase" maxlength="2" placeholder="KH" /></label><label class="block"><span class="label">Postal Code</span><input v-model="form.postal_code" class="input" placeholder="12000" /></label></div></article>
      </section>

      <section class="card p-4 sm:p-5"><div class="mb-4 flex items-start gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"><MapPin class="h-4 w-4" /></div><div><h2 class="text-sm font-semibold text-ink">Shop Location</h2><p class="text-xs text-gray-500 dark:text-muted">Optional address details for this shop.</p></div></div><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label class="block sm:col-span-2"><span class="label">Address</span><input v-model="form.address_line" class="input" placeholder="Street address" /></label><label class="block"><span class="label">Mall / Building</span><input v-model="form.mall" class="input" placeholder="Building name" /></label><label class="block"><span class="label">City</span><input v-model="form.city" class="input" placeholder="Phnom Penh" /></label><label class="block"><span class="label">Province</span><input v-model="form.province" class="input" placeholder="Province" /></label></div></section>

      <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">{{ error }}</p>
      <div class="flex items-center justify-between border-t border-border-gray pt-4"><RouterLink :to="{ name: 'admin-shops' }" class="btn-secondary !px-4 !py-2">Cancel</RouterLink><button class="btn-primary !px-4 !py-2" :disabled="saving"><Save class="h-4 w-4" />{{ saving ? 'Saving...' : editing ? 'Update Shop' : 'Create Shop' }}</button></div>
    </form>
  </div>
</template>
