<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch, onMounted } from "vue";
import { useRouter } from "vue-router";
import type { RouteLocationRaw } from "vue-router";
import { Heart, LogIn, LogOut, Menu, Search, ShoppingBag, User, X } from "lucide-vue-next";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import ThemeToggle from "@/components/ThemeToggle.vue";
import { categoriesApi } from "@/api";

const navCategories = ref<{ id: number; name: string; slug: string }[]>([]);

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
const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);

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

onBeforeUnmount(() => document.removeEventListener("click", onClickOutside));

async function signOut(): Promise<void> {
  closeUserMenu();
  await auth.logout();
  router.push("/");
}
</script>

<template>
  <header>
    <div
      class="bg-primary-dark px-4 py-1.5 text-center text-xs font-medium text-white"
    >
      {{ $t('header.announcement') }}
    </div>
    <div class="sticky-header">
      <div class="container-app flex h-16 items-center gap-2 sm:gap-4 lg:h-20">
        <button
          type="button"
          class="btn-icon lg:hidden"
          :aria-label="$t('header.open_menu')"
          @click="openMobile"
        >
          <Menu :size="22" />
        </button>

        <RouterLink
          to="/"
          class="shrink-0 whitespace-nowrap text-xl font-extrabold tracking-tight text-primary sm:text-2xl"
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
          <LanguageSwitcher compact />
          <ThemeToggle />

          <RouterLink
            v-if="!auth.isAuthenticated"
            to="/auth/login"
            class="btn-icon"
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
            class="btn-icon relative"
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

          <div ref="userMenuRef" class="relative">
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
    </div>
  </header>

  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="mobileOpen" class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-black/40" @click="closeMobile"></div>
        <aside
          class="panel fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl dark:bg-surface"
        >
          <div
            class="flex items-center justify-between border-b border-border-gray px-4 py-4 dark:border-border-gray"
          >
            <RouterLink
              to="/"
              class="text-xl font-extrabold tracking-tight text-primary"
              @click="closeMobile"
            >
              E-<span class="text-ink dark:text-ink">KHMER</span>
            </RouterLink>
            <div class="flex items-center gap-1">
              <ThemeToggle />
              <button
                type="button"
                class="btn-icon"
                :aria-label="$t('header.close_menu')"
                @click="closeMobile"
              >
                <X :size="20" />
              </button>
            </div>
          </div>
          <p
            class="px-5 pt-4 text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500"
          >
            {{ $t('nav.shop_by_category') }}
          </p>
          <nav class="flex-1 space-y-1 overflow-y-auto p-4">
            <RouterLink
              v-for="item in navCategories"
              :key="item.id"
              :to="{ name: 'shop', query: { category: item.slug } }"
              class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-canvas hover:text-primary dark:text-gray-200 dark:hover:bg-surface-hover dark:hover:text-primary"
              @click="closeMobile"
            >
              {{ $te(`categories.${item.slug.replace(/-/g, '_')}`) ? $t(`categories.${item.slug.replace(/-/g, '_')}`) : item.name }}
            </RouterLink>
          </nav>
          <div class="space-y-1 border-t border-border-gray p-4 dark:border-border-gray">
            <RouterLink
              to="/cart"
              class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-canvas hover:text-primary dark:text-gray-200 dark:hover:bg-surface-hover dark:hover:text-primary"
              @click="closeMobile"
            >
              {{ $t('nav.cart') }}
            </RouterLink>
            <RouterLink
              to="/account"
              class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-canvas hover:text-primary dark:text-gray-200 dark:hover:bg-surface-hover dark:hover:text-primary"
              @click="closeMobile"
            >
              {{ $t('nav.dashboard') }}
            </RouterLink>
            <RouterLink
              to="/account/wishlist"
              class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-canvas hover:text-primary dark:text-gray-200 dark:hover:bg-surface-hover dark:hover:text-primary"
              @click="closeMobile"
            >
              {{ $t('nav.wishlist') }}
            </RouterLink>
          </div>
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
</style>
