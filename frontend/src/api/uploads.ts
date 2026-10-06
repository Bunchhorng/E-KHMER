import apiClient from './client'

export interface UploadedImage {
  path: string
}

export interface ProductImageItem {
  id: number
  image_path: string
  alt_text: string | null
  sort_order: number
  is_cover: boolean
}

function toFormData(file: File, extra: Record<string, string | number | boolean> = {}): FormData {
  const form = new FormData()
  form.append('image', file)
  for (const [key, value] of Object.entries(extra)) {
    form.append(key, String(value))
  }
  return form
}

export const mediaApi = {
  uploadImage(file: File, context: 'products' | 'brands' | 'categories' | 'shops' = 'products') {
    return apiClient.post<{ data: UploadedImage }>('/admin/uploads/image', toFormData(file, { context }))
  },

  attachProductImage(productId: number, file: File, extra: { is_cover?: boolean; alt_text?: string } = {}) {
    return apiClient.post<{ data: ProductImageItem }>(
      `/admin/products/${productId}/images`,
      toFormData(file, extra)
    )
  },

  removeProductImage(productId: number, imageId: number) {
    return apiClient.delete<{ data: { message: string } }>(
      `/admin/products/${productId}/images/${imageId}`
    )
  },

  uploadBrandLogo(brandId: number, file: File) {
    return apiClient.post<{ data: { logo: string } }>(`/admin/brands/${brandId}/logo`, toFormData(file))
  },

  uploadCategoryImage(categoryId: number, file: File) {
    return apiClient.post<{ data: { image: string } }>(
      `/admin/categories/${categoryId}/image`,
      toFormData(file)
    )
  },

  deleteCategoryImage(categoryId: number) {
    return apiClient.delete<{ data: { message: string } }>(`/admin/categories/${categoryId}/image`)
  }
}
