import { spawn } from 'node:child_process'
import fs from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const runtimeDir = path.join(root, '.runtime')
const screenshotPath = path.join(runtimeDir, 'mobile-home-exact.png')
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const debugPort = Number(process.env.CHROME_DEBUG_PORT || 9225)
const productionPort = process.env.MOBILE_QA_PORT || '3100'
const targetUrl = process.env.MOBILE_QA_URL || `http://127.0.0.1:${productionPort}/hy`
const apiBase = process.env.MOBILE_QA_API || 'http://127.0.0.1:8010/api'

await fs.mkdir(runtimeDir, { recursive: true })
const profilePath = await fs.mkdtemp(path.join(runtimeDir, 'chrome-mobile-cdp-'))

const productionServer = spawn(process.execPath, ['.output/server/index.mjs'], {
  cwd: path.join(root, 'frontend'),
  env: {
    ...process.env,
    NODE_ENV: 'production',
    NITRO_HOST: '127.0.0.1',
    NITRO_PORT: productionPort,
    NUXT_PUBLIC_API_BASE: apiBase,
    NUXT_API_BASE_INTERNAL: apiBase,
    NUXT_PUBLIC_SITE_URL: `http://127.0.0.1:${productionPort}`,
  },
  stdio: ['ignore', 'ignore', 'pipe'],
})

let productionErrors = ''
productionServer.stderr.on('data', chunk => {
  productionErrors += chunk.toString()
})

async function waitForSite() {
  const startedAt = Date.now()

  while (Date.now() - startedAt < 30000) {
    if (productionServer.exitCode !== null) {
      throw new Error(`Production server exited early with code ${productionServer.exitCode}`)
    }

    try {
      const response = await fetch(targetUrl, {
        headers: { accept: 'text/html' },
        signal: AbortSignal.timeout(1000),
      })
      if (response.ok) {
        return
      }
    }
    catch {
      // The production server is still starting.
    }

    await new Promise(resolve => setTimeout(resolve, 200))
  }

  throw new Error('Production site did not become ready within 30 seconds')
}

await waitForSite()

const chrome = spawn(chromePath, [
  '--headless=new',
  '--disable-gpu',
  '--hide-scrollbars',
  '--no-first-run',
  '--no-default-browser-check',
  `--remote-debugging-port=${debugPort}`,
  `--user-data-dir=${profilePath}`,
  'about:blank',
], {
  stdio: ['ignore', 'ignore', 'pipe'],
})

let chromeErrors = ''
chrome.stderr.on('data', chunk => {
  chromeErrors += chunk.toString()
})

async function getDebugTarget() {
  const startedAt = Date.now()

  while (Date.now() - startedAt < 15000) {
    if (chrome.exitCode !== null) {
      throw new Error(`Chrome exited early with code ${chrome.exitCode}`)
    }

    try {
      const targets = await fetch(`http://127.0.0.1:${debugPort}/json/list`, {
        signal: AbortSignal.timeout(1000),
      }).then(response => response.json())
      const page = targets.find(target => target.type === 'page')

      if (page?.webSocketDebuggerUrl) {
        return page
      }
    }
    catch {
      // Chrome is still starting.
    }

    await new Promise(resolve => setTimeout(resolve, 150))
  }

  throw new Error('Chrome DevTools endpoint did not become ready')
}

try {
  const target = await getDebugTarget()
  const socket = new WebSocket(target.webSocketDebuggerUrl)
  let sequence = 0
  const pending = new Map()
  const eventWaiters = new Map()

  await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true })
    socket.addEventListener('error', reject, { once: true })
  })

  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data)

    if (message.id && pending.has(message.id)) {
      const { resolve, reject } = pending.get(message.id)
      pending.delete(message.id)

      if (message.error) {
        reject(new Error(message.error.message))
      }
      else {
        resolve(message.result)
      }
      return
    }

    const waiters = eventWaiters.get(message.method)
    if (waiters?.length) {
      eventWaiters.set(message.method, [])
      for (const resolve of waiters) {
        resolve(message.params)
      }
    }
  })

  const call = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence
    pending.set(id, { resolve, reject })
    socket.send(JSON.stringify({ id, method, params }))
  })

  const once = method => new Promise(resolve => {
    eventWaiters.set(method, [...(eventWaiters.get(method) || []), resolve])
  })

  await call('Page.enable')
  await call('Runtime.enable')
  await call('Emulation.setDeviceMetricsOverride', {
    width: 390,
    height: 844,
    deviceScaleFactor: 1,
    mobile: true,
    screenWidth: 390,
    screenHeight: 844,
    screenOrientation: { angle: 0, type: 'portraitPrimary' },
  })

  const loaded = once('Page.loadEventFired')
  await call('Page.navigate', { url: targetUrl })
  await loaded
  await new Promise(resolve => setTimeout(resolve, 1000))
  await call('Runtime.evaluate', { expression: 'window.scrollTo(0, 0)' })

  const metricsResult = await call('Runtime.evaluate', {
    expression: `(() => {
      const html = document.documentElement
      const title = document.querySelector('.hero h1')?.getBoundingClientRect()
      const header = document.querySelector('.header-inner')?.getBoundingClientRect()
      const search = document.querySelector('.hero-search')?.getBoundingClientRect()
      const rect = value => value ? ({
        left: Math.round(value.left),
        right: Math.round(value.right),
        width: Math.round(value.width),
      }) : null
      const overflowers = [...document.body.querySelectorAll('*')]
        .map(element => {
          const bounds = element.getBoundingClientRect()
          return {
            node: element.tagName.toLowerCase(),
            className: typeof element.className === 'string' ? element.className : '',
            parentClass: typeof element.parentElement?.className === 'string' ? element.parentElement.className : '',
            text: element.textContent?.trim().replace(/\s+/g, ' ').slice(0, 80) || '',
            left: Math.round(bounds.left),
            right: Math.round(bounds.right),
            width: Math.round(bounds.width),
          }
        })
        .filter(element => element.right > html.clientWidth + 1 || element.left < -1)
        .sort((a, b) => b.right - a.right)
        .slice(0, 12)

      return {
        innerWidth,
        scrollX,
        visualOffsetLeft: visualViewport?.offsetLeft ?? null,
        clientWidth: html.clientWidth,
        scrollWidth: html.scrollWidth,
        bodyOverflowX: getComputedStyle(document.body).overflowX,
        title: rect(title),
        header: rect(header),
        search: rect(search),
        heading: document.querySelector('.hero h1')?.textContent?.trim(),
        overflowers,
      }
    })()`,
    returnByValue: true,
  })

  const screenshot = await call('Page.captureScreenshot', {
    format: 'png',
    clip: { x: 0, y: 0, width: 390, height: 844, scale: 1 },
    captureBeyondViewport: false,
    fromSurface: true,
  })
  await fs.writeFile(screenshotPath, Buffer.from(screenshot.data, 'base64'))
  socket.close()

  const metrics = metricsResult.result.value
  const failures = []

  if (metrics.clientWidth !== 390 || Math.abs(metrics.innerWidth - metrics.clientWidth) > 2) {
    failures.push(`Viewport is ${metrics.innerWidth}/${metrics.clientWidth}px instead of 390px`)
  }
  if (metrics.scrollWidth - metrics.clientWidth > 2 || !['hidden', 'clip'].includes(metrics.bodyOverflowX)) {
    failures.push(`Page has horizontal overflow: ${metrics.scrollWidth}px > ${metrics.clientWidth}px`)
  }
  for (const [name, rect] of [['hero title', metrics.title], ['header', metrics.header], ['search', metrics.search]]) {
    if (rect && (rect.left < 0 || rect.right > metrics.clientWidth)) {
      failures.push(`${name} escapes the viewport: ${JSON.stringify(rect)}`)
    }
  }

  console.log(JSON.stringify({ screenshotPath, metrics, failures }, null, 2))
  if (failures.length) {
    process.exitCode = 1
  }
}
catch (error) {
  console.error(error instanceof Error ? error.message : String(error))
  if (productionErrors.trim()) {
    console.error(productionErrors.trim())
  }
  if (chromeErrors.trim()) {
    console.error(chromeErrors.trim())
  }
  process.exitCode = 1
}
finally {
  const stopChild = child => child.exitCode !== null
    ? Promise.resolve()
    : Promise.race([
        new Promise(resolve => {
          child.once('exit', resolve)
          child.kill()
        }),
        new Promise(resolve => setTimeout(resolve, 2000)),
      ])

  await Promise.all([stopChild(chrome), stopChild(productionServer)])
  await fs.rm(profilePath, { recursive: true, force: true }).catch(() => {})
}
