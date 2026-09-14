// Read-only checks. Never starts a supplier search, imports offers, or creates bookings.
import assert from 'node:assert/strict'
const site = process.env.TRAVEL_SITE_URL || 'http://127.0.0.1:3000'
const api = process.env.TRAVEL_API_URL || 'http://127.0.0.1:8010/api'
let checks = 0
const check = (value, message) => { assert.ok(value, message); checks++ }
const options = await fetch(`${api}/v1/packages/options`).then(r => r.json())
check(options.providers.length === 8, 'Eight package suppliers')
check(options.destinations.length === 12, 'Twelve destinations')
check(options.payments_enabled === false, 'Payment is not claimed')
check(!JSON.stringify(options).match(/api_key|contract_reference|contact_email|credentials/), 'Supplier details stay private')
for (const locale of ['hy', 'ru', 'en']) {
  const response = await fetch(`${site}/${locale}/travel/packages`)
  check(response.ok, `${locale} package page`)
  const html = await response.text()
  check(html.includes('package-hero'), `${locale} dedicated package design`)
  check(html.includes('package-search'), `${locale} search form`)
  for (const name of ['ANRIVA', 'Maratuk', 'World Voyage', 'TravelOne Armenia', 'TEZ TOUR', 'Tourvisor', 'Sletat', 'TBO Packages']) check(html.includes(name), `${locale} ${name}`)
  const result = await fetch(`${api}/v1/packages/offers?locale=${locale}&origin=EVN&adults=2`).then(r => r.json())
  check(Array.isArray(result.data) && result.meta.total >= 0, `${locale} catalogue response`)
  check(!JSON.stringify(result).match(/supplier_reference|internal_notes|"cost"/), `${locale} private pricing hidden`)
  for (const item of result.data) {
    const detail = await fetch(`${api}/v1/packages/offers/${item.id}?locale=${locale}`)
    check(detail.ok, `${locale} published offer details`)
    check(detail.headers.get('cache-control')?.includes('no-store'), 'Offer prices are not cached by browsers')
  }
}
for (const path of ['package-offers', 'package-offers/template', 'providers']) {
  const response = await fetch(`${api}/admin/${path}`, { headers: { Accept: 'application/json' } })
  check(response.status === 401, `${path} requires admin access`)
}
const unknown = await fetch(`${api}/v1/packages/offers/2147483647?locale=en`, { headers: { Accept: 'application/json' } })
check(unknown.status === 404, 'Unknown offer has no invented fallback')
console.log(`Package smoke: ${checks} checks passed.`)
