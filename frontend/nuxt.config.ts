// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2026-07-18',
  devtools: { enabled: process.env.NODE_ENV !== 'production' },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    apiBaseInternal: process.env.NUXT_API_BASE_INTERNAL
      || process.env.NUXT_PUBLIC_API_BASE
      || 'http://127.0.0.1:8000/api',
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8000/api',
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL || 'https://govista.am',
    },
  },
  app: {
    head: {
      htmlAttrs: { lang: 'hy' },
      title: 'GoVista — Տուրեր Հայաստանում և արտերկրում',
      meta: [
        { name: 'description', content: 'Ներքին և արտաքին տուրեր GoVista-ից՝ խմբային, անհատական և ամբողջական ճամփորդական փաթեթներով։' },
        { name: 'theme-color', content: '#0b1f33' },
        { property: 'og:type', content: 'website' },
        { property: 'og:site_name', content: 'GoVista' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'apple-touch-icon', href: '/brand/govista-mark.png' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Armenian:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;1,500&display=swap' },
      ],
    },
  },
  nitro: {
    compressPublicAssets: true,
  },
  routeRules: {
    '/': { redirect: '/hy' },
    '/**': {
      headers: {
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'SAMEORIGIN',
        'Referrer-Policy': 'strict-origin-when-cross-origin',
        'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
      },
    },
  },
})
