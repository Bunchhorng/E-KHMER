<script setup lang="ts">
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import ContentLayout from '@/layouts/ContentLayout.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col bg-canvas">
    <header class="border-b border-border-gray bg-white dark:bg-canvas">
      <ContentLayout class="flex items-center justify-between py-3">
        <RouterLink to="/" class="text-xl font-extrabold tracking-tight text-primary">
          E<span class="text-ink dark:text-ink">-KHMER</span>
        </RouterLink>
        <div class="flex items-center gap-2">
          <LanguageSwitcher />
          <ThemeToggle />
        </div>
      </ContentLayout>
    </header>
    <ContentLayout as="main" class="flex flex-1 items-center justify-center py-10">
      <router-view />
    </ContentLayout>
    <footer class="border-t border-border-gray py-4 text-center text-xs text-gray-400">
      <ContentLayout>{{ $t('footer.copyright') }}</ContentLayout>
    </footer>
  </div>
</template>
