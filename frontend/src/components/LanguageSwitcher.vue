<script setup lang="ts">
import { computed } from 'vue'
import { Languages } from 'lucide-vue-next'
import { useLocaleStore } from '@/stores/locale'

const localeStore = useLocaleStore()
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })

const currentLabel = computed(() => (localeStore.currentLocale === 'km' ? 'ភាសាខ្មែរ' : 'English'))

const toggleText = computed(() => (localeStore.currentLocale === 'km' ? 'ភាសាខ្មែរ / English' : 'English / ភាសាខ្មែរ'))

function toggle() {
  localeStore.toggle()
}
</script>

<template>
  <button
    type="button"
    :aria-label="`Switch language, current: ${localeStore.currentLocale}`"
    :title="toggleText"
    class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-ink dark:text-muted dark:hover:bg-surface-hover dark:hover:text-ink"
    @click="toggle"
  >
    <Languages :size="16" />
    <span class="leading-tight" :class="{ 'hidden sm:inline': compact }">{{ currentLabel }}</span>
  </button>
</template>
