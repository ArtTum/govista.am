<script setup>
import { ArrowLeft, MapPin } from '@lucide/vue'

const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const { openBooking } = useBooking()
const route = useRoute()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const absoluteImage = value => value?.startsWith('/') ? `${siteUrl}${value}` : value
const { data, error } = await useAsyncData(
  () => `destination-${locale.value}-${route.params.slug}`,
  () => api(`/v1/destinations/${route.params.slug}`, { query: { locale: locale.value } }),
  { watch: [locale] },
)
useContentError(error)
useSeoMeta({
  title: () => `${data.value?.title || ''} — GoVista`,
  description: () => data.value?.description,
  ogTitle: () => `${data.value?.title || ''} — GoVista`,
  ogDescription: () => data.value?.description,
  ogType: 'website',
  ogImage: () => absoluteImage(data.value?.image),
  twitterCard: 'summary_large_image',
  twitterTitle: () => `${data.value?.title || ''} — GoVista`,
  twitterDescription: () => data.value?.description,
  twitterImage: () => absoluteImage(data.value?.image),
})
useHead(() => ({
  script: [{
    key: 'destination-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'TouristDestination',
          name: data.value?.title,
          description: data.value?.description,
          image: [data.value?.image, ...(data.value?.gallery || [])].filter(Boolean).map(absoluteImage),
          address: { '@type': 'PostalAddress', addressRegion: data.value?.region, addressCountry: 'AM' },
        },
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
            { '@type': 'ListItem', position: 2, name: t('nav.destinations'), item: `${siteUrl}${localePath('/destinations')}` },
            { '@type': 'ListItem', position: 3, name: data.value?.title, item: `${siteUrl}${localePath(`/destinations/${data.value?.slug}`)}` },
          ],
        },
      ],
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div v-if="data" class="destination-detail-page">
    <section class="destination-detail-hero" :style="{ backgroundImage: `linear-gradient(90deg, rgba(5,20,32,.78), rgba(5,20,32,.1)), url('${data.image}')` }">
      <div class="container">
        <NuxtLink :to="localePath('/destinations')" class="back-link"><ArrowLeft :size="17" />{{ t('nav.destinations') }}</NuxtLink>
        <p><MapPin :size="16" />{{ data.region }}</p>
        <h1>{{ data.title }}</h1>
      </div>
    </section>
    <section class="section destination-detail-copy">
      <div class="container">
        <p class="section-eyebrow">Explore deeper</p>
        <h2>{{ data.title }}</h2>
        <p>{{ data.description }}</p>
        <button class="primary-cta" @click="openBooking({ title: data.title, type: 'custom' })">{{ t('common.book') }}</button>
      </div>
    </section>
  </div>
</template>
