export type AnalyticsPage = { url: string; title: string; referrer: string }

// Track published localized pages, never account recovery or application endpoints.
export function publicAnalyticsUrl(value: string, siteUrl: string): string | null {
  try {
    const site = new URL(siteUrl)
    const url = new URL(value, site)
    if (site.origin !== 'https://govista.am' || url.origin !== site.origin) return null
    if (url.username || url.password || !/^\/(hy|ru|en)(?:\/|$)/.test(url.pathname)) return null
    const path = decodeURIComponent(url.pathname)
    if (/^\/(hy|ru|en)\/(account|admin|api|error|auth|login|reset-password)(?:\/|$)/i.test(path)) return null
    return `${site.origin}${url.pathname.replace(/\/$/, '')}`
  }
  catch {
    return null
  }
}

export function analyticsReferrer(value: string, siteUrl: string): string {
  if (!value) return ''
  try {
    const url = new URL(value)
    if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password) return ''
    if (url.origin === new URL(siteUrl).origin) return publicAnalyticsUrl(value, siteUrl) || ''
    // Search terms and personal data can also appear in external referrer paths.
    return `${url.origin}/`
  }
  catch {
    return ''
  }
}

export function createAnalyticsPageState(siteUrl: string, initialReferrer = '') {
  let current: AnalyticsPage | null = null
  let referrer = analyticsReferrer(initialReferrer, siteUrl)
  return {
    visit(value: string, title: string, hasError = false): AnalyticsPage | null {
      const url = hasError ? null : publicAnalyticsUrl(value, siteUrl)
      if (!url) {
        current = null
        referrer = ''
        return null
      }
      if (current?.url === url) return null
      current = { url, title, referrer }
      referrer = url
      return current
    },
    isCurrent(page: AnalyticsPage) { return current === page },
    clear() { current = null; referrer = '' },
  }
}
