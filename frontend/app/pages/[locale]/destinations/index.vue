<script setup>
import { ArrowUpRight, MapPinned } from '@lucide/vue'

const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const route = useRoute()
const page = computed(() => Math.max(1, Number.parseInt(String(route.query.page || '1'), 10) || 1))
const { data, status, error, refresh } = await useAsyncData(
  () => `destinations-${locale.value}-${page.value}`,
  () => api('/v1/destinations', { query: { locale: locale.value, per_page: 12, page: page.value } }),
  { watch: [locale] },
)
const destinations = computed(() => data.value?.data || [])
const seoDescription = computed(() => locale.value === 'hy'
  ? 'Բացահայտեք Հայաստանի լավագույն ուղղությունները, տեսարժան վայրերն ու GoVista-ի ընտրված տուրերը։'
  : locale.value === 'ru'
    ? 'Откройте лучшие направления и достопримечательности Армении с авторскими маршрутами GoVista.'
    : 'Discover Armenia’s best destinations, landmarks and thoughtfully curated GoVista journeys.')
useSeoMeta({
  title: () => `${t('nav.destinations')} — GoVista`,
  description: () => seoDescription.value,
  ogTitle: () => `${t('nav.destinations')} — GoVista`,
  ogDescription: () => seoDescription.value,
  ogType: 'website',
  twitterCard: 'summary_large_image',
})
useHead(() => ({
  script: [{
    key: 'destinations-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
            { '@type': 'ListItem', position: 2, name: t('nav.destinations'), item: `${siteUrl}${localePath('/destinations')}` },
          ],
        },
        {
          '@type': 'ItemList',
          numberOfItems: destinations.value.length,
          itemListElement: destinations.value.map((item, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            name: item.title,
            url: `${siteUrl}${localePath(`/destinations/${item.slug}`)}`,
          })),
        },
      ],
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div>
    <section class="page-hero destinations-page-hero">
      <div class="container"><p class="section-eyebrow">Explore Armenia</p><h1>{{ t('home.destinationsTitle') }}</h1><p>{{ locale === 'hy' ? 'Հին քաղաքներ, կապույտ լիճ, անտառներ և քարե լեռներ՝ մեկ փոքր, անսահման բազմազան երկրում։' : locale === 'ru' ? 'Древние города, голубое озеро, леса и каменные горы в одной небольшой, бесконечно разнообразной стране.' : 'Ancient cities, a blue lake, forests and stone mountains in one small, endlessly diverse country.' }}</p></div>
    </section>
    <section id="catalog" class="section">
      <div class="container"><CollectionState :status="status" :error="error" :empty="!destinations.length" @retry="refresh" /></div>
      <div v-if="!error && status !== 'pending'" class="container destination-listing">
        <NuxtLink v-for="(destination, index) in destinations" :key="destination.id" v-reveal="index * 70" :to="localePath(`/destinations/${destination.slug}`)" class="destination-feature-card">
          <img :src="destination.image" :alt="destination.title" width="1200" height="900" loading="lazy" decoding="async">
          <div class="destination-feature-overlay"></div>
          <span><MapPinned :size="15" />{{ destination.region }}</span>
          <div><h2>{{ destination.title }}</h2><p>{{ destination.description }}</p></div>
          <i><ArrowUpRight :size="21" /></i>
        </NuxtLink>
      </div>
      <div class="container"><CatalogPagination v-if="!error" :meta="data?.meta" /></div>
    </section>
  </div>
</template>
