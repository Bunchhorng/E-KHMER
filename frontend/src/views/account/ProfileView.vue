<script setup lang="ts">
import { RouterLink } from 'vue-router'
import {
  BadgeCheck,
  CheckCircle2,
  ChevronRight,
  KeyRound,
  Loader2,
  Mail,
  MapPin,
  Phone,
  Save,
  ShieldCheck,
  User,
  UserCog,
  Camera,
  XCircle
} from 'lucide-vue-next'
import { accountApi } from '@/api/account'
import { useAuthStore } from '@/stores/auth'
import { formatDate } from '@/utils/format'
import { computed, reactive, ref } from 'vue'

const authStore = useAuthStore()
const activeUser = computed(() => authStore.user)

const form = reactive({
  name: activeUser.value?.name ?? '',
  phone: activeUser.value?.phone ?? ''
})

const saved = ref(false)
const saving = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

const isVerified = computed(() => Boolean(activeUser.value?.email_verified))
const isAdmin = computed(() => ['admin', 'super_admin'].includes(activeUser.value?.role ?? ''))

const emailDisplay = computed(() => activeUser.value?.email ?? '—')
const phoneDisplay = computed(() => activeUser.value?.phone || '—')

// Avatar upload state
const avatarInput = ref<HTMLInputElement | null>(null)
const avatarPreview = ref<string | null>(null)
const avatarLoading = ref(false)
const avatarSaved = ref(false)
const avatarError = ref(false)
let avatarTimer: ReturnType<typeof setTimeout> | null = null

const avatarUrl = computed(() => activeUser.value?.avatar || null)

function triggerAvatarPicker() {
  avatarInput.value?.click()
}

function onAvatarSelected(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    avatarError.value = true
    if (avatarTimer) clearTimeout(avatarTimer)
    avatarTimer = setTimeout(() => (avatarError.value = false), 3000)
    return
  }
  avatarPreview.value = URL.createObjectURL(file)
  avatarSaved.value = false
  avatarError.value = false
  uploadAvatar(file)
  input.value = ''
}

async function uploadAvatar(file: File) {
  avatarLoading.value = true
  try {
    const res = await accountApi.uploadAvatar(file)
    const updatedUser = res.data
    authStore.user = { ...authStore.user!, avatar: updatedUser.avatar }
    localStorage.setItem('ekhmer_user', JSON.stringify(authStore.user))
    avatarSaved.value = true
    if (avatarTimer) clearTimeout(avatarTimer)
    avatarTimer = setTimeout(() => (avatarSaved.value = false), 3000)
  } catch {
    avatarError.value = true
    if (avatarTimer) clearTimeout(avatarTimer)
    avatarTimer = setTimeout(() => (avatarError.value = false), 3000)
  } finally {
    avatarLoading.value = false
  }
}

async function saveProfile() {
  if (saving.value) return
  saving.value = true
  try {
    const res = await accountApi.updateProfile({
      name: form.name.trim(),
      phone: form.phone.trim() || undefined
    })
    if (res.data) {
      const updated = res.data
      authStore.user = {
        ...authStore.user!,
        name: updated.name,
        phone: updated.phone ?? undefined
      }
      localStorage.setItem('ekhmer_user', JSON.stringify(authStore.user))
    }
    saved.value = true
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => { saved.value = false }, 3000)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Hero banner -->
    <div class="hero-gradient relative overflow-hidden rounded-2xl p-6 text-white sm:p-8">
      <div class="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-white/10"></div>
      <div class="pointer-events-none absolute -bottom-20 -left-8 h-44 w-44 rounded-full bg-white/10"></div>
      <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center">
        <!-- Avatar -->
        <div class="group relative shrink-0 self-center sm:self-start">
          <div
            class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-white/30 to-white/10 text-2xl font-extrabold text-white shadow-lg ring-2 ring-white/40 sm:h-24 sm:w-24 overflow-hidden"
          >
            <img
              v-if="avatarUrl"
              :key="avatarUrl"
              :src="avatarPreview ?? avatarUrl"
              :alt="activeUser?.name ?? ''"
              class="h-full w-full object-cover"
            />
            <span v-else class="select-none"><User class="h-8 w-8" /></span>
          </div>

          <!-- Upload overlay -->
          <button
            type="button"
            class="absolute inset-0 flex items-center justify-center rounded-2xl bg-black/50 opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
            :title="$t('account.change_photo')"
            @click="triggerAvatarPicker"
          >
            <span v-if="avatarLoading" class="flex items-center justify-center">
              <Loader2 class="h-6 w-6 animate-spin text-white" />
            </span>
            <Camera v-else class="h-6 w-6 text-white" />
          </button>

          <input
            ref="avatarInput"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            class="hidden"
            @change="onAvatarSelected"
          />
        </div>

        <!-- Name / email / status -->
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-2xl font-extrabold tracking-tight">{{ activeUser?.name ?? $t('nav.profile') }}</h2>
            <span
              class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
              :class="isVerified ? 'bg-emerald-400/20 text-emerald-200' : 'bg-amber-400/20 text-amber-200'"
            >
              <BadgeCheck class="h-3.5 w-3.5" />
              {{ isVerified ? $t('account.verified') : $t('account.not_verified') }}
            </span>
          </div>
          <p class="mt-1.5 flex items-center gap-2 text-sm text-white/80">
            <Mail class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ emailDisplay }}</span>
          </p>
          <p class="mt-3 flex items-center gap-2 text-xs text-white/70">
            <ShieldCheck class="h-4 w-4 shrink-0" />
            {{ $t('account.member_since', { date: formatDate(activeUser?.created_at ?? new Date().toISOString()) }) }}
          </p>
        </div>

        <!-- Role badge -->
        <div class="flex flex-col items-start gap-2 sm:items-end">
          <span class="chip !bg-white/15 !text-white backdrop-blur-sm">
            {{ isAdmin ? $t('account.admin_role') : $t('account.customer_role') }}
          </span>
          <span class="text-xs font-medium text-white/60">{{ activeUser?.email }}</span>
        </div>
      </div>

      <!-- Avatar status messages (inside hero) -->
      <Transition name="fade-in">
        <p
          v-if="avatarSaved"
          class="relative mt-4 flex items-center gap-2 rounded-lg bg-emerald-400/20 px-3 py-2 text-sm font-medium text-emerald-200"
        >
          <CheckCircle2 class="h-4 w-4 shrink-0" />
          {{ $t('account.avatar_updated') }}
        </p>
      </Transition>
      <Transition name="fade-in">
        <p
          v-if="avatarError"
          class="relative mt-4 flex items-center gap-2 rounded-lg bg-red-400/20 px-3 py-2 text-sm font-medium text-red-200"
        >
          <XCircle class="h-4 w-4 shrink-0" />
          {{ $t('account.avatar_update_failed') }}
        </p>
      </Transition>
    </div>

    <!-- Main grid -->
    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Personal information form -->
      <div class="card p-6 lg:col-span-2">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-bold text-ink">{{ $t('account.personal_information') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ $t('account.personal_information_desc') }}</p>
          </div>
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
            <UserCog class="h-5 w-5" />
          </span>
        </div>

        <form class="mt-6 grid gap-5 sm:grid-cols-2" @submit.prevent="saveProfile">
          <div>
            <label class="label" for="profile-name">
              <span class="flex items-center gap-1.5">
                <User class="h-3.5 w-3.5" />
                {{ $t('checkout.full_name') }}
              </span>
            </label>
            <input id="profile-name" v-model="form.name" type="text" class="input" />
          </div>
          <div>
            <label class="label" for="profile-phone">
              <span class="flex items-center gap-1.5">
                <Phone class="h-3.5 w-3.5" />
                {{ $t('checkout.phone') }}
              </span>
            </label>
            <input id="profile-phone" v-model="form.phone" type="tel" class="input" />
          </div>

          <div class="sm:col-span-2">
            <label class="label" for="profile-email">
              <span class="flex items-center gap-1.5">
                <Mail class="h-3.5 w-3.5" />
                {{ $t('auth.email') }}
              </span>
            </label>
            <input
              id="profile-email"
              :value="activeUser?.email"
              type="email"
              class="input cursor-not-allowed bg-gray-100 text-gray-500 opacity-80 dark:bg-surface-hover dark:text-muted"
              disabled
            />
            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
              {{ isVerified ? $t('account.verified') : $t('account.not_verified') }}
            </p>
          </div>

          <div class="sm:col-span-2 flex items-center justify-end gap-3">
            <Transition name="fade-in">
              <p v-if="saved" class="flex items-center gap-2 text-sm font-medium text-emerald-600 dark:text-success">
                <CheckCircle2 class="h-4 w-4 shrink-0" />
                {{ $t('account.save_successful') }}
              </p>
            </Transition>
            <button type="submit" class="btn-primary inline-flex items-center gap-2" :disabled="saving">
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
              <Save v-else class="h-4 w-4" />
              {{ saving ? $t('common.saving') : $t('actions.save_changes') }}
            </button>
          </div>
        </form>
      </div>

      <!-- Account summary + quick links -->
      <div class="space-y-6">
        <div class="card overflow-hidden">
          <div class="border-b border-border-gray bg-canvas/60 px-6 py-4">
            <h3 class="text-base font-bold text-ink">{{ $t('account.account_summary') }}</h3>
          </div>
          <div class="divide-y divide-border-gray">
            <div class="flex items-center justify-between gap-3 px-6 py-3.5">
              <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <Mail class="h-4 w-4" />
                </span>
                <div>
                  <p class="text-xs text-gray-500 dark:text-muted">{{ $t('account.email_status') }}</p>
                  <p class="text-sm font-medium text-gray-600 dark:text-muted">{{ emailDisplay }}</p>
                </div>
              </div>
              <span
                class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                :class="isVerified
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                  : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'"
              >
                {{ isVerified ? $t('account.verified') : $t('account.not_verified') }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-3 px-6 py-3.5">
              <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-success/15 dark:text-success">
                  <Phone class="h-4 w-4" />
                </span>
                <div>
                  <p class="text-xs text-gray-500 dark:text-muted">{{ $t('account.phone_number') }}</p>
                  <p class="text-sm font-medium text-ink">{{ phoneDisplay }}</p>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-between gap-3 px-6 py-3.5">
              <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                  <ShieldCheck class="h-4 w-4" />
                </span>
                <div>
                  <p class="text-xs text-gray-500 dark:text-muted">{{ $t('account.role_label') }}</p>
                  <p class="text-sm font-medium text-ink">
                    {{ isAdmin ? $t('account.admin_role') : $t('account.customer_role') }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick links -->
        <div class="card overflow-hidden">
          <div class="border-b border-border-gray bg-canvas/60 px-6 py-4">
            <h3 class="text-base font-bold text-ink">{{ $t('account.quick_links') }}</h3>
          </div>
          <div class="divide-y divide-border-gray">
            <RouterLink
              to="/account/password"
              class="group flex items-center gap-3 px-6 py-3.5 transition-colors hover:bg-canvas"
            >
              <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 group-hover:text-primary dark:bg-surface-hover dark:text-muted dark:group-hover:text-primary">
                <KeyRound class="h-4 w-4" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-ink">{{ $t('nav.change_password') }}</p>
                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-muted">{{ $t('account.change_password_hint') }}</p>
              </div>
              <ChevronRight class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-hover:translate-x-0.5" />
            </RouterLink>
            <RouterLink
              to="/account/addresses"
              class="group flex items-center gap-3 px-6 py-3.5 transition-colors hover:bg-canvas"
            >
              <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 group-hover:text-primary dark:bg-surface-hover dark:text-muted dark:group-hover:text-primary">
                <MapPin class="h-4 w-4" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-ink">{{ $t('nav.addresses') }}</p>
                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-muted">{{ $t('account.manage_addresses_hint') }}</p>
              </div>
              <ChevronRight class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-hover:translate-x-0.5" />
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
