import { createAnalyticsPageState, publicAnalyticsUrl, type AnalyticsPage } from '../utils/site-analytics'

type TagCommand = (...args: unknown[]) => void
type MetricaCommand = TagCommand & { a?: IArguments[]; l?: number }
type AnalyticsWindow = Window & {
  dataLayer?: IArguments[]
  govistaMetricaDataLayer?: unknown[]
  gtag?: TagCommand
  ym?: MetricaCommand
  [key: `ga-disable-${string}`]: boolean
}

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig().public
  const siteUrl = String(config.siteUrl)
  if (import.meta.dev || !publicAnalyticsUrl(`${window.location.origin}/hy`, siteUrl)) return
  const googleId = /^G-[A-Z0-9]+$/.test(String(config.googleAnalyticsId)) ? String(config.googleAnalyticsId) : ''
  const metricaId = /^[1-9]\d*$/.test(String(config.yandexMetricaId)) ? Number(config.yandexMetricaId) : 0
  if (!googleId && !metricaId) return

  const browser = window as AnalyticsWindow
  const error = useError()
  const router = useRouter()
  const pages = createAnalyticsPageState(siteUrl, document.referrer)
  let googleReady = false
  let metricaReady = false
  let googleLoad: Promise<boolean> | undefined
  let metricaLoad: Promise<boolean> | undefined
  let frame = 0

  const loadScript = (id: string, src: string) => new Promise<boolean>((resolve) => {
    const script = document.createElement('script')
    script.id = id
    script.async = true
    script.src = src
    script.referrerPolicy = 'no-referrer'
    script.onload = () => resolve(true)
    script.onerror = () => resolve(false)
    document.head.appendChild(script)
  })

  function pause() {
    pages.clear()
    if (googleId) browser[`ga-disable-${googleId}`] = true
    if (metricaReady) {
      browser.ym?.(metricaId, 'destruct')
      metricaReady = false
    }
  }

  const isCurrent = (page: AnalyticsPage) => !error.value && pages.isCurrent(page)
    && publicAnalyticsUrl(window.location.href, siteUrl) === page.url

  async function sendGoogle(page: AnalyticsPage) {
    if (!googleLoad) {
      browser.dataLayer ||= []
      browser.gtag ||= function () { browser.dataLayer!.push(arguments) }
      googleLoad = loadScript('govista-google-analytics', `https://www.googletagmanager.com/gtag/js?id=${googleId}`)
    }
    if (!await googleLoad || !isCurrent(page)) return
    browser[`ga-disable-${googleId}`] = false
    const fields = { page_location: page.url, page_referrer: page.referrer, page_title: page.title }
    // Set before initialization so even automatic session events use clean URLs.
    browser.gtag!('set', fields)
    if (!googleReady) {
      browser.gtag!('js', new Date())
      // The GA4 stream's Enhanced Measurement must also be disabled.
      browser.gtag!('config', googleId, {
        send_page_view: false,
        allow_google_signals: false, allow_ad_personalization_signals: false,
      })
      googleReady = true
    }
    browser.gtag!('event', 'page_view', { ...fields, send_to: googleId })
  }

  async function sendMetrica(page: AnalyticsPage) {
    if (!metricaLoad) {
      browser.ym ||= function () { (browser.ym!.a ||= []).push(arguments) }
      browser.ym.l = Date.now()
      metricaLoad = loadScript('govista-yandex-metrica', 'https://mc.yandex.ru/metrika/tag.js')
    }
    if (!await metricaLoad || !isCurrent(page)) return
    // Metrica's deferred init still sends a nohit request. Its URL/referrer
    // defaults include raw browser values, and empty overrides fall through.
    const referrer = page.referrer || `${new URL(siteUrl).origin}/`
    if (!metricaReady) {
      // A false ecommerce option can fall back to the dashboard's shared layer.
      // Keep Metrica on its own empty layer; this integration never pushes to it.
      browser.govistaMetricaDataLayer = []
      browser.ym!(metricaId, 'init', {
        url: page.url, referrer,
        defer: true, webvisor: false, clickmap: false, trackLinks: false,
        trackHash: false, accurateTrackBounce: false, ecommerce: 'govistaMetricaDataLayer',
        childIframe: false, sendTitle: false, disableYtm: true,
      })
      metricaReady = true
    }
    browser.ym!(metricaId, 'hit', page.url, { title: page.title, referer: referrer })
  }

  function recordPage() {
    if (error.value || !publicAnalyticsUrl(window.location.href, siteUrl)) { pause(); return }
    const page = pages.visit(window.location.href, document.title)
    if (!page) return
    if (googleId) void sendGoogle(page)
    if (metricaId) void sendMetrica(page)
  }

  // Both hooks can fire on hydration. The page state deduplicates their view.
  const schedule = () => {
    cancelAnimationFrame(frame)
    frame = requestAnimationFrame(recordPage)
  }
  nuxtApp.hook('app:mounted', schedule)
  nuxtApp.hook('page:finish', schedule)
  nuxtApp.hook('app:error', pause)
  watch(error, value => { if (value) pause() }, { flush: 'sync' })
  router.beforeEach((to) => {
    if (!publicAnalyticsUrl(to.fullPath, siteUrl)) pause()
  })
  // Query/hash-only navigation must not produce another view, but leaving a
  // private page for the same public path must count as a new visit.
  router.afterEach((_to, _from, failure) => { if (failure) schedule() })
})
