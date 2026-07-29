<script setup>
const api = useGovistaApi()
const { locale } = useLocale()
const route = useRoute()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const legacyScope = computed(() => (
  route.path.endsWith('/tours') && ['domestic', 'international'].includes(String(route.query.scope))
    ? String(route.query.scope)
    : ''
))
const canonicalPath = computed(() => (
  legacyScope.value
    ? route.path.replace(/\/tours$/, `/${legacyScope.value === 'domestic' ? 'domestic-tours' : 'international-tours'}`)
    : route.path
))
const canonicalUrl = computed(() => `${siteUrl}${canonicalPath.value}`)
const localizedPath = (nextLocale) => canonicalPath.value.replace(/^\/(hy|ru|en)(?=\/|$)/, `/${nextLocale}`)
const shouldNoindex = computed(() => Boolean(
  route.query.scope || route.query.search || route.query.type || route.query.date || route.query.guests,
))

useHead(() => ({
  htmlAttrs: { lang: locale.value },
  link: [
    { rel: 'canonical', href: canonicalUrl.value },
    ...['hy', 'ru', 'en'].map((language) => ({
      rel: 'alternate',
      hreflang: language,
      href: `${siteUrl}${localizedPath(language)}`,
    })),
    { rel: 'alternate', hreflang: 'x-default', href: `${siteUrl}${localizedPath('hy')}` },
  ],
  meta: [
    { property: 'og:url', content: canonicalUrl.value },
    { property: 'og:locale', content: { hy: 'hy_AM', ru: 'ru_RU', en: 'en_US' }[locale.value] },
    ...Object.entries({ hy: 'hy_AM', ru: 'ru_RU', en: 'en_US' })
      .filter(([language]) => language !== locale.value)
      .map(([, content]) => ({ property: 'og:locale:alternate', content })),
    {
      name: 'robots',
      content: shouldNoindex.value
        ? 'noindex, follow'
        : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
    },
  ],
}))

const { data: globalData } = await useAsyncData(
  () => `global-content-${locale.value}`,
  () => api('/v1/home', { query: { locale: locale.value } }),
  { watch: [locale] },
)
</script>

<template>
  <div class="site-shell">
    <AppHeader :settings="globalData?.settings || {}" />
    <main>
      <slot />
    </main>
    <AppFooter :settings="globalData?.settings || {}" />
    <BookingDrawer />
  </div>
</template>
