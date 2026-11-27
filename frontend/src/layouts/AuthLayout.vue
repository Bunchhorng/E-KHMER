<script setup lang="ts">
import { Check, ShieldCheck, ShoppingBag, Truck } from 'lucide-vue-next'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import ContentLayout from '@/layouts/ContentLayout.vue'
</script>

<template>
  <div class="flex min-h-[100dvh] flex-col bg-canvas text-ink">
    <header class="border-b border-border-gray bg-surface pt-[env(safe-area-inset-top)]">
      <ContentLayout class="flex min-h-14 items-center justify-between py-2.5 sm:min-h-16">
        <RouterLink
          to="/"
          class="inline-flex min-h-11 items-center rounded-none text-xl font-extrabold tracking-tight text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus sm:text-2xl"
        >
          E-<span class="text-ink">KHMER</span>
        </RouterLink>
        <div class="flex items-center gap-1 sm:gap-2">
          <LanguageSwitcher />
          <ThemeToggle />
        </div>
      </ContentLayout>
    </header>

    <main class="flex flex-1 items-center py-6 sm:py-10">
      <div class="container-app">
        <div class="mx-auto grid max-w-5xl overflow-hidden border border-border-gray bg-surface shadow-card lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
          <aside
            data-testid="auth-brand-panel"
            class="hero-gradient relative hidden flex-col justify-between border-r border-border-gray p-8 text-white lg:flex xl:p-10"
          >
            <div class="space-y-4">
              <p class="inline-block border border-white/25 bg-white/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-widest text-blue-100">
                {{ $t('auth.brand_eyebrow') }}
              </p>
              <h2 class="text-2xl font-extrabold leading-tight tracking-tight text-white xl:text-3xl">
                {{ $t('auth.brand_title') }}
              </h2>
              <p class="text-sm leading-relaxed text-blue-100/90">
                {{ $t('auth.brand_subtitle') }}
              </p>
            </div>

            <ul class="mt-8 space-y-3 border-t border-white/15 pt-6 text-sm text-blue-50">
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center border border-white/30 bg-white/10">
                  <ShoppingBag :size="12" aria-hidden="true" />
                </span>
                <span>{{ $t('auth.brand_point_1') }}</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center border border-white/30 bg-white/10">
                  <ShieldCheck :size="12" aria-hidden="true" />
                </span>
                <span>{{ $t('auth.brand_point_2') }}</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center border border-white/30 bg-white/10">
                  <Truck :size="12" aria-hidden="true" />
                </span>
                <span>{{ $t('auth.brand_point_3') }}</span>
              </li>
            </ul>

            <div class="mt-8 flex items-center gap-2 text-xs text-blue-100/80">
              <Check :size="14" class="text-emerald-300" aria-hidden="true" />
              <span>Bakong / KHQR · Cash on Delivery</span>
            </div>
          </aside>

          <div class="flex items-center justify-center bg-surface p-5 sm:p-8 lg:p-10">
            <div class="w-full max-w-md">
              <router-view />
            </div>
          </div>
        </div>
      </div>
    </main>

    <footer class="border-t border-border-gray bg-surface py-4 pb-[max(1rem,env(safe-area-inset-bottom))] text-center text-xs text-muted">
      <ContentLayout>{{ $t('footer.copyright') }}</ContentLayout>
    </footer>
  </div>
</template>
