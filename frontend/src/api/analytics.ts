import apiClient from './client'

export type ForecastMetric = 'revenue' | 'profit' | 'expense' | 'loss' | 'orders' | 'average_order_value'
export type ForecastPeriod = 'monthly' | 'quarterly' | 'yearly'
export interface FinancialMetrics {
  revenue: number
  cogs: number | null
  profit: number | null
  expense: number
  loss: number | null
  orders: number
  average_order_value: number | null
  refunds: number
  missing_cost_items: number
  cost_coverage: number
}
export interface ForecastPoint {
  period: string
  start: string
  end: string
  type: 'actual' | 'current' | 'predicted'
  value: number | null
  lower_bound?: number | null
  upper_bound?: number | null
  metrics?: FinancialMetrics
  partial?: boolean
}
export interface BusinessForecast {
  scope: { shop_id: number | null; shop_name: string | null; kind: 'marketplace' | 'shop' }
  currency: string
  metric: ForecastMetric
  period: ForecastPeriod
  from: string
  to: string
  summary: FinancialMetrics
  history: ForecastPoint[]
  forecast: ForecastPoint[]
  status: 'available' | 'insufficient_history' | 'missing_costs'
  model: 'linear_regression' | 'moving_average' | 'growth_rate' | null
  confidence: 'high' | 'medium' | 'low' | 'unavailable'
  confidence_score: number
  data_points: number
  backtest_mae: number | null
  growth_percentage: number | null
  growth_speed: 'accelerating' | 'slowing' | 'stable' | null
  trend: 'growing' | 'declining' | 'stable' | null
  forecast_total: number | null
  warnings: string[]
  generated_at: string
}
export interface ForecastFilters {
  metric: ForecastMetric
  period: ForecastPeriod
  horizon: number
  from: string
  to: string
  currency: string
  shop_id?: number
}
export interface BusinessExpense {
  id: number
  shop_id: number
  shop_name: string | null
  category: 'rent' | 'payroll' | 'shipping' | 'marketing' | 'fees' | 'other'
  description: string
  amount: number
  currency: string
  incurred_on: string
}
export type ExpensePayload = Pick<BusinessExpense, 'category' | 'description' | 'amount' | 'currency' | 'incurred_on'>
const base = (shopId: number | null) => shopId === null ? '/admin' : `/seller/shops/${shopId}`
export const analyticsApi = {
  forecast(shopId: number | null, params: ForecastFilters, signal?: AbortSignal, regenerate = false) {
    const url = `${base(shopId)}/forecasts`
    return regenerate
      ? apiClient.post<{ data: BusinessForecast }>(`${url}/generate`, params, { signal })
      : apiClient.get<{ data: BusinessForecast }>(url, { params, signal })
  },
  expenses(shopId: number | null, params: Pick<ForecastFilters, 'from' | 'to' | 'currency' | 'shop_id'>, page = 1) {
    return apiClient.get<{ data: BusinessExpense[]; meta: { current_page: number; last_page: number; total: number } }>(`${base(shopId)}/expenses`, { params: { ...params, page } })
  },
  saveExpense(shopId: number | null, payload: ExpensePayload, selectedShop?: number, id?: number) {
    const url = `${base(shopId)}/expenses`
    const body = { ...payload, ...(selectedShop ? { shop_id: selectedShop } : {}) }
    return id ? apiClient.put<{ data: BusinessExpense }>(`${url}/${id}`, body) : apiClient.post<{ data: BusinessExpense }>(url, body)
  },
  deleteExpense(shopId: number | null, id: number) { return apiClient.delete(`${base(shopId)}/expenses/${id}`) }
}
