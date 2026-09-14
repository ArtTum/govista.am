import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { spawn } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

// Isolated fixture API: no real bookings, content, or credentials are touched.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
let outage = false
const fixtureApi = createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost')
  response.setHeader('Content-Type', 'application/json')
  if (outage) { response.writeHead(503); response.end('{}'); return }
  if (url.pathname === '/v1/settings') { response.end('{"site_name":"GoVista"}'); return }
  if (url.pathname === '/v1/services') { response.end('[]'); return }
  if (url.pathname === '/v1/home' || /\/v1\/(tours|posts|services|pages)\/.+/.test(url.pathname)) {
    response.writeHead(404); response.end('{}'); return
  }
  const resource = url.pathname.split('/').at(-1)
  const total = ['tours', 'pages'].includes(resource) ? 55 : 0
  const page = Number(url.searchParams.get('page') || 1)
  const perPage = Number(url.searchParams.get('per_page') || 48)
  const data = Array.from({ length: total }, (_, i) => ({ id: i + 1, slug: `audit-${resource}-${i + 1}`, updated_at: '2026-09-01T10:00:00Z' })).slice((page - 1) * perPage, page * perPage)
  response.end(JSON.stringify({ data, meta: { current_page: page, last_page: Math.max(1, Math.ceil(total / perPage)), total } }))
})
await new Promise(resolve => fixtureApi.listen(0, '127.0.0.1', resolve))
const apiBase = `http://127.0.0.1:${fixtureApi.address().port}`
const port = Number(process.env.AUDIT_SMOKE_PORT || 3112)
const origin = `http://127.0.0.1:${port}`
const server = spawn(process.execPath, ['.output/server/index.mjs'], {
  cwd: path.join(root, 'frontend'), windowsHide: true,
  env: { ...process.env, NITRO_HOST: '127.0.0.1', NITRO_PORT: String(port), NUXT_API_BASE_INTERNAL: apiBase, NUXT_PUBLIC_API_BASE: apiBase, NUXT_PUBLIC_SITE_URL: origin },
  stdio: ['ignore', 'ignore', 'pipe'],
})
let errors = ''
server.stderr.on('data', chunk => { errors += chunk.toString() })
const request = pathname => fetch(origin + pathname, { redirect: 'manual', signal: AbortSignal.timeout(15000) })
let checks = 0
const check = (value, message) => { assert.ok(value, message); checks++ }
try {
  const deadline = Date.now() + 30000
  while (true) {
    try { await request('/robots.txt'); break } catch {
      if (server.exitCode !== null || Date.now() > deadline) throw new Error('Audit server did not start: ' + errors)
      await new Promise(resolve => setTimeout(resolve, 150))
    }
  }
  const sitemap = await request('/sitemap.xml')
  check(sitemap.status === 200, 'Healthy sitemap should be available')
  const xml = await sitemap.text()
  for (const locale of ['hy', 'ru', 'en']) {
    check(xml.includes(`/${locale}/tours/audit-tours-55</loc>`), `${locale}: tour beyond the first 48 was omitted`)
    check(xml.includes(`/${locale}/audit-pages-55</loc>`), `${locale}: dynamic page beyond the first 48 was omitted`)
  }
  check(!xml.includes('/en/about</loc>'), 'Inactive/unavailable static content should not appear in sitemap')
  const redirect = await request('/de/tours?search=Armenia')
  check(redirect.status === 301 && redirect.headers.get('location') === '/hy/tours?search=Armenia', 'Invalid locales should redirect while keeping search')
  check((await request('/en/tours/missing')).status === 404, 'Missing content should be 404')
  outage = true
  const failedSitemap = await request('/sitemap.xml')
  check(failedSitemap.status === 503, 'API outage must not publish an incomplete sitemap')
  check(failedSitemap.headers.get('cache-control')?.includes('no-store'), 'Failed sitemap must not be cached')
  check((await request('/en')).status === 503, 'Homepage API outage should be 503')
  check((await request('/en/tours/missing')).status === 503, 'API outage must not be mistaken for missing content')
  console.log(`Audit smoke: ${checks} checks passed.`)
} finally {
  server.kill()
  fixtureApi.closeAllConnections()
  await new Promise(resolve => fixtureApi.close(resolve))
}
