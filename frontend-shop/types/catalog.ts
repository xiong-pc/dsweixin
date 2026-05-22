/**
 * 商城前台公开 API 数据类型（M11-PR43）。
 *
 * 与 backend/app/Http/Resources/Api/Shop/Shop{Category,Product}Resource 输出一一对应。
 */

export interface ShopCategory {
  id: number
  parent_id: number
  code: string
  cover_image: string
  sort: number
  name: string
  description: string
}

export interface ShopProductTranslationLite {
  locale: string
  name: string
  slug: string
}

export interface ShopProduct {
  id: number
  category_id: number | null
  brand_id: number | null
  cover_image: string
  images: string[]
  base_price: string | number
  base_currency: string
  name: string
  slug: string
  short_description: string
  description: string
  seo: {
    title: string
    keywords: string
    description: string
  }
  translations: ShopProductTranslationLite[]
}

/** 后端 paginate() 输出的 list / total / page / pageSize 包结构 */
export interface PaginatedList<T> {
  list: T[]
  total: number
  page: number
  pageSize: number
}
