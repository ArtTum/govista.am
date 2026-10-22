import { readFile } from 'node:fs/promises'

const origin = 'https://govista.am'
const endpoint = 'https://yandex.com/indexnow'
const key = '9362af3e1c8f10ef89db21efb643ec18'
const keyLocation = `${origin}/${key}.txt`
const usage = `Usage (Node 24):
  node scripts/indexnow-submit.mjs https://govista.am/hy [more URLs]
  node scripts/indexnow-submit.mjs --sitemap
Add --submit to send the reviewed URLs. Without it, this is a dry run.`

function validateUrl(value) {
  const url = new URL(value)
  const path = decodeURIComponent(url.pathname)
  if (url.origin !== origin || url.username || url.password
    || value !== `${origin}${url.pathname}` || url.pathname.endsWith('/')
    || !/^\/(hy|ru|en)(?:\/|$)/.test(path) || /[%\\\s?#]/.test(path) || path.includes('//')
    || /\/(account|admin|api|auth|login|logout|register|reset-password|forgot-password|password|private|internal|dashboard)(?:\/|$)/i.test(path)
    || /^\/(hy|ru|en)\/travel\//.test(path)) {
    throw new Error(`Not a canonical public localized URL: ${value}`)
  }
  return value
}

async function fetchText(url) {
  const response = await fetch(url, { redirect: 'error', signal: AbortSignal.timeout(15000) })
  if (response.status !== 200) throw new Error(`GET ${url} returned HTTP ${response.status}`)
  return response.text()
}

function sitemapUrls(xml) {
  if (!/<urlset\b/.test(xml) || !/<\/urlset>\s*$/.test(xml) || /<!DOCTYPE/i.test(xml)) {
    throw new Error('Expected a sitemap urlset, not an index or HTML response')
  }
  const entities = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" }
  const urls = [...xml.matchAll(/<loc>\s*([^<]+?)\s*<\/loc>/g)]
    .map(match => match[1].replace(/&(amp|lt|gt|quot|apos);/g, (_entity, name) => entities[name]))
  if (!urls.length || urls.length !== [...xml.matchAll(/<url>/g)].length) {
    throw new Error('Sitemap is empty or has incomplete URL entries')
  }
  return urls
}

async function main() {
  const args = process.argv.slice(2)
  if (args.length === 1 && args[0] === '--help') { console.log(usage); return }
  const submit = args.includes('--submit')
  const sitemap = args.includes('--sitemap')
  const urls = args.filter(arg => !['--submit', '--sitemap'].includes(arg))
  if (urls.some(arg => arg.startsWith('--')) || (sitemap && urls.length) || (!sitemap && !urls.length)) {
    throw new Error(usage)
  }
  const localKey = await readFile(new URL(`../frontend/public/${key}.txt`, import.meta.url), 'utf8')
  if (localKey.trim() !== key) throw new Error('Local IndexNow key file does not match the configured key')
  const selected = sitemap ? sitemapUrls(await fetchText(`${origin}/sitemap.xml`)) : urls
  const urlList = [...new Set(selected.map(validateUrl))]
  if (!urlList.length || urlList.length > 10000) throw new Error('Select between 1 and 10000 distinct URLs')
  const payload = { host: 'govista.am', key, keyLocation, urlList }
  if (!submit) {
    console.log(JSON.stringify({ mode: 'dry-run', endpoint, urlCount: urlList.length, payload }, null, 2))
    return
  }

  // Ownership must be verifiable on the live host before any notification POST.
  if ((await fetchText(keyLocation)).trim() !== key) {
    throw new Error(`Deployed key does not match: ${keyLocation}`)
  }
  const response = await fetch(endpoint, {
    method: 'POST', redirect: 'error', signal: AbortSignal.timeout(30000),
    headers: { 'Content-Type': 'application/json; charset=utf-8' },
    body: JSON.stringify(payload),
  })
  if (response.status === 200 || response.status === 202) {
    console.log(JSON.stringify({
      status: response.status,
      result: response.status === 200 ? 'submitted' : 'pending-key-verification',
      urlCount: urlList.length, keyLocation,
      note: response.status === 200
        ? 'URLs submitted. Indexing is not guaranteed.'
        : 'Request received; Yandex key verification is still pending. No automatic retry was made.',
    }, null, 2))
    if (response.status === 202) process.exitCode = 2
    return
  }
  const detail = (await response.text()).slice(0, 500)
  throw new Error(`IndexNow returned HTTP ${response.status}${detail ? `: ${detail}` : ''}`)
}

main().catch(error => {
  console.error(error.message)
  process.exitCode = 1
})
