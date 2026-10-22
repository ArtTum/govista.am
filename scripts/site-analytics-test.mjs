import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { stripTypeScriptTypes } from 'node:module'
import { analyticsReferrer, createAnalyticsPageState, publicAnalyticsUrl } from '../frontend/app/utils/site-analytics.ts'

const site = 'https://govista.am'
assert.equal(publicAnalyticsUrl(`${site}/hy/tours?search=private@example.com#secret`, site), `${site}/hy/tours`)
assert.equal(publicAnalyticsUrl(`${site}/hy/`, site), `${site}/hy`)
for (const url of [
  `${site}/hy/account?reset_token=secret&email=private@example.com`,
  `${site}/en/%61ccount`, `${site}/ru/admin`, `${site}/api/v1/account`,
  `${site}/hy/error`, `${site}/__nuxt_error`, `${site}/de/tours`,
  `${site}/hy/%E0%A4%A`, 'http://govista.am/hy', 'https://www.govista.am/hy',
  'http://localhost:3000/hy', 'https://user:password@govista.am/hy',
]) assert.equal(publicAnalyticsUrl(url, site), null, url)
assert.equal(publicAnalyticsUrl('http://localhost:3000/hy', 'http://localhost:3000'), null)
assert.equal(analyticsReferrer('https://www.google.com/search?q=private@example.com#secret', site), 'https://www.google.com/')
assert.equal(analyticsReferrer(`${site}/hy/tours?email=private@example.com#secret`, site), `${site}/hy/tours`)
assert.equal(analyticsReferrer(`${site}/hy/account?reset_token=secret`, site), '')
assert.equal(analyticsReferrer('javascript:alert(1)', site), '')

const pages = createAnalyticsPageState(site, 'https://www.google.com/search?q=private@example.com')
const first = pages.visit(`${site}/hy`, 'Home')
assert.deepEqual(first, { url: `${site}/hy`, title: 'Home', referrer: 'https://www.google.com/' })
assert.equal(pages.visit(`${site}/hy`, 'Home'), null, 'Hydration must not duplicate a view')
assert.equal(pages.visit(`${site}/hy?email=private@example.com#secret`, 'Home'), null, 'Query and hash changes must not duplicate views')
const tours = pages.visit(`${site}/hy/tours`, 'Tours')
assert.equal(tours.referrer, `${site}/hy`)
assert.equal(pages.isCurrent(first), false, 'A late script load must not send a stale page')
assert.equal(pages.isCurrent(tours), true)
assert.equal(pages.visit(`${site}/hy/account?reset_token=secret`, 'Account'), null)
assert.equal(pages.isCurrent(tours), false, 'Private navigation must cancel pending views')
assert.deepEqual(pages.visit(`${site}/hy/tours`, 'Tours'), { url: `${site}/hy/tours`, title: 'Tours', referrer: '' })
assert.equal(pages.visit(`${site}/hy/missing`, 'Not found', true), null)
assert.equal(pages.visit(`${site}/hy`, 'Home').referrer, '', 'Error pages must not become referrers')
pages.clear()
assert.notEqual(pages.visit(`${site}/hy`, 'Home'), null, 'Returning after a private page is a new visit')

// Exercise the real Nuxt plugin against browser/analytics stubs. No network calls.
const pluginUrl = new URL('../frontend/app/plugins/site-analytics.client.ts', import.meta.url)
const helperUrl = new URL('../frontend/app/utils/site-analytics.ts', import.meta.url).href
const source = stripTypeScriptTypes(await readFile(pluginUrl, 'utf8'))
  .replace("'../utils/site-analytics'", JSON.stringify(helperUrl))
globalThis.defineNuxtPlugin = setup => setup
const { default: setupPlugin } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)

function browserFixture(href, googleId = 'G-TEST12345', metricaId = '12345678') {
  const hooks = new Map()
  const scripts = []
  const frames = new Map()
  const guards = []
  const failures = []
  const errors = { value: null }
  let watchError
  let frameId = 0
  globalThis.window = { location: new URL(href) }
  globalThis.document = {
    title: 'GoVista', referrer: 'https://www.google.com/search?q=private@example.com',
    createElement: () => ({}),
    head: { appendChild: script => { scripts.push(script); queueMicrotask(() => script.onload()) } },
  }
  globalThis.useRuntimeConfig = () => ({ public: { siteUrl: site, googleAnalyticsId: googleId, yandexMetricaId: metricaId } })
  globalThis.useError = () => errors
  globalThis.useRouter = () => ({ beforeEach: cb => guards.push(cb), afterEach: cb => failures.push(cb) })
  globalThis.watch = (_ref, cb) => { watchError = cb }
  globalThis.requestAnimationFrame = callback => { frames.set(++frameId, callback); return frameId }
  globalThis.cancelAnimationFrame = id => frames.delete(id)
  setupPlugin({ hook: (name, callback) => hooks.set(name, callback) })
  const flush = async () => {
    for (const callback of frames.values()) callback()
    frames.clear()
    for (let i = 0; i < 5; i++) await Promise.resolve()
  }
  return {
    scripts, hooks, flush,
    google: () => (window.dataLayer || []).map(args => Array.from(args)),
    yandex: () => (window.ym?.a || []).map(args => Array.from(args)),
    async go(path) {
      guards.forEach(guard => guard({ fullPath: path }))
      window.location = new URL(path, site)
      hooks.get('page:finish')?.()
      await flush()
    },
    fail() { errors.value = { statusCode: 404 }; watchError?.(errors.value); hooks.get('app:error')?.() },
  }
}

let browser = browserFixture('http://localhost:3000/hy')
assert.equal(browser.hooks.size, 0, 'Preview hosts must not install tracking')
browser = browserFixture(`${site}/hy`, '', '')
assert.equal(browser.hooks.size, 0, 'Blank IDs must not install tracking')
browser = browserFixture(`${site}/hy/account?reset_token=secret&email=private@example.com`)
browser.hooks.get('app:mounted')()
await browser.flush()
assert.equal(browser.scripts.length, 0, 'Direct account visits must not load tracking scripts')
await browser.go('/hy?search=private@example.com#secret')
browser.hooks.get('app:mounted')()
await browser.flush()
assert.equal(browser.scripts.length, 2)
assert.ok(browser.scripts.every(script => script.async && script.referrerPolicy === 'no-referrer'))
assert.equal(browser.google().filter(command => command[0] === 'event').length, 1)
assert.equal(browser.yandex().filter(command => command[1] === 'hit').length, 1)
const googleConfig = browser.google().find(command => command[0] === 'config')[2]
assert.equal(googleConfig.send_page_view, false)
assert.equal(googleConfig.allow_google_signals, false)
const yandexConfig = browser.yandex().find(command => command[1] === 'init')[2]
assert.equal(yandexConfig.defer, true)
assert.equal(yandexConfig.url, `${site}/hy`, 'Deferred initialization must override the raw browser URL')
assert.equal(yandexConfig.referrer, `${site}/`, 'A missing referrer needs a nonempty override to prevent raw fallback')
assert.equal(browser.yandex().find(command => command[1] === 'hit')[3].referer, `${site}/`)
assert.equal(yandexConfig.ecommerce, 'govistaMetricaDataLayer')
assert.deepEqual(window.govistaMetricaDataLayer, [], 'The dedicated ecommerce layer must stay empty')
assert.notEqual(window.govistaMetricaDataLayer, window.dataLayer, 'Metrica must not consume the Google data layer')
for (const option of ['webvisor', 'clickmap', 'trackLinks', 'trackHash', 'accurateTrackBounce', 'childIframe']) assert.equal(yandexConfig[option], false)
await browser.go('/hy?search=different#private')
assert.equal(browser.google().filter(command => command[0] === 'event').length, 1)
await browser.go('/hy/tours?email=private@example.com')
assert.equal(browser.google().filter(command => command[0] === 'event').length, 2)
assert.equal(browser.yandex().filter(command => command[1] === 'hit').length, 2)
await browser.go('/hy/account?reset_token=secret&email=private@example.com')
assert.equal(window['ga-disable-G-TEST12345'], true)
assert.equal(browser.yandex().at(-1)[1], 'destruct')
await browser.go('/hy/tours')
assert.equal(window['ga-disable-G-TEST12345'], false)
assert.equal(browser.google().filter(command => command[0] === 'event').length, 3)
const resumedYandex = browser.yandex().filter(command => command[1] === 'init').at(-1)[2]
assert.equal(resumedYandex.url, `${site}/hy/tours`)
assert.equal(resumedYandex.referrer, `${site}/`, 'Private-page transitions must not fall back to document.referrer')
assert.equal(resumedYandex.ecommerce, 'govistaMetricaDataLayer')
assert.deepEqual(window.govistaMetricaDataLayer, [], 'Navigation must not push ecommerce events')
assert.equal(browser.scripts.length, 2, 'Returning must reuse each script')
browser.fail()
assert.equal(window['ga-disable-G-TEST12345'], true)
assert.equal(browser.yandex().at(-1)[1], 'destruct')
const payloads = JSON.stringify([...browser.google(), ...browser.yandex()])
for (const privateValue of ['private@example.com', 'reset_token', 'secret', '?', '#']) assert.ok(!payloads.includes(privateValue), `Tracker payload leaked ${privateValue}`)
console.log('Site analytics URL, privacy, navigation, deduplication and tracker integration checks passed.')
