// Read-only checks against the locally running compiled frontend and Laravel API.
const site = process.env.TRAVEL_SITE_URL || 'http://127.0.0.1:3000'
const api = process.env.TRAVEL_API_URL || 'http://127.0.0.1:8010/api'
let checks = 0
const assert = (condition, message) => { if (!condition) throw new Error(message); checks++ }
const services = ['tours', 'hotels', 'flights', 'cars', 'transfers', 'activities', 'places', 'packages']
for (const locale of ['hy', 'ru', 'en']) {
  for (const path of ['travel', 'account', ...services.map(service => `travel/${service}`)]) {
    const response = await fetch(`${site}/${locale}/${path}`)
    assert(response.ok, `${locale}/${path}: ${response.status}`)
    const html = await response.text()
    assert(html.includes('<h1'), `${locale}/${path}: missing title`)
    assert(html.includes(`lang="${locale}"`), `${locale}/${path}: incorrect language`)
    const canonical = html.match(/rel="canonical"[^>]*href="([^"]+)"/)
    assert(canonical && new URL(canonical[1]).pathname === `/${locale}/${path}`, `${locale}/${path}: missing canonical`)
    if (path !== 'travel') assert(/name="robots"[^>]*content="noindex/.test(html), `${locale}/${path}: must be noindex`)
    assert(!html.includes('statusCode":500'), `${locale}/${path}: server error`)
  }
}
const serviceResponse = await fetch(`${api}/v1/travel/services`)
assert(serviceResponse.ok, 'Travel services endpoint')
const catalog = await serviceResponse.json()
assert(catalog.services.length === 8, 'All eight service categories')
assert(catalog.payments_enabled === false, 'No payment claims before activation')
const account = await fetch(`${api}/v1/account/orders`, { headers: { Accept: 'application/json' } })
assert(account.status === 401, 'Customer orders require authentication')
const unknown = await fetch(`${site}/hy/travel/not-a-service`)
assert(unknown.status === 404, 'Unknown service must be 404')
console.log(`Travel smoke: ${checks} checks passed.`)
