<script setup lang="ts">
import { computed } from 'vue'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler } from 'chart.js'
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js'
import { Line } from 'vue-chartjs'
import { useI18n } from 'vue-i18n'
import { useChartTheme } from '@/composables/useChartTheme'
import { formatCompactNumber, formatPrice } from '@/utils/format'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)
ChartJS.defaults.font.family = 'Inter, sans-serif'

const props = defineProps<{
  data: { label: string; revenue: number }[]
}>()

const { palette } = useChartTheme()
const { t } = useI18n()
const primary = '#2563EB'

const chartData = computed<ChartData<'line'>>(() => ({
  labels: props.data.map((p) => p.label),
  datasets: [
    {
      label: t('admin.chart.revenue'),
      data: props.data.map((p) => p.revenue),
      borderColor: primary,
      backgroundColor: 'rgba(37, 99, 235, 0.08)',
      pointBackgroundColor: primary,
      tension: 0.35,
      fill: true,
      borderWidth: 2,
      pointRadius: props.data.length > 60 ? 0 : 3,
      pointHoverRadius: 5
    }
  ]
}))

const chartOptions = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (context: TooltipItem<'line'>) => formatPrice(context.parsed.y ?? 0)
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
      ticks: {
        color: palette.value.text,
        callback: (value: number | string) => `$${formatCompactNumber(Number(value))}`
      }
    }
  }
}))
</script>

<template>
  <div class="h-72" style="position: relative;">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
