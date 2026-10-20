<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ImagePlus, Save, UploadCloud, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { AdminBrand } from '@/api/admin'
import { mediaApi } from '@/api/uploads'

const { t } = useI18n()

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => Boolean(route.params.id))
const brandId = computed(() => (isEdit.value ? Number(route.params.id) : null))

const form = reactive({
  name: '',
  slug: '',
  website: '',
  status: 'Active',
  logoUrl: null as string | null
})

const errors = reactive<Record<string, string>>({})

const logoFile = ref<File | null>(null)
const logoInput = ref<HTMLInputElement | null>(null)
const logoLink = ref('')
const logoPreviewFailed = ref(false)
let logoObjectUrl: string | null = null

function releaseLogoObjectUrl() {
  if (logoObjectUrl) URL.revokeObjectURL(logoObjectUrl)
  logoObjectUrl = null
}

function isValidLogoLink(value: string): boolean {
  try {
    return ['http:', 'https:'].includes(new URL(value).protocol)
  } catch {
    return false
  }
}

function onLogoLinkChanged() {
  releaseLogoObjectUrl()
  logoFile.value = null
  if (logoInput.value) logoInput.value.value = ''
  const url = logoLink.value.trim()
  errors.logo = url && !isValidLogoLink(url) ? t('admin.brands.logo_url_invalid') : ''
  form.logoUrl = url && !errors.logo ? url : null
  logoPreviewFailed.value = false
}

function slugify(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
}

const formTitle = computed(() =>
  isEdit.value
    ? t('admin.brands.edit_brand')
    : t('admin.brands.add_brand')
)

function onLogoPicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file || !file.type.startsWith('image/')) return
  releaseLogoObjectUrl()
  logoLink.value = ''
  errors.logo = ''
  logoPreviewFailed.value = false
  logoFile.value = file
  logoObjectUrl = URL.createObjectURL(file)
  form.logoUrl = logoObjectUrl
}

function removeLogoPreview() {
  releaseLogoObjectUrl()
  logoFile.value = null
  logoLink.value = ''
  errors.logo = ''
  logoPreviewFailed.value = false
  form.logoUrl = null
  if (logoInput.value) logoInput.value.value = ''
}

const saving = ref(false)
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
  try {
    const { data: resp } = await adminApi.listBrands()
    if (isEdit.value && brandId.value) {
      const brand = resp.data.find((b: AdminBrand) => b.id === brandId.value)
      if (!brand) {
        router.replace({ name: 'admin-brands' })
        return
      }
      form.name = brand.name
      form.slug = brand.slug
      form.website = brand.description ?? ''
      form.status = brand.is_active ? 'Active' : 'Draft'
      form.logoUrl = brand.logo
      logoLink.value = brand.logo && isValidLogoLink(brand.logo) ? brand.logo : ''
    }
  } catch {
    if (isEdit.value) {
      showToast(t('admin.brands.toast_load_error'))
      router.replace({ name: 'admin-brands' })
    }
  }
})

onUnmounted(() => {
  releaseLogoObjectUrl()
  if (toastTimer) clearTimeout(toastTimer)
})

function validate(): boolean {
  errors.name = form.name.trim() ? '' : t('admin.brands.toast_enter_name')
  errors.logo = logoLink.value.trim() && !isValidLogoLink(logoLink.value.trim())
    ? t('admin.brands.logo_url_invalid')
    : ''
  return Object.values(errors).every((v) => v === '')
}

async function save() {
  if (!validate()) {
    showToast(errors.name || errors.logo || '')
    return
  }

  const name = form.name.trim()
  const slug = form.slug.trim() || slugify(name)

  const payload: Record<string, unknown> = {
    name,
    slug,
    description: form.website.trim() || null,
    is_active: form.status === 'Active'
  }
  if (!logoFile.value) payload.logo = form.logoUrl

  try {
    saving.value = true
    const targetId = brandId.value

    if (isEdit.value && targetId) {
      await adminApi.updateBrand(targetId, payload)
      if (logoFile.value) {
        await mediaApi.uploadBrandLogo(targetId, logoFile.value)
      }
      showToast(t('admin.brands.toast_updated', { name }))
    } else {
      const { data: resp } = await adminApi.createBrand(payload)
      if (logoFile.value) {
        await mediaApi.uploadBrandLogo(resp.data.id, logoFile.value)
      }
      showToast(t('admin.brands.toast_added', { name }))
    }
    router.push({ name: 'admin-brands' })
  } catch {
    showToast(t('admin.brands.toast_save_error'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="space-y-6 pb-16" @submit.prevent="save()">
    <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="section-eyebrow">{{ $t('admin.products.catalog') }}</div>
        <h1 class="text-xl font-bold text-ink">{{ formTitle }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="btn-secondary btn-sm" type="button" @click="router.push({ name: 'admin-brands' })">{{ $t('actions.cancel') }}</button>
        <button class="btn-primary btn-sm" type="submit" :disabled="saving">
          <Save class="h-4 w-4" />
          {{ $t('admin.brands.save_brand') }}
        </button>
      </div>
    </div>

    <div class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_1') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.basic_information') }}</h2>

      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="label" for="brand-name">{{ $t('admin.brands.name_label') }}</label>
          <input
            id="brand-name"
            v-model="form.name"
            class="input"
            :class="{ 'input-error': errors.name }"
            :placeholder="$t('admin.brands.name_placeholder')"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
        </div>

        <div>
          <label class="label" for="brand-status">{{ $t('admin.products.status_label') }}</label>
          <select id="brand-status" v-model="form.status" class="select">
            <option value="Active">{{ $t('status.active') }}</option>
            <option value="Draft">{{ $t('status.draft') }}</option>
          </select>
        </div>

        <div>
          <label class="label" for="brand-slug">{{ $t('admin.brands.slug_label') }}</label>
          <input id="brand-slug" v-model="form.slug" class="input" :placeholder="$t('admin.brands.slug_placeholder')" />
        </div>

        <div class="sm:col-span-2">
          <label class="label" for="brand-website">{{ $t('admin.brands.website_label') }}</label>
          <input id="brand-website" v-model="form.website" class="input" :placeholder="$t('admin.brands.website_placeholder')" />
        </div>
      </div>
    </div>

    <div class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_5') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.brands.logo_label') }}</h2>

      <div class="mt-5 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full bg-canvas">
          <img v-if="form.logoUrl && !logoPreviewFailed" :src="form.logoUrl" alt="Logo" class="h-full w-full object-cover" @error="logoPreviewFailed = true" />
          <UploadCloud v-else class="h-8 w-8 text-gray-300" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="btn-secondary btn-sm" @click="logoInput?.click()">
            <ImagePlus class="h-4 w-4" />
            {{ form.logoUrl ? $t('admin.brands.change_logo') : $t('admin.brands.upload_logo') }}
          </button>
          <button v-if="form.logoUrl" type="button" class="btn-ghost btn-sm" @click="removeLogoPreview()">
            <X class="h-4 w-4" />
            {{ $t('admin.brands.remove_logo') }}
          </button>
        </div>
        <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoPicked" />
      </div>
      <div class="mt-4 max-w-xl">
        <label class="label" for="brand-logo-url">{{ $t('admin.brands.logo_url_label') }}</label>
        <input
          id="brand-logo-url"
          v-model="logoLink"
          type="url"
          class="input"
          :class="{ 'input-error': errors.logo }"
          placeholder="https://example.com/logo.png"
          :aria-invalid="Boolean(errors.logo)"
          :aria-describedby="errors.logo ? 'brand-logo-url-error' : 'brand-logo-url-hint'"
          @input="onLogoLinkChanged"
        />
        <p id="brand-logo-url-hint" class="mt-1 text-xs text-muted">{{ $t('admin.brands.logo_url_hint') }}</p>
        <p v-if="errors.logo" id="brand-logo-url-error" class="mt-1 text-xs text-red-600" role="alert">{{ errors.logo }}</p>
        <p v-if="logoPreviewFailed" class="mt-1 text-xs text-red-600" role="alert">{{ $t('admin.brands.logo_preview_error') }}</p>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <button class="btn-secondary" type="button" @click="router.push({ name: 'admin-brands' })">{{ $t('actions.cancel') }}</button>
      <button class="btn-primary" type="submit" :disabled="saving">
        <Save class="h-4 w-4" />
        {{ $t('admin.brands.save_brand') }}
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
