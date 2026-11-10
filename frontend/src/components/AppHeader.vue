<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch, onMounted } from "vue";
import { useRouter } from "vue-router";
import type { RouteLocationRaw } from "vue-router";
import { ChevronDown, ChevronRight, Grid2X2, Headphones, Heart, HelpCircle, Home, LogIn, LogOut, Menu, Search, ShoppingBag, User, X } from "lucide-vue-next";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import ThemeToggle from "@/components/ThemeToggle.vue";
import { categoriesApi } from "@/api";
import { getCategoryIcon } from '@/utils/categoryIcon'

type NavigationCategory = { id: number; name: string; slug: string; icon?: string | null; children?: NavigationCategory[] }
const navCategories = ref<NavigationCategory[]>([]);

onMounted(async () => {
  try {
    const { data } = await categoriesApi.getTree();
    navCategories.value = data.data;
  } catch {
    navCategories.value = [];
  }
});
import { useAuthStore } from "@/stores/auth";
import { useCartStore } from "@/stores/cart";
import { useUiStore } from "@/stores/ui";
import { useWishlistStore } from "@/stores/wishlist";

const router = useRouter();
const auth = useAuthStore();
const cartStore = useCartStore();
const uiStore = useUiStore();
const wishlistStore = useWishlistStore();

const searchTerm = ref("");
const mobileOpen = ref(false);
const expandedCategoryId = ref<number | null>(null)
const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);
const menuTriggerRef = ref<HTMLButtonElement | null>(null)
const drawerRef = ref<HTMLElement | null>(null)
const drawerCloseRef = ref<HTMLButtonElement | null>(null)
let previousBodyOverflow = ''

const accountLinks = computed<Array<{ labelKey: string; to: RouteLocationRaw }>>(() => [
  ...(auth.isAdmin ? [{ labelKey: 'marketplace.platform_admin', to: { name: 'admin-dashboard' } }] : []),
  ...(auth.isSeller ? [{ labelKey: 'marketplace.seller_center', to: { name: 'seller-dashboard' } }] : []),
  { labelKey: "nav.dashboard", to: { name: "account-dashboard" } },
  { labelKey: "nav.my_orders", to: { name: "account-orders" } },
  { labelKey: "nav.wishlist", to: { name: "account-wishlist" } },
  { labelKey: "nav.addresses", to: { name: "account-addresses" } },
  { labelKey: "nav.profile", to: { name: "account-profile" } },
  { labelKey: 'marketplace.become_seller', to: { name: 'seller-application' } },
]);

function submitSearch(): void {
  const q = searchTerm.value.trim();
  router.push({ name: "shop", query: q ? { q } : {} });
}

function openMobile(): void {
  mobileOpen.value = true;
}

function closeMobile(): void {
  mobileOpen.value = false;
  nextTick(() => menuTriggerRef.value?.focus())
}

function toggleCategoryGroup(id: number): void {
  expandedCategoryId.value = expandedCategoryId.value === id ? null : id
}

function toggleUserMenu(): void {
  userMenuOpen.value = !userMenuOpen.value;
}

function closeUserMenu(): void {
  userMenuOpen.value = false;
}

function onClickOutside(event: MouseEvent): void {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target as Node)) {
    closeUserMenu();
  }
}

watch(userMenuOpen, (open) => {
  if (open) document.addEventListener("click", onClickOutside);
  else document.removeEventListener("click", onClickOutside);
});

watch(mobileOpen, (open) => {
  if (open) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    nextTick(() => drawerCloseRef.value?.focus())
  } else {
    document.body.style.overflow = previousBodyOverflow
  }
})

function onDrawerKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeMobile()
    return
  }
  if (event.key !== 'Tab' || !drawerRef.value) return
  const focusable = Array.from(drawerRef.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
  if (!focusable.length) return
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault(); last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault(); first.focus()
  }
}

watch(mobileOpen, (open) => {
  if (open) window.addEventListener('keydown', onDrawerKeydown)
  else window.removeEventListener('keydown', onDrawerKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener("click", onClickOutside)
  window.removeEventListener('keydown', onDrawerKeydown)
  if (mobileOpen.value) document.body.style.overflow = previousBodyOverflow
});

async function signOut(): Promise<void> {
  closeUserMenu();
  if (mobileOpen.value) closeMobile()
  await auth.logout();
  router.push("/");
}
</script>

<template>
  <header data-testid="storefront-main-header">
    <div class="sticky top-0 z-40 border-b border-border-gray bg-surface/95 backdrop-blur">
      <div class="container-app flex h-14 items-center gap-2 sm:gap-4 lg:h-20">
        <button
          ref="menuTriggerRef"
          type="button"
          class="btn-icon lg:hidden"
          :aria-label="$t('header.open_menu')"
          @click="openMobile"
        >
          <Menu :size="22" />
        </button>

        <RouterLink
          to="/"
          class="shrink-0 whitespace-nowrap text-lg font-extrabold tracking-tight text-primary sm:text-2xl"
        >
          E-<span class="text-ink dark:text-ink">KHMER</span>
        </RouterLink>

        <form
          class="relative hidden flex-1 max-w-lg md:block"
          role="search"
          @submit.prevent="submitSearch"
        >
          <Search
            class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-gray-400"
            :size="18"
          />
          <input
            v-model="searchTerm"
            type="search"
            class="input py-2.5 pl-10 pr-4"
            :placeholder="$t('header.search_placeholder')"
          />
        </form>

        <div class="ml-auto flex items-center gap-1">
          <div class="hidden items-center gap-1 md:flex">
            <LanguageSwitcher />
            <ThemeToggle />
          </div>

          <RouterLink
            v-if="!auth.isAuthenticated"
            to="/auth/login"
            class="btn-icon hidden md:inline-flex"
            :aria-label="$t('nav.sign_in')"
            :title="$t('nav.sign_in')"
          >
            <LogIn :size="20" />
          </RouterLink>

          <RouterLink
            to="/account/wishlist"
            class="btn-icon relative hidden sm:inline-flex"
            :aria-label="$t('nav.wishlist')"
          >
            <Heart :size="20" />
            <span
              v-if="wishlistStore.count > 0"
              class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white"
            >
              {{ wishlistStore.count }}
            </span>
          </RouterLink>

          <button
            type="button"
            class="btn-icon relative hidden md:inline-flex"
            :aria-label="$t('header.open_cart')"
            @click="uiStore.openCartDrawer()"
          >
            <ShoppingBag :size="20" />
            <span
              v-if="cartStore.totalItemCount > 0"
              class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white"
            >
              {{ cartStore.totalItemCount }}
            </span>
          </button>

          <div ref="userMenuRef" class="relative hidden md:block">
            <button
              v-if="auth.isAuthenticated"
              type="button"
              class="btn-icon"
              :aria-expanded="userMenuOpen"
              :aria-label="$t('header.account_menu')"
              @click="toggleUserMenu"
            >
              <User :size="20" />
            </button>
            <div
              v-if="auth.isAuthenticated && userMenuOpen"
              class="card absolute right-0 top-11 z-50 w-56 overflow-hidden py-2 shadow-popover dark:border-border-gray dark:bg-surface"
            >
              <div class="border-b border-border-gray px-4 py-3 dark:border-border-gray">
                <p class="text-sm font-semibold text-ink dark:text-ink">
                  {{ auth.user?.name }}
                </p>
                <p class="truncate text-xs text-gray-500 dark:text-muted">
                  {{ auth.user?.email }}
                </p>
              </div>
              <div class="py-1">
                <RouterLink
                  v-for="link in accountLinks"
                  :key="link.labelKey"
                  :to="link.to"
                  class="block px-4 py-2 text-sm text-gray-600 transition-colors hover:bg-canvas hover:text-primary dark:text-muted dark:hover:bg-surface-hover dark:hover:text-primary"
                  @click="closeUserMenu"
                >
                  {{ $t(link.labelKey) }}
                </RouterLink>
              </div>
              <div class="border-t border-border-gray px-4 py-2 dark:border-border-gray">
                <button
                  type="button"
                  class="flex items-center gap-2 text-sm font-medium text-red-500 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                  @click="signOut"
                >
                  <LogOut :size="15" />
                  {{ $t('nav.sign_out') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="border-t border-border-gray md:hidden">
        <form class="container-app relative py-2" role="search" @submit.prevent="submitSearch">
          <Search class="pointer-events-none absolute left-7 top-1/2 -translate-y-1/2 text-gray-400" :size="18" />
          <input v-model="searchTerm" type="search" class="input w-full pl-10 pr-4" :placeholder="$t('header.search_placeholder')" />
        </form>
      </div>
    </div>
  </header>

  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="mobileOpen" class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-black/40" @click="closeMobile"></div>
        <aside
          ref="drawerRef"
          role="dialog"
          aria-modal="true"
          :aria-label="$t('header.open_menu')"
          class="panel fixed inset-y-0 left-0 z-50 flex h-[100dvh] w-[min(22rem,88vw)] max-w-[88vw] flex-col overflow-hidden !rounded-none !border-y-0 !border-l-0 !p-0 bg-surface pb-[env(safe-area-inset-bottom)] shadow-xl"
        >
          <div
            class="flex min-h-16 items-center justify-between gap-2 border-b border-border-gray px-4 pt-[max(1rem,env(safe-area-inset-top))] dark:border-border-gray"
          >
            <RouterLink
              to="/"
              class="min-w-0 shrink whitespace-nowrap text-lg font-extrabold tracking-tight text-primary"
              @click="closeMobile"
            >
              E-<span class="text-ink dark:text-ink">KHMER</span>
            </RouterLink>
            <div class="flex shrink-0 items-center gap-0.5">
              <LanguageSwitcher />
              <ThemeToggle />
              <button
                ref="drawerCloseRef"
                type="button"
                class="btn-icon"
                :aria-label="$t('header.close_menu')"
                @click="closeMobile"
              >
                <X :size="20" />
              </button>
            </div>
          </div>
          <nav class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5">
            <section aria-labelledby="drawer-browse-heading">
              <p id="drawer-browse-heading" class="px-2 text-xs font-bold uppercase tracking-widest text-muted">{{ $t('home.browse') }}</p>
              <RouterLink to="/shop" class="drawer-link mt-2" @click="closeMobile">
                <Grid2X2 :size="18" />
                <span>{{ $t('nav.all_products') }}</span>
                <ChevronRight :size="16" class="ml-auto text-muted" />
              </RouterLink>
              <div class="mt-1 space-y-1">
                <div v-for="item in navCategories" :key="item.id">
                  <div class="flex items-center rounded-xl transition-colors hover:bg-canvas dark:hover:bg-surface-hover">
                    <RouterLink
                      :to="{ name: 'shop', query: { category: item.slug } }"
                      class="drawer-link min-w-0 flex-1 !bg-transparent"
                      @click="closeMobile"
                    >
                      <component :is="getCategoryIcon(item)" :size="18" />
                      <span class="truncate">{{ $te(`categories.${item.slug.replace(/-/g, '_')}`) ? $t(`categories.${item.slug.replace(/-/g, '_')}`) : item.name }}</span>
                    </RouterLink>
                    <button
                      v-if="item.children?.length"
                      type="button"
                      class="btn-icon mr-1 shrink-0"
                      :aria-expanded="expandedCategoryId === item.id"
                      :aria-label="`${$t('actions.view_all')}: ${item.name}`"
                      @click="toggleCategoryGroup(item.id)"
                    >
                      <ChevronDown :size="18" :class="expandedCategoryId === item.id ? 'rotate-180' : ''" class="transition-transform" />
                    </button>
                  </div>
                  <div v-if="item.children?.length && expandedCategoryId === item.id" class="ml-5 border-l border-border-gray pl-2">
                    <RouterLink
                      v-for="child in item.children"
                      :key="child.id"
                      :to="{ name: 'shop', query: { category: child.slug } }"
                      class="drawer-link !min-h-10 !py-2 text-sm"
                      @click="closeMobile"
                    >
                      <component :is="getCategoryIcon(child, item)" :size="15" />
                      <span class="truncate">{{ $te(`categories.${child.slug.replace(/-/g, '_')}`) ? $t(`categories.${child.slug.replace(/-/g, '_')}`) : child.name }}</span>
                    </RouterLink>
                  </div>
                </div>
              </div>
            </section>

            <section class="mt-6 border-t border-border-gray pt-5" aria-labelledby="drawer-help-heading">
              <p id="drawer-help-heading" class="px-2 text-xs font-bold uppercase tracking-widest text-muted">{{ $t('nav.customer_services') }}</p>
              <div class="mt-2 space-y-1">
                <RouterLink to="/support" class="drawer-link" @click="closeMobile"><Headphones :size="18" />{{ $t('footer.contact') }}</RouterLink>
                <RouterLink to="/help/faq" class="drawer-link" @click="closeMobile"><HelpCircle :size="18" />{{ $t('footer.faq') }}</RouterLink>
              </div>
            </section>

            <section class="mt-6 border-t border-border-gray pt-5" aria-labelledby="drawer-account-heading">
              <p id="drawer-account-heading" class="px-2 text-xs font-bold uppercase tracking-widest text-muted">{{ $t('nav.account') }}</p>
              <div class="mt-2 space-y-1">
                <RouterLink v-if="auth.isAuthenticated" to="/account/dashboard" class="drawer-link" @click="closeMobile"><User :size="18" />{{ $t('nav.dashboard') }}</RouterLink>
                <RouterLink v-if="auth.isAuthenticated" to="/account/wishlist" class="drawer-link" @click="closeMobile"><Heart :size="18" />{{ $t('nav.wishlist') }}</RouterLink>
                <RouterLink v-if="!auth.isAuthenticated" to="/auth/login" class="drawer-link" @click="closeMobile"><LogIn :size="18" />{{ $t('nav.sign_in') }}</RouterLink>
                <button v-if="auth.isAuthenticated" type="button" class="drawer-link w-full text-danger" @click="signOut"><LogOut :size="18" />{{ $t('nav.sign_out') }}</button>
              </div>
            </section>
          </nav>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-enter-active .panel,
.drawer-leave-active .panel {
  transition: transform 0.25s ease;
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from .panel,
.drawer-leave-to .panel {
  transform: translateX(-100%);
}
.drawer-link {
  display: flex;
  min-height: 2.75rem;
  min-width: 0;
  align-items: center;
  gap: 0.75rem;
  border-radius: 0.75rem;
  padding: 0.625rem 0.75rem;
  color: rgb(var(--color-ink));
  font-size: 0.875rem;
  font-weight: 650;
  transition: background-color 0.15s ease, color 0.15s ease;
}
.drawer-link:hover { background: rgb(var(--color-canvas)); color: rgb(var(--color-primary)); }
.drawer-link:active { background: rgb(var(--color-surface-active)); }
html.dark .drawer-link:hover { background: rgb(var(--color-surface-hover)); }
</style>
