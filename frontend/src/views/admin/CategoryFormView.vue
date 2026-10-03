<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ImagePlus, Save, UploadCloud, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { AdminCategory } from '@/api/admin'
import { mediaApi } from '@/api/uploads'

const { t } = useI18n()

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => Boolean(route.params.id))

interface ParentOption {
  id: number
  name: string
  depth: number
}

const form = reactive({
  name: '',
  slug: '',
  parentId: null as number | null,
  sortOrder: 0,
  status: 'Active',
  description: '',
  imageUrl: null as string | null
})

const errors = reactive<Record<string, string>>({})

const categories = ref<AdminCategory[]>([])
const imageFile = ref<File | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
// Set when the admin clears a stored image, so saving can detach it server-side
// instead of silently keeping the old file.
const imageRemoved = ref(false)

const categoryId = computed(() => (isEdit.value ? Number(route.params.id) : null))

const formTitle = computed(() => {
  if (isEdit.value) return t('admin.categories.edit_category')
  if (route.query.parent) return t('admin.categories.add_child_category')
  return t('admin.categories.add_category')
})

function slugify(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
}

function findNode(list: AdminCategory[], id: number): AdminCategory | undefined {
  for (const c of list) {
    if (c.id === id) return c
    if (c.children) {
      const found = findNode(c.children, id)
      if (found) return found
    }
  }
  return undefined
}

function collectDescendantIds(node: AdminCategory, ids: number[] = []): number[] {
  for (const child of node.children ?? []) {
    ids.push(child.id)
    collectDescendantIds(child, ids)
  }
  return ids
}

const parentOptions = computed<ParentOption[]>(() => {
  const out: ParentOption[] = []
  const excluded = new Set<number>()
  if (categoryId.value) {
    const self = findNode(categories.value, categoryId.value)
    excluded.add(categoryId.value)
    if (self) collectDescendantIds(self).forEach((id) => excluded.add(id))
  }

  const walk = (list: AdminCategory[], depth: number) => {
    for (const c of list) {
      if (!excluded.has(c.id)) out.push({ id: c.id, name: c.name, depth })
      if (c.children?.length) walk(c.children, depth + 1)
    }
  }
  walk(categories.value, 0)
  return out
})

function parentIndent(option: ParentOption): string {
  return `${'— '.repeat(option.depth)}${option.name}`
}

// AdminImageUploadRequest caps images at 5 MB; catching it here turns a slow
// round trip into immediate feedback.
const MAX_IMAGE_BYTES = 5 * 1024 * 1024

function onImagePicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  if (!file.type.startsWith('image/')) {
    input.value = ''
    showToast(t('admin.categories.toast_image_type'))
    return
  }

  if (file.size > MAX_IMAGE_BYTES) {
    input.value = ''
    showToast(
      t('admin.categories.toast_image_too_large', { mb: Math.round(MAX_IMAGE_BYTES / 1024 / 1024) })
    )
    return
  }

  imageFile.value = file
  imageRemoved.value = false
  form.imageUrl = URL.createObjectURL(file)
}

function removeImagePreview() {
  imageFile.value = null
  imageRemoved.value = true
  form.imageUrl = null
  if (imageInput.value) imageInput.value.value = ''
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
    const { data: resp } = await adminApi.listCategories()
    categories.value = resp.data

    if (isEdit.value && categoryId.value) {
      const node = findNode(categories.value, categoryId.value)
      if (!node) {
        router.replace({ name: 'admin-categories' })
        return
      }
      form.name = node.name
      form.slug = node.slug
      form.parentId = node.parent_id
      form.description = node.description ?? ''
      form.sortOrder = node.sort_order ?? 0
      form.status = node.is_active ? 'Active' : 'Draft'
      form.imageUrl = node.image
    } else if (route.query.parent) {
      form.parentId = Number(route.query.parent) || null
    }
  } catch {
    if (isEdit.value) {
      showToast(t('admin.categories.toast_load_error'))
      router.replace({ name: 'admin-categories' })
    }
  }
})

function validate(): boolean {
  errors.name = form.name.trim() ? '' : t('admin.categories.toast_enter_name')
  return Object.values(errors).every((v) => v === '')
}

function clearErrors() {
  for (const key of Object.keys(errors)) delete errors[key]
}

/**
 * Copy a 422 body onto the matching inputs so the admin sees which field the
 * server refused instead of one generic toast.
 */
function mapServerErrors(error: unknown) {
  const payload = (error as { response?: { data?: { errors?: Record<string, string[]> } } })
    ?.response?.data

  const fieldErrors = payload?.errors

  if (!fieldErrors) return false

  for (const [field, messages] of Object.entries(fieldErrors)) {
    errors[field] = messages?.[0] ?? ''
  }

  return true
}

async function syncImage(categoryId: number) {
  if (imageRemoved.value) {
    await mediaApi.deleteCategoryImage(categoryId)
  } else if (imageFile.value) {
    await mediaApi.uploadCategoryImage(categoryId, imageFile.value)
  }
}

async function save() {
  if (!validate()) {
    showToast(t('admin.categories.toast_enter_name'))
    return
  }

  clearErrors()

  const name = form.name.trim()
  const slug = form.slug.trim() || slugify(name)

  const payload: Record<string, unknown> = {
    name,
    slug,
    description: form.description.trim() || null,
    parent_id: form.parentId,
    sort_order: Number(form.sortOrder) || 0,
    is_active: form.status === 'Active'
  }

  try {
    saving.value = true
    const targetId = categoryId.value

    if (isEdit.value && targetId) {
      await adminApi.updateCategory(targetId, payload)
      await syncImage(targetId)
      showToast(t('admin.categories.toast_updated', { name }))
    } else {
      const { data: resp } = await adminApi.createCategory(payload)
      await syncImage(resp.data.id)
      showToast(
        route.query.parent
          ? t('admin.categories.toast_added_child', { name })
          : t('admin.categories.toast_added', { name })
      )
    }
    router.push({ name: 'admin-categories' })
  } catch (error) {
    if (!mapServerErrors(error)) {
      showToast(t('admin.categories.toast_save_error'))
    }
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
        <button class="btn-secondary btn-sm" type="button" @click="router.push({ name: 'admin-categories' })">{{ $t('actions.cancel') }}</button>
        <button class="btn-primary btn-sm" type="submit" :disabled="saving">
          <Save class="h-4 w-4" />
          {{ $t('admin.categories.save_category') }}
        </button>
      </div>
    </div>

    <div class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_1') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.products.basic_information') }}</h2>

      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="label" for="cat-name">{{ $t('admin.categories.name_label') }}</label>
          <input
            id="cat-name"
            v-model="form.name"
            class="input"
            :class="{ 'input-error': errors.name }"
            :placeholder="$t('admin.categories.name_placeholder')"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
        </div>

        <div>
          <label class="label" for="cat-parent">{{ $t('admin.categories.parent_label') }}</label>
          <select id="cat-parent" v-model="form.parentId" class="select" :class="{ 'input-error': errors.parent_id }">
            <option :value="null">{{ $t('admin.categories.parent_none') }}</option>
            <option v-for="opt in parentOptions" :key="opt.id" :value="opt.id">{{ parentIndent(opt) }}</option>
          </select>
          <p v-if="errors.parent_id" class="mt-1 text-xs text-red-600">{{ errors.parent_id }}</p>
        </div>

        <div>
          <label class="label" for="cat-status">{{ $t('admin.products.status_label') }}</label>
          <select id="cat-status" v-model="form.status" class="select">
            <option value="Active">{{ $t('status.active') }}</option>
            <option value="Draft">{{ $t('status.draft') }}</option>
          </select>
        </div>

        <div>
          <label class="label" for="cat-slug">{{ $t('admin.categories.slug_label') }}</label>
          <input id="cat-slug" v-model="form.slug" class="input" :class="{ 'input-error': errors.slug }" :placeholder="$t('admin.categories.slug_placeholder')" />
          <p v-if="errors.slug" class="mt-1 text-xs text-red-600">{{ errors.slug }}</p>
        </div>

        <div>
          <label class="label" for="cat-sort-order">{{ $t('admin.categories.sort_order_label') }}</label>
          <input id="cat-sort-order" v-model.number="form.sortOrder" class="input" :class="{ 'input-error': errors.sort_order }" type="number" min="0" placeholder="0" />
          <p v-if="errors.sort_order" class="mt-1 text-xs text-red-600">{{ errors.sort_order }}</p>
        </div>

        <div class="sm:col-span-2">
          <label class="label" for="cat-description">{{ $t('admin.categories.description_label') }}</label>
          <textarea
            id="cat-description"
            v-model="form.description"
            class="textarea"
            :class="{ 'input-error': errors.description }"
            rows="4"
            :placeholder="$t('admin.categories.description_placeholder')"
          ></textarea>
          <p v-if="errors.description" class="mt-1 text-xs text-red-600">{{ errors.description }}</p>
        </div>
      </div>
    </div>

    <div class="card p-6">
      <div class="section-eyebrow">{{ $t('admin.products.step_5') }}</div>
      <h2 class="mt-2 text-lg font-semibold">{{ $t('admin.categories.image_label') }}</h2>

      <div class="mt-5 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-canvas">
          <img v-if="form.imageUrl" :src="form.imageUrl" alt="Category" class="h-full w-full object-cover" />
          <UploadCloud v-else class="h-8 w-8 text-gray-300" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="btn-secondary btn-sm" @click="imageInput?.click()">
            <ImagePlus class="h-4 w-4" />
            {{ form.imageUrl ? $t('admin.categories.change_image') : $t('admin.categories.upload_image') }}
          </button>
          <button v-if="form.imageUrl" type="button" class="btn-ghost btn-sm" @click="removeImagePreview()">
            <X class="h-4 w-4" />
            {{ $t('admin.categories.remove_image') }}
          </button>
        </div>
        <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="onImagePicked" />
      </div>
      <p v-if="errors.image" class="mt-2 text-xs text-red-600">{{ errors.image }}</p>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <button class="btn-secondary" type="button" @click="router.push({ name: 'admin-categories' })">{{ $t('actions.cancel') }}</button>
      <button class="btn-primary" type="submit" :disabled="saving">
        <Save class="h-4 w-4" />
        {{ $t('admin.categories.save_category') }}
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