type Resource = {
  slug?: string
  type?: string
  updated_at?: string
}

type PaginatedResource = {
  data?: Resource[]
  meta?: { last_page?: number }
}

type SitemapEntry = {
  path: string
  lastmod?: string
  changefreq: 'daily' | 'weekly' | 'monthly' | 'yearly'
  priority: string
}

const escapeXml = (value: string) => value
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&apos;')

const dateOnly = (value?: string) => {
  if (!value) return undefined
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? undefined : date.toISOString().slice(0, 10)
}

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
  const apiBase = String(config.apiBaseInternal).replace(/\/$/, '')
  const locales = ['hy', 'ru', 'en']
  const entries: SitemapEntry[] = [
    { path: '', changefreq: 'daily', priority: '1.0' },
    { path: '/tours', changefreq: 'daily', priority: '0.9' },
    { path: '/domestic-tours', changefreq: 'daily', priority: '0.9' },
    { path: '/international-tours', changefreq: 'daily', priority: '0.9' },
    { path: '/stays', changefreq: 'daily', priority: '0.9' },
    { path: '/cars', changefreq: 'daily', priority: '0.9' },
    { path: '/destinations', changefreq: 'weekly', priority: '0.8' },
    { path: '/blog', changefreq: 'weekly', priority: '0.8' },
    { path: '/travel', changefreq: 'weekly', priority: '0.9' },
  ]

  let tours: PaginatedResource = {}
  let destinations: PaginatedResource = {}
  let posts: PaginatedResource = {}
  let services: Resource[] = []
  let pages: PaginatedResource = {}

  const fetchCollection = async (resource: string): Promise<PaginatedResource> => {
    const data: Resource[] = []
    let page = 1
    let lastPage = 1
    do {
      const response = await $fetch<PaginatedResource>(`${apiBase}/v1/${resource}`, {
        query: { locale: 'en', per_page: 48, page }, timeout: 8000, retry: 1,
      })
      data.push(...(response.data || []))
      lastPage = response.meta?.last_page || 1
      page += 1
    } while (page <= lastPage)
    return { data }
  }

  try {
    [tours, destinations, posts, services, pages] = await Promise.all([
      fetchCollection('tours'),
      fetchCollection('destinations'),
      fetchCollection('posts'),
      $fetch<Resource[]>(`${apiBase}/v1/services`, { query: { locale: 'en' }, timeout: 8000, retry: 1 }),
      fetchCollection('pages'),
    ])
  }
  catch {
    setResponseHeader(event, 'Cache-Control', 'no-store')
    setResponseHeader(event, 'Retry-After', '60')
    throw createError({ statusCode: 503, statusMessage: 'Sitemap temporarily unavailable' })
  }

  entries.push(
    ...(pages.data || []).filter(item => item.slug).map(item => ({
      path: `/${item.slug}`,
      lastmod: dateOnly(item.updated_at),
      changefreq: 'monthly' as const,
      priority: '0.5',
    })),
    ...(tours.data || []).filter(item => item.slug).map(item => ({
      path: `/tours/${item.slug}`,
      lastmod: dateOnly(item.updated_at),
      changefreq: 'weekly' as const,
      priority: '0.8',
    })),
    ...(destinations.data || []).filter(item => item.slug).map(item => ({
      path: `/destinations/${item.slug}`,
      lastmod: dateOnly(item.updated_at),
      changefreq: 'monthly' as const,
      priority: '0.7',
    })),
    ...(posts.data || []).filter(item => item.slug).map(item => ({
      path: `/blog/${item.slug}`,
      lastmod: dateOnly(item.updated_at),
      changefreq: 'monthly' as const,
      priority: '0.7',
    })),
    ...services
      .filter(item => item.slug && ['accommodation', 'transport', 'transfer', 'activity'].includes(String(item.type)))
      .map(item => ({
          path: `/${item.type === 'accommodation' ? 'stays' : item.type === 'transport' ? 'cars' : 'services'}/${item.slug}`,
          lastmod: dateOnly(item.updated_at),
          changefreq: 'weekly' as const,
          priority: '0.8',
      })),
  )

  const body = entries.flatMap(entry => locales.map((locale) => {
    const loc = `${siteUrl}/${locale}${entry.path}`
    const alternates = [
      ...locales.map(language => ({ language, href: `${siteUrl}/${language}${entry.path}` })),
      { language: 'x-default', href: `${siteUrl}/hy${entry.path}` },
    ]
      .map(({ language, href }) => `    <xhtml:link rel="alternate" hreflang="${language}" href="${escapeXml(href)}" />`)
      .join('\n')

    return [
      '  <url>',
      `    <loc>${escapeXml(loc)}</loc>`,
      entry.lastmod ? `    <lastmod>${entry.lastmod}</lastmod>` : '',
      `    <changefreq>${entry.changefreq}</changefreq>`,
      `    <priority>${entry.priority}</priority>`,
      alternates,
      '  </url>',
    ].filter(Boolean).join('\n')
  })).join('\n')

  setResponseHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  setResponseHeader(event, 'Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400')

  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${body}\n</urlset>\n`
})
