import type { PaginatedList, ShopCategory, ShopProduct } from '~/types/catalog'

/**
 * 商城公开数据访问（M11-PR43）。
 *
 * 提供分类树 / 商品列表 / 单品的轻量包装；复用 useApi 注入 X-Tenant-Id / X-Shop-Subdomain。
 *
 * SSR 友好：在页面 setup 内通过 useAsyncData 包一层即可缓存 + 拿到 ref。
 */
export function useCategories() {
  return useApi<ShopCategory[]>('shop/categories')
}

export interface ProductListQuery {
  category_id?: number
  brand_id?: number
  keywords?: string
  pageNum?: number
  pageSize?: number
}

export function useProductList(query: ProductListQuery = {}) {
  return useApi<PaginatedList<ShopProduct>>('shop/products', { query })
}

export function useProduct(id: number | string) {
  return useApi<ShopProduct>(`shop/products/${id}`)
}

export function useProductBySlug(slug: string) {
  return useApi<ShopProduct>(`shop/products/by-slug/${encodeURIComponent(slug)}`)
}
