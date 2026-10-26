<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler } from 'chart.js'
import { Line } from 'vue-chartjs'
import type { BusinessForecast } from '@/api/analytics'
import { useChartTheme } from '@/composables/useChartTheme'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)
const props = defineProps<{ data: BusinessForecast }>()
const { t } = useI18n()
const { palette } = useChartTheme()
const points = computed(() => [...props.data.history, ...props.data.forecast])
const chart = computed(() => {
  const history = props.data.history
  const anchor = history.reduce((last, point, index) => point.type === 'actual' && !point.partial && point.value !== null ? index : last, -1)
  return {
    labels: points.value.map(point => point.period),
    datasets: [
      { label: t('analytics.range_upper'), data: points.value.map(point => point.type === 'predicted' ? point.upper_bound ?? null : null), borderColor: 'transparent', backgroundColor: 'rgba(37, 99, 235, .10)', pointRadius: 0, fill: '+1' },
      { label: t('analytics.range_lower'), data: points.value.map(point => point.type === 'predicted' ? point.lower_bound ?? null : null), borderColor: 'transparent', backgroundColor: 'transparent', pointRadius: 0 },
      { label: t('analytics.actual'), data: points.value.map(point => point.type === 'actual' ? point.value : null), borderColor: '#2563eb', backgroundColor: '#2563eb', borderWidth: 2, pointRadius: 3, tension: .15 },
      { label: t('analytics.current'), data: points.value.map(point => point.type === 'current' ? point.value : null), borderColor: '#d97706', backgroundColor: '#d97706', pointRadius: 5, showLine: false },
      { label: t('analytics.predicted'), data: points.value.map((point, index) => point.type === 'predicted' || index === anchor ? point.value : null), borderColor: '#8b5cf6', backgroundColor: '#8b5cf6', borderDash: [6, 5], borderWidth: 2, pointRadius: 3, spanGaps: true, tension: .15 }
    ]
  }
})
const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index' as const, intersect: false },
  plugins: { legend: { labels: { color: palette.value.text, filter: (item: { datasetIndex?: number }) => (item.datasetIndex ?? 0) >= 2 } } },
  scales: { x: { grid: { display: false }, ticks: { color: palette.value.text, maxTicksLimit: 10 } }, y: { grid: { color: palette.value.grid }, ticks: { color: palette.value.text } } }
}))
</script>

<template>
  <div class="h-80 min-w-0"><Line :data="chart" :options="options" :aria-label="t('analytics.chart_title')" role="img" /></div>
</template>
