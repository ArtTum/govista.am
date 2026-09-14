<script setup>
import { ArrowLeft, Clock3 } from '@lucide/vue'

const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const route = useRoute()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const absoluteImage = value => value?.startsWith('/') ? `${siteUrl}${value}` : value
const { data, error } = await useAsyncData(
  () => `post-${locale.value}-${route.params.slug}`,
  () => api(`/v1/posts/${route.params.slug}`, { query: { locale: locale.value } }),
  { watch: [locale] },
)
useContentError(error)
useSeoMeta({
  title: () => `${data.value?.title || ''} — GoVista Journal`,
  description: () => data.value?.excerpt,
  ogTitle: () => `${data.value?.title || ''} — GoVista Journal`,
  ogDescription: () => data.value?.excerpt,
  ogType: 'article',
  ogImage: () => absoluteImage(data.value?.image),
  articlePublishedTime: () => data.value?.published_at,
  articleModifiedTime: () => data.value?.updated_at,
  twitterCard: 'summary_large_image',
  twitterTitle: () => data.value?.title,
  twitterDescription: () => data.value?.excerpt,
  twitterImage: () => absoluteImage(data.value?.image),
})
useHead(() => ({
  script: [{
    key: 'article-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'BlogPosting',
          headline: data.value?.title,
          description: data.value?.excerpt,
          image: absoluteImage(data.value?.image),
          datePublished: data.value?.published_at,
          dateModified: data.value?.updated_at,
          inLanguage: locale.value,
          author: { '@type': 'Organization', name: 'GoVista', url: siteUrl },
          publisher: { '@type': 'Organization', '@id': `${siteUrl}/#organization`, name: 'GoVista' },
          mainEntityOfPage: `${siteUrl}${localePath(`/blog/${data.value?.slug}`)}`,
        },
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
            { '@type': 'ListItem', position: 2, name: t('nav.blog'), item: `${siteUrl}${localePath('/blog')}` },
            { '@type': 'ListItem', position: 3, name: data.value?.title, item: `${siteUrl}${localePath(`/blog/${data.value?.slug}`)}` },
          ],
        },
      ],
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <article v-if="data" class="article-page">
    <header class="article-header container">
      <NuxtLink :to="localePath('/blog')" class="back-link"><ArrowLeft :size="17" />{{ t('nav.blog') }}</NuxtLink>
      <span>{{ data.category }}</span>
      <h1>{{ data.title }}</h1>
      <p>{{ data.excerpt }}</p>
      <small><Clock3 :size="15" />{{ data.reading_time }} min</small>
    </header>
    <div class="article-cover container"><img :src="data.image" :alt="data.title" width="1400" height="900" decoding="async"></div>
    <div class="article-body container"><p v-for="paragraph in String(data.content).split('\\n')" :key="paragraph">{{ paragraph }}</p></div>
  </article>
</template>
