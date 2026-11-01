export interface CodPayment {
  id: number
  payment_id: number
  method: 'cod'
  order_number: string
  shop_order_number: string
  shop: { id: number; name: string | null }
  amount: number
  currency: string
  status: string
  can_collect: boolean
  collected_at: string | null
  collected_by: string | null
}
