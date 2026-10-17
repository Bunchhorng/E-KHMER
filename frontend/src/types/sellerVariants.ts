export interface SellerVariant {
  key: string; id?: number; name: string; sku: string; price: number; compare_at_price: number | null
  quantity: number; reserved_quantity: number; is_active: boolean; image_id: number | null
  attributes: { attribute: string; value: string }[]
}

