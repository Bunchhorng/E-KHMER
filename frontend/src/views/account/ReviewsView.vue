<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link2, Loader2, Pencil, Star, Trash2 } from 'lucide-vue-next'
import EmptyState from '@/components/EmptyState.vue'
import StarRating from '@/components/StarRating.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import { reviewsApi } from '@/api/reviews'
import type { ApiReview } from '@/api/reviews'
import { formatDate } from '@/utils/format'
import { extractErrorMessage } from '@/api/errors'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const reviews = ref<ApiReview[]>([])
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const savingId = ref<number | null>(null)
const editingId = ref<number | null>(null)
const editRating = ref(5)
const editTitle = ref('')
const editBody = ref('')

async function loadReviews() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await reviewsApi.listMine()
    reviews.value = data.data
  } catch {
    error.value = t('account.reviews_load_error')
  } finally {
    loading.value = false
  }
}

onMounted(loadReviews)

function startEdit(review: ApiReview) {
  actionError.value = ''
  editingId.value = review.id
  editRating.value = review.rating
  editTitle.value = review.title ?? ''
  editBody.value = review.body ?? ''
}

async function saveEdit(review: ApiReview) {
  if (savingId.value !== null) return
  savingId.value = review.id
  actionError.value = ''
  try {
    const { data } = await reviewsApi.update(review.id, {
      rating: editRating.value,
      title: editTitle.value.trim() || null,
      body: editBody.value.trim() || null
    })
    const index = reviews.value.findIndex((item) => item.id === review.id)
    if (index >= 0) reviews.value[index] = data.data
    editingId.value = null
  } catch (requestError) {
    actionError.value = extractErrorMessage(requestError, 'Could not update your review.')
  } finally {
    savingId.value = null
  }
}

async function deleteReview(review: ApiReview) {
  if (savingId.value !== null || !window.confirm('Delete this review?')) return
  savingId.value = review.id
  actionError.value = ''
  try {
    await reviewsApi.remove(review.id)
    reviews.value = reviews.value.filter((item) => item.id !== review.id)
    if (editingId.value === review.id) editingId.value = null
  } catch (requestError) {
    actionError.value = extractErrorMessage(requestError, 'Could not delete your review.')
  } finally {
    savingId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('nav.reviews') }}</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ $t('account.reviews_approved_note') }}</p>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <Loader2 class="h-6 w-6 animate-spin text-primary" />
    </div>

    <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      <p>{{ error }}</p>
      <button type="button" class="btn-secondary btn-sm mt-3" @click="loadReviews">{{ $t('actions.retry') }}</button>
    </div>

    <div v-else-if="reviews.length" class="space-y-4">
      <p v-if="actionError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ actionError }}</p>
      <div v-for="r in reviews" :key="r.id" class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <StarRating :value="r.rating" size="sm" />
              <BaseBadge :variant="r.status === 'approved' ? 'success' : r.status === 'rejected' ? 'danger' : 'neutral'">
                {{ $t(`account.review_status_${r.status}`) }}
              </BaseBadge>
            </div>
            <template v-if="editingId === r.id">
              <div class="mt-3 flex items-center gap-1">
                <button v-for="star in 5" :key="star" type="button" class="text-xl" :class="star <= editRating ? 'text-accent' : 'text-gray-300'" @click="editRating = star">★</button>
              </div>
              <input v-model="editTitle" class="input mt-3" maxlength="120" placeholder="Review title (optional)" />
              <textarea v-model="editBody" class="textarea mt-3" rows="4" maxlength="3000" placeholder="Share your experience (optional)"></textarea>
              <div class="mt-3 flex gap-2">
                <button type="button" class="btn-primary btn-sm" :disabled="savingId === r.id" @click="saveEdit(r)">{{ savingId === r.id ? $t('common.saving') : $t('actions.save') }}</button>
                <button type="button" class="btn-secondary btn-sm" :disabled="savingId === r.id" @click="editingId = null">{{ $t('actions.cancel') }}</button>
              </div>
            </template>
            <template v-else>
              <div v-if="r.title?.trim()" class="mt-2 font-semibold text-ink dark:text-ink">{{ r.title }}</div>
              <p v-if="r.body?.trim()" class="mt-1 text-sm text-gray-600 dark:text-muted">{{ r.body }}</p>
            </template>
            <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ formatDate(r.created_at) }}</div>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <RouterLink v-if="r.product" :to="`/product/${r.product.slug}`" class="inline-flex items-center gap-1.5 rounded-lg border border-border-gray px-3 py-1.5 text-xs font-medium text-primary hover:bg-canvas dark:border-border-gray dark:hover:bg-canvas">
              <Link2 class="h-3.5 w-3.5" />
              <span class="max-w-[160px] truncate">{{ r.product.name }}</span>
            </RouterLink>
            <button type="button" class="btn-icon" :aria-label="$t('actions.edit')" :disabled="savingId === r.id" @click="startEdit(r)"><Pencil class="h-4 w-4" /></button>
            <button type="button" class="btn-icon text-red-600" :aria-label="$t('actions.delete')" :disabled="savingId === r.id" @click="deleteReview(r)"><Trash2 class="h-4 w-4" /></button>
          </div>
        </div>
      </div>
    </div>

    <EmptyState
      v-else
      :title="$t('account.no_reviews_title')"
      :description="$t('account.reviews_empty_description')"
      :cta-label="$t('account.browse_products')"
      @cta="$router.push('/shop')"
    >
      <template #icon>
        <Star class="h-10 w-10 text-gray-300" />
      </template>
    </EmptyState>
  </div>
</template>
