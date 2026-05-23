<script setup lang="ts">
  import type { ShopProduct } from '~/types/catalog'
  import { ApiError } from '~/composables/useApi'

  /**
   * 商品详情页（M11-PR44）：SSR + 完整 SEO 套件。
   *
   * SEO 输出（首屏 SSR HTML 内即可见）：
   * - <title> + <meta description> 优先 seo_*，回退 name / short_description
   * - Open Graph：og:title / og:description / og:image / og:type=product / og:url
   * - Twitter Card：summary_large_image
   * - canonical：当前 locale 的 URL
   * - alternate hreflang：每条翻译 → /<locale>/product/<slug>
   * - x-default hreflang：默认语言
   * - JSON-LD Product schema：Google Rich Snippets / 知识图谱
   *
   * 404 行为：商品不存在或下线时 setResponseStatus(404)，搜索引擎不收录。
   */
  const route = useRoute()
  const { t, locale, locales: localesRef } = useI18n()
  const requestURL = useRequestURL()

  const slug = computed(() => String(route.params.slug ?? ''))

  const { data: product, error } = await useAsyncData<ShopProduct | null>(
    () => `product-${slug.value}`,
    async () => {
      try {
        return await useApi<ShopProduct>(`products/by-slug/${encodeURIComponent(slug.value)}`)
      } catch (e) {
        if (e instanceof ApiError && (e.code === 404 || e.code === 400)) {
          return null
        }
        throw e
      }
    },
    { watch: [slug] },
  )

  if (!product.value) {
    setResponseStatus(404)
  }

  // ===== SEO 计算属性 =====

  const seoTitle = computed(() => {
    const p = product.value
    if (!p) return t('product.not_found')
    return p.seo.title || p.name || p.slug
  })

  const seoDescription = computed(() => {
    const p = product.value
    if (!p) return t('product.not_found_desc')
    return p.seo.description || p.short_description || p.name
  })

  // 当前页 canonical（带 locale 前缀的真实 URL）
  const canonical = computed(() => {
    const origin = `${requestURL.protocol}//${requestURL.host}`
    return `${origin}${route.fullPath}`
  })

  // hreflang：每条翻译生成一条 <link rel="alternate">
  interface I18nLocaleMeta {
    code: string
    iso?: string
  }

  const alternateLinks = computed(() => {
    const p = product.value
    if (!p) return []
    const origin = `${requestURL.protocol}//${requestURL.host}`
    const rawLocales = Array.isArray(localesRef.value) ? localesRef.value : []
    const localesArr = rawLocales as unknown as I18nLocaleMeta[]
    const defaultLocale = 'zh-CN'

    const links: Array<{ rel: 'alternate'; hreflang: string; href: string }> = []
    for (const tr of p.translations) {
      if (!tr.slug) continue
      const localeMeta = localesArr.find((l) => l.code === tr.locale)
      const hreflang: string = localeMeta?.iso ?? tr.locale
      const prefix = tr.locale === defaultLocale ? '' : `/${tr.locale}`
      const href = `${origin}${prefix}/product/${tr.slug}`
      links.push({ rel: 'alternate', hreflang, href })
    }

    // x-default
    const def = p.translations.find((tr) => tr.locale === defaultLocale) ?? p.translations[0]
    if (def?.slug) {
      links.push({ rel: 'alternate', hreflang: 'x-default', href: `${origin}/product/${def.slug}` })
    }
    return links
  })

  // JSON-LD Product schema —— 序列化为 string 嵌入 <script>
  const jsonLd = computed(() => {
    const p = product.value
    if (!p) return ''
    const obj: Record<string, unknown> = {
      '@context': 'https://schema.org/',
      '@type': 'Product',
      name: p.name || p.slug,
      description: p.short_description || p.seo.description || p.name,
      sku: String(p.id),
      url: canonical.value,
      offers: {
        '@type': 'Offer',
        price: String(p.base_price),
        priceCurrency: p.base_currency,
        availability: 'https://schema.org/InStock',
        url: canonical.value,
      },
    }
    if (p.cover_image) {
      obj.image = p.images && p.images.length > 0 ? p.images : [p.cover_image]
    }
    return JSON.stringify(obj)
  })

  useHead({
    title: seoTitle,
    htmlAttrs: { lang: locale },
    link: () => [
      { rel: 'canonical', href: canonical.value },
      ...alternateLinks.value.map((l) => ({ rel: l.rel, hreflang: l.hreflang, href: l.href })),
    ],
    meta: () => {
      const p = product.value
      const ogImage = p?.cover_image || (p?.images?.[0] ?? '')
      return [
        { name: 'description', content: seoDescription.value },
        ...(p?.seo.keywords ? [{ name: 'keywords', content: p.seo.keywords }] : []),
        { property: 'og:type', content: 'product' },
        { property: 'og:title', content: seoTitle.value },
        { property: 'og:description', content: seoDescription.value },
        { property: 'og:url', content: canonical.value },
        ...(ogImage ? [{ property: 'og:image', content: ogImage }] : []),
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'twitter:title', content: seoTitle.value },
        { name: 'twitter:description', content: seoDescription.value },
        ...(ogImage ? [{ name: 'twitter:image', content: ogImage }] : []),
      ]
    },
    script: () =>
      jsonLd.value
        ? [
            {
              type: 'application/ld+json',
              children: jsonLd.value,
            },
          ]
        : [],
  })

  // ===== 行为 =====

  // SpecSelector v-model：当前选中的 variant_id
  const selectedVariantId = ref<number | null>(null)
  const selectedVariant = computed(() => {
    const p = product.value
    if (!p || !selectedVariantId.value) return null
    return p.variants.find((v) => v.id === selectedVariantId.value) ?? null
  })

  // 价格优先取选中变体的，否则回退商品 base_price（PR47 替换为 Intl.NumberFormat 国际化）
  const formattedPrice = computed(() => {
    const p = product.value
    if (!p) return ''
    const variantPrice = selectedVariant.value?.price
    const raw = variantPrice ?? p.base_price
    const num = Number(raw)
    if (!Number.isFinite(num)) return ''
    return `${p.base_currency} ${num.toFixed(2)}`
  })
</script>

<template>
  <article class="mx-auto max-w-7xl px-4 py-10">
    <p v-if="error" class="text-red-500">{{ t('common.error') }}</p>

    <div v-else-if="!product" class="rounded border border-gray-200 p-8 text-center">
      <h1 class="text-2xl font-semibold text-gray-900">{{ t('product.not_found') }}</h1>
      <p class="mt-2 text-gray-500">{{ t('product.not_found_desc') }}</p>
      <NuxtLink to="/" class="mt-6 inline-block text-primary hover:underline">
        {{ t('product.back_home') }}
      </NuxtLink>
    </div>

    <div v-else class="grid grid-cols-1 gap-8 md:grid-cols-2">
      <ProductImageGallery :images="product.images" :alt="product.name || product.slug" />

      <section class="flex flex-col gap-5">
        <header>
          <h1 class="text-3xl font-bold text-gray-900">
            {{ product.name || product.slug }}
          </h1>
          <p v-if="product.short_description" class="mt-3 text-gray-600">
            {{ product.short_description }}
          </p>
        </header>

        <div class="text-3xl font-bold text-primary">{{ formattedPrice }}</div>

        <ProductSpecSelector
          v-if="product.variants.length > 0"
          v-model:selected-variant-id="selectedVariantId"
          :variants="product.variants"
        />
        <p v-else class="text-sm text-gray-500">
          {{ t('product.no_variants') }}
        </p>

        <ProductAddToCartButton :variant-id="selectedVariantId" />

        <section v-if="product.description" class="prose mt-8 max-w-none">
          <h2 class="text-lg font-semibold text-gray-900">
            {{ t('product.description') }}
          </h2>
          <div class="mt-2 whitespace-pre-line text-sm text-gray-700">
            {{ product.description }}
          </div>
        </section>
      </section>
    </div>
  </article>
</template>
