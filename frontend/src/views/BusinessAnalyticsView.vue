<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ChartNoAxesCombined, CircleDollarSign, Info, Pencil, Plus, RefreshCw, Search, ShieldCheck, Trash2, TrendingUp, Wallet } from 'lucide-vue-next'
import axios from 'axios'
import { analyticsApi, type BusinessExpense, type BusinessForecast, type ExpensePayload, type ForecastFilters, type ForecastMetric } from '@/api/analytics'
import { adminApi } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice, formatCompactNumber } from '@/utils/format'
import DashboardPageHeader from '@/components/dashboard/DashboardPageHeader.vue'
import DashboardMetricCard from '@/components/dashboard/DashboardMetricCard.vue'
import DashboardSection from '@/components/dashboard/DashboardSection.vue'
import BusinessForecastChart from '@/components/dashboard/BusinessForecastChart.vue'
import BaseModal from '@/components/BaseModal.vue'
import BasePagination from '@/components/BasePagination.vue'

const route = useRoute()
const { t } = useI18n()
const isAdmin = route.name === 'admin-analytics'
const shopId = isAdmin ? null : Number(route.params.id)
const dateInput = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const today = dateInput(new Date())
const first = new Date()
first.setDate(1)
first.setMonth(first.getMonth() - 35)
const filters = reactive<ForecastFilters>({ metric: 'profit', period: 'monthly', horizon: 3, from: dateInput(first), to: today, currency: 'USD' })
const metrics: ForecastMetric[] = ['revenue', 'profit', 'expense', 'loss', 'orders', 'average_order_value']
const result = ref<BusinessForecast | null>(null)
const loading = ref(false)
const error = ref('')
const expenseError = ref('')
const success = ref('')
const expenses = ref<BusinessExpense[]>([])
const expensePage = ref(1)
const expensePages = ref(1)
const shops = ref<{ id: number; name: string }[]>([])
const shopSearch = ref('')
const shopsLoading = ref(false)
const shopsError = ref('')
let requestId = 0
let controller: AbortController | undefined
let appliedFilters: ForecastFilters = { ...filters }
const amount = (value: number | null | undefined, metric: ForecastMetric = 'profit') => value === null || value === undefined
  ? t('analytics.unavailable') : metric === 'orders' ? formatCompactNumber(value) : formatPrice(value, result.value?.currency ?? filters.currency)
const canManageExpenses = computed(() => !isAdmin || !!filters.shop_id)
const cards = computed(() => [
  { key: 'revenue', label: t('analytics.metrics.revenue'), value: amount(result.value?.summary.revenue), detail: t('analytics.revenue_help'), icon: CircleDollarSign, tone: 'blue' as const },
  { key: 'profit', label: t('analytics.metrics.profit'), value: amount(result.value?.summary.profit), detail: t('analytics.profit_help'), icon: TrendingUp, tone: 'green' as const },
  { key: 'expense', label: t('analytics.metrics.expense'), value: amount(result.value?.summary.expense), detail: t('analytics.expense_help'), icon: Wallet, tone: 'amber' as const },
  { key: 'forecast', label: t('analytics.projected', { metric: t(`analytics.metrics.${result.value?.metric ?? filters.metric}`) }), value: amount(result.value?.forecast_total, result.value?.metric ?? filters.metric), detail: t('analytics.forecast_help', { count: result.value?.forecast.length ?? 0 }), icon: ChartNoAxesCombined, tone: 'violet' as const }
])
const confidenceClass = computed(() => result.value?.confidence === 'high' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : result.value?.confidence === 'medium' ? 'bg-primary/10 text-primary' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300')

async function findShops() {
  shopsLoading.value = true
  shopsError.value = ''
  try {
    const { data } = await adminApi.listShops({ q: shopSearch.value || undefined })
    const selected = shops.value.find(shop => shop.id === filters.shop_id)
    shops.value = [...(selected && !data.data.some(shop => shop.id === selected.id) ? [selected] : []), ...data.data]
  } catch (cause) { shopsError.value = extractErrorMessage(cause, t('analytics.load_error')) }
  finally { shopsLoading.value = false }
}

async function load(regenerate = false) {
  const current = ++requestId
  controller?.abort()
  controller = new AbortController()
  loading.value = true
  error.value = ''
  expenseError.value = ''
  result.value = null
  expenses.value = []
  expensePage.value = 1
  appliedFilters = { ...filters }
  const responses = await Promise.allSettled([
    analyticsApi.forecast(shopId, appliedFilters, controller.signal, regenerate),
    analyticsApi.expenses(shopId, appliedFilters)
  ])
  if (current !== requestId) return
  const forecast = responses[0]
  const ledger = responses[1]
  if (forecast.status === 'fulfilled') result.value = forecast.value.data.data
  else if (!axios.isCancel(forecast.reason)) error.value = extractErrorMessage(forecast.reason, t('analytics.load_error'))
  if (ledger.status === 'fulfilled') { expenses.value = ledger.value.data.data; expensePages.value = ledger.value.data.meta.last_page }
  else expenseError.value = extractErrorMessage(ledger.reason, t('analytics.expenses_error'))
  loading.value = false
}

async function changeExpensePage(page: number) {
  const current = requestId
  expenseError.value = ''
  try {
    const { data } = await analyticsApi.expenses(shopId, appliedFilters, page)
    if (current !== requestId) return
    expenses.value = data.data
    expensePage.value = data.meta.current_page
    expensePages.value = data.meta.last_page
  } catch (cause) { if (current === requestId) expenseError.value = extractErrorMessage(cause, t('analytics.expenses_error')) }
}

const expenseOpen = ref(false)
const editing = ref<number | undefined>()
const deleting = ref<BusinessExpense | null>(null)
const saving = ref(false)
const mutationError = ref('')
const form = reactive<ExpensePayload>({ category: 'other', description: '', amount: 0, currency: 'USD', incurred_on: today })
function openExpense(expense?: BusinessExpense) {
  mutationError.value = ''
  editing.value = expense?.id
  Object.assign(form, expense ? { category: expense.category, description: expense.description, amount: expense.amount, currency: expense.currency, incurred_on: expense.incurred_on } : { category: 'other', description: '', amount: 0, currency: filters.currency, incurred_on: today })
  expenseOpen.value = true
}
async function saveExpense() {
  saving.value = true
  mutationError.value = ''
  try {
    await analyticsApi.saveExpense(shopId, form, filters.shop_id, editing.value)
    expenseOpen.value = false
    success.value = t('analytics.expense_saved')
    await load(true)
  } catch (cause) { mutationError.value = extractErrorMessage(cause, t('analytics.save_error')) }
  finally { saving.value = false }
}
async function deleteExpense() {
  if (!deleting.value) return
  saving.value = true
  mutationError.value = ''
  try {
    await analyticsApi.deleteExpense(shopId, deleting.value.id)
    deleting.value = null
    success.value = t('analytics.expense_deleted')
    await load(true)
  } catch (cause) { mutationError.value = extractErrorMessage(cause, t('analytics.save_error')) }
  finally { saving.value = false }
}
function closeExpense(open: boolean) { if (!saving.value) expenseOpen.value = open }
function closeDelete(open: boolean) { if (!open && !saving.value) deleting.value = null }
onMounted(() => { void load(); if (isAdmin) void findShops() })
onBeforeUnmount(() => { requestId++; controller?.abort() })
</script>

<template>
  <div class="min-w-0 space-y-6" :aria-busy="loading">
    <DashboardPageHeader :title="t('analytics.title')" :description="t('analytics.description')">
      <button type="button" class="btn-secondary" :disabled="loading || saving" @click="load(true)"><RefreshCw :size="17" :class="{ 'animate-spin': loading }" aria-hidden="true" />{{ t('analytics.regenerate') }}</button>
    </DashboardPageHeader>

    <form class="card space-y-4 p-5 sm:p-6" @submit.prevent="load()">
      <div v-if="isAdmin" class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <label class="text-sm text-muted">{{ t('analytics.shop') }}<select v-model="filters.shop_id" class="select mt-2"><option :value="undefined">{{ t('analytics.all_shops') }}</option><option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option></select></label>
        <div><label for="analytics-shop-search" class="text-sm text-muted">{{ t('analytics.find_shop') }}</label><div class="mt-2 flex gap-2"><input id="analytics-shop-search" v-model="shopSearch" type="search" class="input min-w-0" @keydown.enter.prevent="findShops" /><button type="button" class="btn-secondary !px-3" :aria-label="t('actions.search')" :disabled="shopsLoading" @click="findShops"><Search :size="18" /></button></div></div>
        <p v-if="shopsError" role="alert" class="text-sm text-red-600">{{ shopsError }}</p>
      </div>
      <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <label class="text-sm text-muted">{{ t('analytics.metric') }}<select v-model="filters.metric" class="select mt-2"><option v-for="metric in metrics" :key="metric" :value="metric">{{ t(`analytics.metrics.${metric}`) }}</option></select></label>
        <label class="text-sm text-muted">{{ t('analytics.period') }}<select v-model="filters.period" class="select mt-2"><option v-for="period in ['monthly', 'quarterly', 'yearly']" :key="period" :value="period">{{ t(`analytics.periods.${period}`) }}</option></select></label>
        <label class="text-sm text-muted">{{ t('analytics.horizon') }}<select v-model="filters.horizon" class="select mt-2"><option v-for="count in [1, 2, 3, 6, 12]" :key="count" :value="count">{{ t('analytics.next_periods', { count }) }}</option></select></label>
        <label class="text-sm text-muted">{{ t('analytics.currency') }}<select v-model="filters.currency" class="select mt-2"><option value="USD">USD</option><option value="KHR">KHR</option></select></label>
        <label class="text-sm text-muted">{{ t('analytics.from') }}<input v-model="filters.from" type="date" class="input mt-2" :max="filters.to || today" required /></label>
        <label class="text-sm text-muted">{{ t('analytics.to') }}<input v-model="filters.to" type="date" class="input mt-2" :min="filters.from" :max="today" required /></label>
        <button type="submit" class="btn-primary min-h-11" :disabled="loading || saving">{{ t('analytics.apply') }}</button>
      </div>
    </form>

    <p v-if="success" role="status" class="rounded-xl bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-300">{{ success }}</p>
    <div v-if="error" role="alert" class="card p-5 text-sm text-red-600 dark:text-red-300">{{ error }}<button type="button" class="ml-3 font-semibold underline" @click="load()">{{ t('actions.retry') }}</button></div>
    <div v-if="loading" class="grid animate-pulse gap-4 sm:grid-cols-2 xl:grid-cols-4" role="status" :aria-label="t('common.loading')"><div v-for="i in 4" :key="i" class="h-48 rounded-2xl bg-border-gray/40"></div></div>

    <template v-if="result">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><DashboardMetricCard v-for="card in cards" :key="card.key" :label="card.label" :value="card.value" :detail="card.detail" :icon="card.icon" :tone="card.tone" /></div>
      <div class="grid gap-4 xl:grid-cols-3">
        <DashboardSection :title="t('analytics.chart_title')" :description="t('analytics.chart_help')" class="xl:col-span-2">
          <div class="p-4 sm:p-6"><BusinessForecastChart v-if="result.history.length" :data="result" /><div v-else class="flex h-64 flex-col items-center justify-center gap-3 text-center"><ChartNoAxesCombined :size="30" class="text-muted" aria-hidden="true" /><p class="font-semibold">{{ t('analytics.no_history') }}</p><p class="max-w-md text-sm text-muted">{{ t('analytics.no_history_help') }}</p></div></div>
        </DashboardSection>
        <DashboardSection :title="t('analytics.forecast_details')" :icon="ShieldCheck">
          <div class="space-y-5 p-5">
            <div><p class="text-xs text-muted">{{ t('analytics.confidence') }}</p><span class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold" :class="confidenceClass">{{ t(`analytics.confidence_levels.${result.confidence}`) }}</span></div>
            <dl class="space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-muted">{{ t('analytics.model') }}</dt><dd class="text-right font-medium">{{ result.model ? t(`analytics.models.${result.model}`) : t('analytics.unavailable') }}</dd></div><div class="flex justify-between gap-3"><dt class="text-muted">{{ t('analytics.complete_periods') }}</dt><dd class="font-medium">{{ result.data_points }}</dd></div><div class="flex justify-between gap-3"><dt class="text-muted">{{ t('analytics.trend') }}</dt><dd class="font-medium">{{ result.trend ? t(`analytics.trends.${result.trend}`) : t('analytics.unavailable') }}</dd></div><div class="flex justify-between gap-3"><dt class="text-muted">{{ t('analytics.growth') }}</dt><dd class="font-medium">{{ result.growth_percentage === null ? t('analytics.unavailable') : `${result.growth_percentage > 0 ? '+' : ''}${result.growth_percentage}%` }}</dd></div><div v-if="result.growth_speed" class="flex justify-between gap-3"><dt class="text-muted">{{ t('analytics.growth_speed') }}</dt><dd class="font-medium">{{ t(`analytics.speeds.${result.growth_speed}`) }}</dd></div></dl>
            <p v-if="result.status !== 'available'" class="rounded-xl bg-amber-500/10 p-3 text-sm leading-relaxed text-amber-800 dark:text-amber-200" role="status">{{ t(`analytics.states.${result.status}`) }}</p>
            <p class="border-t border-border-gray pt-4 text-xs leading-relaxed text-muted">{{ t('analytics.reliability_help') }}</p>
          </div>
        </DashboardSection>
      </div>

      <DashboardSection :title="t('analytics.history_title')" :description="t('analytics.history_help')">
        <div class="relative overflow-x-auto"><table class="w-full min-w-[600px] text-left text-sm"><thead class="border-b border-border-gray bg-canvas/60 text-xs text-muted"><tr><th scope="col" class="px-5 py-3">{{ t('analytics.period') }}</th><th scope="col" class="px-5 py-3">{{ t('analytics.type') }}</th><th scope="col" class="px-5 py-3 text-right">{{ t(`analytics.metrics.${result.metric}`) }}</th><th scope="col" class="px-5 py-3 text-right">{{ t('analytics.indicative_range') }}</th></tr></thead><tbody class="divide-y divide-border-gray"><tr v-for="point in [...result.history, ...result.forecast]" :key="point.type + point.period" :class="{ 'bg-primary/5': point.type === 'predicted' }"><td class="px-5 py-3 font-medium">{{ point.period }}<span v-if="point.partial" class="ml-2 text-xs text-muted">{{ t('analytics.partial') }}</span></td><td class="px-5 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="point.type === 'predicted' ? 'bg-violet-500/10 text-violet-700 dark:text-violet-300' : point.type === 'current' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300' : 'bg-primary/10 text-primary'">{{ t(`analytics.${point.type}`) }}</span></td><td class="px-5 py-3 text-right font-semibold tabular-nums">{{ amount(point.value, result.metric) }}</td><td class="px-5 py-3 text-right tabular-nums text-muted">{{ point.type === 'predicted' ? `${amount(point.lower_bound, result.metric)} – ${amount(point.upper_bound, result.metric)}` : '—' }}</td></tr><tr v-if="!result.history.length && !result.forecast.length"><td colspan="4" class="p-8 text-center text-muted">{{ t('analytics.no_history') }}</td></tr></tbody></table></div>
      </DashboardSection>
      <div class="card space-y-3 p-5 text-sm leading-relaxed text-muted"><p v-for="warning in result.warnings" :key="warning" class="flex gap-2"><Info :size="17" class="mt-0.5 shrink-0 text-primary" aria-hidden="true" /><span>{{ t(`analytics.warnings.${warning}`, { count: result.summary.missing_cost_items }) }}</span></p><p>{{ t('analytics.cost_coverage', { value: result.summary.cost_coverage }) }}</p><p class="border-t border-border-gray pt-3">{{ t('analytics.formula') }}</p></div>
    </template>

    <DashboardSection :title="t('analytics.expenses_title')" :description="t('analytics.expense_help')">
      <template #actions><button type="button" class="btn-primary btn-sm" :disabled="!canManageExpenses || loading || saving" :title="!canManageExpenses ? t('analytics.select_shop') : undefined" @click="openExpense()"><Plus :size="16" aria-hidden="true" />{{ t('analytics.add_expense') }}</button></template>
      <p v-if="!canManageExpenses" class="px-5 pt-4 text-sm text-muted">{{ t('analytics.select_shop') }}</p>
      <p v-if="expenseError" role="alert" class="p-5 text-sm text-red-600 dark:text-red-300">{{ expenseError }}</p>
      <div v-else class="relative overflow-x-auto"><table class="w-full min-w-[600px] text-left text-sm"><thead class="border-b border-border-gray bg-canvas/60 text-xs text-muted"><tr><th scope="col" class="px-5 py-3">{{ t('analytics.date') }}</th><th scope="col" class="px-5 py-3">{{ t('analytics.expense_description') }}</th><th v-if="isAdmin" scope="col" class="px-5 py-3">{{ t('analytics.shop') }}</th><th scope="col" class="px-5 py-3">{{ t('analytics.category') }}</th><th scope="col" class="px-5 py-3 text-right">{{ t('analytics.amount') }}</th><th scope="col" class="px-5 py-3"><span class="sr-only">{{ t('analytics.manage') }}</span></th></tr></thead><tbody class="divide-y divide-border-gray"><tr v-for="expense in expenses" :key="expense.id"><td class="whitespace-nowrap px-5 py-3">{{ expense.incurred_on }}</td><td class="max-w-sm px-5 py-3 break-words">{{ expense.description }}</td><td v-if="isAdmin" class="px-5 py-3">{{ expense.shop_name }}</td><td class="px-5 py-3 text-muted">{{ t(`analytics.categories.${expense.category}`) }}</td><td class="whitespace-nowrap px-5 py-3 text-right font-semibold tabular-nums">{{ formatPrice(expense.amount, expense.currency) }}</td><td class="px-5 py-3"><div class="flex gap-1"><button type="button" class="btn-icon" :aria-label="t('analytics.edit_expense', { description: expense.description })" :disabled="saving" @click="openExpense(expense)"><Pencil :size="16" /></button><button type="button" class="btn-icon" :aria-label="t('analytics.delete_expense', { description: expense.description })" :disabled="saving" @click="deleting = expense; mutationError = ''"><Trash2 :size="16" /></button></div></td></tr><tr v-if="!expenses.length && !loading"><td :colspan="isAdmin ? 6 : 5" class="p-8 text-center text-muted">{{ t('analytics.no_expenses') }}</td></tr></tbody></table></div>
      <div v-if="expensePages > 1" class="border-t border-border-gray p-4"><BasePagination :page="expensePage" :page-count="expensePages" :page-size="20" @update:page="changeExpensePage" /></div>
    </DashboardSection>
    <p class="text-xs leading-relaxed text-muted">{{ t('analytics.disclaimer') }}</p>

    <BaseModal :model-value="expenseOpen" :title="t(editing ? 'analytics.edit_title' : 'analytics.add_expense')" :close-on-backdrop="!saving" @update:model-value="closeExpense">
      <form id="business-expense-form" class="space-y-4" @submit.prevent="saveExpense">
        <p v-if="mutationError" role="alert" class="text-sm text-red-600 dark:text-red-300">{{ mutationError }}</p>
        <label class="block text-sm">{{ t('analytics.expense_description') }}<input v-model="form.description" class="input mt-2" maxlength="500" required :disabled="saving" /></label>
        <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm">{{ t('analytics.amount') }}<input v-model.number="form.amount" type="number" step="0.01" min="0.01" max="9999999999.99" class="input mt-2" required :disabled="saving" /></label><label class="text-sm">{{ t('analytics.date') }}<input v-model="form.incurred_on" type="date" :max="today" class="input mt-2" required :disabled="saving" /></label></div>
        <label class="block text-sm">{{ t('analytics.category') }}<select v-model="form.category" class="select mt-2" :disabled="saving"><option v-for="category in ['rent', 'payroll', 'shipping', 'marketing', 'fees', 'other']" :key="category" :value="category">{{ t(`analytics.categories.${category}`) }}</option></select></label>
        <p class="text-xs text-muted">{{ t('analytics.currency') }}: {{ form.currency }}</p>
      </form>
      <template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary" :disabled="saving" @click="closeExpense(false)">{{ t('actions.cancel') }}</button><button type="submit" form="business-expense-form" class="btn-primary" :disabled="saving">{{ t('actions.save') }}</button></div></template>
    </BaseModal>
    <BaseModal :model-value="deleting !== null" :title="t('analytics.delete_title')" size="sm" :close-on-backdrop="!saving" @update:model-value="closeDelete"><p class="text-sm leading-relaxed text-muted">{{ t('analytics.delete_help', { description: deleting?.description }) }}</p><p v-if="mutationError" role="alert" class="mt-3 text-sm text-red-600">{{ mutationError }}</p><template #footer><div class="flex justify-end gap-2"><button class="btn-secondary" :disabled="saving" @click="closeDelete(false)">{{ t('actions.cancel') }}</button><button class="btn-danger" :disabled="saving" @click="deleteExpense">{{ t('actions.delete') }}</button></div></template></BaseModal>
  </div>
</template>
