<script setup lang="ts">
import { computed } from 'vue'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import { useChartTheme } from '@/composables/useChartTheme'
import { formatPrice } from '@/utils/format'

ChartJS.register(ArcElement, Tooltip, Legend)
ChartJS.defaults.font.family = 'Inter, sans-serif'

const props = defineProps<{
  data: { category: string; sales: number }[]
}>()

const { palette } = useChartTheme()

const paletteColors = ['#2563EB', '#10B981', '#FBBF24', '#8B5CF6', '#EC4899', '#64748B']

const chartData = computed<ChartData<'doughnut'>>(() => ({
  labels: props.data.map((c) => c.category),
  datasets: [
    {
      data: props.data.map((c) => c.sales),
      backgroundColor: paletteColors,
      borderColor: palette.value.border,
      borderWidth: 2
    }
  ]
}))

const chartOptions = computed<ChartOptions<'doughnut'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '62%',
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        boxWidth: 10,
        usePointStyle: true,
        pointStyle: 'circle',
        padding: 12,
        color: palette.value.text
      }
    },
    tooltip: {
      callbacks: {
        label: (context: TooltipItem<'doughnut'>) => `${context.label || ''}: ${formatPrice(context.parsed)}`
      }
    }
  }
}))
</script>

<template>
  <div class="h-72" style="position: relative;">
    <Doughnut :data="chartData" :options="chartOptions" />
  </div>
</template>
