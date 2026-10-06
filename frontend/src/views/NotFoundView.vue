<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <main class="container-app mx-auto flex min-h-[60vh] max-w-xl flex-col items-center justify-center px-6 text-center">
    <p class="text-7xl font-extrabold text-primary">404</p>
    <h1 class="mt-5 text-2xl font-bold text-ink dark:text-ink">Page not found</h1>
    <p class="mt-2 text-sm text-gray-500 dark:text-muted">The page you requested does not exist or may have moved.</p>
    <RouterLink to="/" class="btn-primary mt-6">Return home</RouterLink>
  </main>
</template>
