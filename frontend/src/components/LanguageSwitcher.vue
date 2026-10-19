<script setup lang="ts">
import { computed } from 'vue'
import { useLocaleStore } from '@/stores/locale'
import khmerFlag from '@/assets/flags/km.svg'
import englishFlag from '@/assets/flags/gb.svg'

const localeStore = useLocaleStore()

const targetFlag = computed(() => (localeStore.currentLocale === 'km' ? englishFlag : khmerFlag))
const toggleText = computed(() => (localeStore.currentLocale === 'km'
  ? 'ភាសាខ្មែរ — ប្ដូរទៅ English'
  : 'English — switch to ភាសាខ្មែរ'))

function toggle() {
  localeStore.toggle()
}
</script>

<template>
  <button
    type="button"
    :aria-label="toggleText"
    :title="toggleText"
    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 dark:hover:bg-surface-hover dark:focus-visible:ring-offset-surface"
    @click="toggle"
  >
    <img :src="targetFlag" alt="" aria-hidden="true" class="h-5 w-8 rounded-sm object-cover shadow-sm" width="32" height="20" />
  </button>
</template>
