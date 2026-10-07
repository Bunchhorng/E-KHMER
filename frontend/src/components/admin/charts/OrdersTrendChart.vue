<script setup lang="ts">
import { computed } from 'vue'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js'
import { Bar } from 'vue-chartjs'
import { useI18n } from 'vue-i18n'
import { useChartTheme } from '@/composables/useChartTheme'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
ChartJS.defaults.font.family = 'Inter, sans-serif'

const props = defineProps<{
  data: { label: string; orders: number }[]
}>()

const { palette } = useChartTheme()
const { t } = useI18n()

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: props.data.map((p) => p.label),
  datasets: [
    {
      label: t('admin.chart.orders'),
      data: props.data.map((p) => p.orders),
      backgroundColor: 'rgba(37, 99, 235, 0.35)',
      borderColor: '#2563EB',
      borderRadius: 5,
      barPercentage: 0.75
    }
  ]
}))

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (context: TooltipItem<'bar'>) => `${context.parsed.y} ${t('admin.chart.orders').toLowerCase()}`
      }
    }
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: palette.value.text, maxTicksLimit: 8 }
    },
    y: {
      beginAtZero: true,
      border: { display: false },
      grid: { color: palette.value.grid },
      ticks: { color: palette.value.text, precision: 0 }
    }
  }
}))
</script>

<template>
  <div class="h-72" style="position: relative;">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
