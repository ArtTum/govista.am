<script setup>
import { ArrowRight } from '@lucide/vue'

const api = useGovistaApi()
const { locale, t } = useLocale()
const { openBooking } = useBooking()
const route = useRoute()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const absoluteImage = value => value?.startsWith('/') ? `${siteUrl}${value}` : value
const { data, error } = await useAsyncData(
  () => `page-${locale.value}-${route.params.page}`,
  () => api(`/v1/pages/${route.params.page}`, { query: { locale: locale.value } }),
  { watch: [locale] },
)
useContentError(error)
useSeoMeta({
  title: () => data.value?.seo_title || `${data.value?.title || ''} — GoVista`,
  description: () => data.value?.seo_description || data.value?.content,
  ogTitle: () => data.value?.seo_title || `${data.value?.title || ''} — GoVista`,
  ogDescription: () => data.value?.seo_description || data.value?.content,
  ogType: 'website',
  ogImage: () => absoluteImage(data.value?.image),
  twitterCard: 'summary_large_image',
})
useHead(() => ({
  script: [{
    key: 'page-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'WebPage',
      name: data.value?.title,
      description: data.value?.seo_description || data.value?.content,
      url: `${siteUrl}/${locale.value}/${data.value?.slug}`,
      inLanguage: locale.value,
      isPartOf: { '@id': `${siteUrl}/#website` },
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div v-if="data" class="content-page">
    <section class="page-hero" :style="data.image ? { backgroundImage: `linear-gradient(90deg, rgba(7,25,39,.88), rgba(7,25,39,.45)), url('${data.image}')` } : {}">
      <div class="container"><p class="section-eyebrow">GoVista</p><h1>{{ data.title }}</h1></div>
    </section>
    <section class="section">
      <div class="container prose-content">
        <p v-for="(paragraph, index) in String(data.content).split(/\r?\n/)" :key="`${index}-${paragraph}`">{{ paragraph }}</p>
        <button v-if="route.params.page === 'about'" class="primary-cta" @click="openBooking()">{{ t('common.book') }} <ArrowRight :size="18" /></button>
      </div>
    </section>
  </div>
</template>
