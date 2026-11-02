<script setup lang="ts">
import { computed } from 'vue'
import { prepareProductDescription } from '@/utils/productDescription'
import '@/styles/productDescription.css'

const props = defineProps<{ content?: string | null }>()
const description = computed(() => prepareProductDescription(props.content))
</script>

<template>
  <div v-if="description.text" class="product-description" data-product-description v-html="description.html"></div>
</template>
