import { spawn } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const frontendRoot = path.join(projectRoot, 'frontend')
const port = process.env.PRODUCTION_SMOKE_PORT || '3100'
const origin = `http://127.0.0.1:${port}`
const apiBase = process.env.PRODUCTION_SMOKE_API || 'http://127.0.0.1:8010/api'

const locales = ['hy', 'ru', 'en']
const staticPaths = ['', '/tours', '/domestic-tours', '/international-tours', '/stays', '/cars', '/destinations', '/blog', '/about', '/privacy', '/terms', '/photo-credits']
const tours = [
  'garni-geghard-symphony',
  'khor-virap-noravank-areni',
  'sevan-dilijan-forest',
  'tatev-wings-private',
  'armenia-seven-day-signature',
  'gyumri-cultural-day',
  'lori-monasteries-canyon',
  'jermuk-waterfall-wellness',
  'aragats-amberd-adventure',
  'echmiadzin-zvartnots',
  'khndzoresk-cave-city',
  'lake-arpi-north',
  'dubai-city-desert',
  'istanbul-bosphorus-weekend',
  'tbilisi-kakheti-wine',
  'paris-romantic-escape',
  'rome-eternal-city',
  'athens-santorini',
  'cairo-hurghada',
  'maldives-island-escape',
  'prague-vienna-budapest',
  'barcelona-costa-brava',
  'cyprus-sun-sea',
  'montenegro-adriatic',
]
const destinations = ['yerevan', 'lake-sevan', 'dilijan', 'tatev', 'areni', 'garni']
const posts = ['first-time-armenia-guide', 'best-season-armenia', 'armenian-flavors']
const stays = ['handpicked-hotels', 'cascade-view-apartment', 'dilijan-forest-cottage', 'sevan-lake-house']
const cars = ['airport-transfers', 'toyota-rav4-rental', 'mercedes-vito-rental', 'economy-city-car']

const localizedPaths = [
  ...staticPaths,
  ...tours.map(slug => `/tours/${slug}`),
  ...destinations.map(slug => `/destinations/${slug}`),
  ...posts.map(slug => `/blog/${slug}`),
  ...stays.map(slug => `/stays/${slug}`),
  ...cars.map(slug => `/cars/${slug}`),
]

let passed = 0
const failures = []

function check(condition, message) {
  if (condition) {
    passed += 1
    return
  }

  failures.push(message)
}

async function request(url, options = {}) {
  const response = await fetch(url, {
    redirect: 'manual',
    signal: AbortSignal.timeout(15000),
    headers: {
      accept: 'text/html,application/xhtml+xml',
      ...options.headers,
    },
    ...options,
  })
  const body = await response.text()

  return { response, body }
}

async function waitUntilReady(child) {
  const startedAt = Date.now()

  while (Date.now() - startedAt < 30000) {
    if (child.exitCode !== null) {
      throw new Error(`Production server exited early with code ${child.exitCode}`)
    }

    try {
      const { response } = await request(`${origin}/hy`)
      if (response.status === 200) {
        return
      }
    }
    catch {
      // The server is still starting.
    }

    await new Promise(resolve => setTimeout(resolve, 250))
  }

  throw new Error('Production server did not become ready within 30 seconds')
}

const server = spawn(process.execPath, ['.output/server/index.mjs'], {
  cwd: frontendRoot,
  env: {
    ...process.env,
    NODE_ENV: 'production',
    NITRO_HOST: '127.0.0.1',
    NITRO_PORT: port,
    NUXT_PUBLIC_API_BASE: apiBase,
    NUXT_API_BASE_INTERNAL: apiBase,
    NUXT_PUBLIC_SITE_URL: origin,
  },
  stdio: ['ignore', 'pipe', 'pipe'],
})

let serverErrors = ''
server.stderr.on('data', chunk => {
  serverErrors += chunk.toString()
})

try {
  await waitUntilReady(server)

  const root = await request(`${origin}/`)
  check(root.response.status === 307, `Root route must redirect with HTTP 307, got ${root.response.status}`)
  check(root.response.headers.get('location') === '/hy', 'Root route must redirect to /hy')

  const expectedUrls = []
  let representativeHtml = ''

  for (const locale of locales) {
    for (const routePath of localizedPaths) {
      const pathname = `/${locale}${routePath}`
      const url = `${origin}${pathname}`
      expectedUrls.push(url)

      const { response, body } = await request(url)
      check(response.status === 200, `${pathname} returned ${response.status}`)
      check(response.headers.get('content-type')?.includes('text/html'), `${pathname} is not HTML`)
      check(new RegExp(`<html[^>]+lang="${locale}"`).test(body), `${pathname} has the wrong document language`)
      check(body.includes(`rel="canonical" href="${url}"`) || body.includes(`rel="canonical" href="${url.replace('&', '&amp;')}"`), `${pathname} has no exact canonical URL`)
      check(body.includes('hreflang="hy"'), `${pathname} has no Armenian hreflang`)
      check(body.includes('hreflang="ru"'), `${pathname} has no Russian hreflang`)
      check(body.includes('hreflang="en"'), `${pathname} has no English hreflang`)
      check(body.includes('hreflang="x-default"'), `${pathname} has no x-default hreflang`)
      check(!body.includes('localhost:3000'), `${pathname} leaked a development hostname`)

      if (pathname === '/hy') {
        representativeHtml = body
        check(body.includes('"@type":"TravelAgency"'), 'Homepage has no TravelAgency structured data')
        check(body.includes('"@type":"WebSite"'), 'Homepage has no WebSite structured data')
      }
    }
  }

  const sitemap = await request(`${origin}/sitemap.xml`)
  check(sitemap.response.status === 200, `Sitemap returned ${sitemap.response.status}`)
  check(sitemap.response.headers.get('content-type')?.includes('application/xml'), 'Sitemap content type is not XML')
  const sitemapCount = (sitemap.body.match(/<url>/g) || []).length
  check(sitemapCount === expectedUrls.length, `Sitemap contains ${sitemapCount} URLs instead of ${expectedUrls.length}`)
  for (const url of expectedUrls) {
    check(sitemap.body.includes(`<loc>${url}</loc>`), `Sitemap is missing ${url}`)
  }

  const robots = await request(`${origin}/robots.txt`)
  check(robots.response.status === 200, `robots.txt returned ${robots.response.status}`)
  check(robots.body.includes('Disallow: /admin'), 'robots.txt does not protect the admin path')
  check(robots.body.includes('Sitemap: https://govista.am/sitemap.xml'), 'robots.txt has the wrong sitemap URL')

  const missing = await request(`${origin}/en/this-route-does-not-exist`)
  check(missing.response.status === 404, `Missing route returned ${missing.response.status}`)
  check(missing.body.includes('This path is not on the map yet'), 'Custom English 404 copy is missing')
  check(missing.body.includes('noindex, nofollow'), '404 route is indexable')

  const home = await request(`${origin}/hy`)
  check(home.response.headers.get('x-content-type-options') === 'nosniff', 'Nuxt response has no nosniff header')
  check(home.response.headers.get('x-frame-options') === 'SAMEORIGIN', 'Nuxt response has no SAMEORIGIN header')
  check(home.response.headers.get('referrer-policy') === 'strict-origin-when-cross-origin', 'Nuxt response has the wrong referrer policy')

  const assetPaths = new Set(
    [...representativeHtml.matchAll(/(?:src|href)="(\/(?:_nuxt|brand|images)\/[^"#?]+)[^"]*"/g)]
      .map(match => match[1]),
  )
  check(assetPaths.size > 0, 'No production assets were discovered in the homepage HTML')

  for (const assetPath of assetPaths) {
    const asset = await request(`${origin}${assetPath}`)
    check(asset.response.status === 200, `Asset ${assetPath} returned ${asset.response.status}`)
    check(Number(asset.response.headers.get('content-length') || asset.body.length) > 0, `Asset ${assetPath} is empty`)
  }

  console.log(`Production smoke: ${passed} checks passed; ${failures.length} failed.`)

  if (failures.length > 0) {
    for (const failure of failures) {
      console.error(`- ${failure}`)
    }
    process.exitCode = 1
  }
}
catch (error) {
  console.error(error instanceof Error ? error.message : String(error))
  if (serverErrors.trim()) {
    console.error(serverErrors.trim())
  }
  process.exitCode = 1
}
finally {
  server.kill()
}
