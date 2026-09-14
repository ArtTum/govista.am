<script setup>
import { ArrowRight, Clock3 } from '@lucide/vue'

const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const route = useRoute()
const page = computed(() => Math.max(1, Number.parseInt(String(route.query.page || '1'), 10) || 1))
const { data, status, error, refresh } = await useAsyncData(
  () => `posts-${locale.value}-${page.value}`,
  () => api('/v1/posts', { query: { locale: locale.value, per_page: 12, page: page.value } }),
  { watch: [locale] },
)
const posts = computed(() => data.value?.data || [])
const seoDescription = computed(() => locale.value === 'hy'
  ? 'Հայաստանի և ճանապարհորդության օգտակար ուղեցույցներ, տեղական պատմություններ ու խորհուրդներ GoVista-ից։'
  : locale.value === 'ru'
    ? 'Полезные путеводители по Армении, местные истории и советы для путешественников от GoVista.'
    : 'Useful Armenia travel guides, local stories and practical advice from the GoVista team.')
useSeoMeta({
  title: () => `${t('nav.blog')} — GoVista`,
  description: () => seoDescription.value,
  ogTitle: () => `${t('nav.blog')} — GoVista`,
  ogDescription: () => seoDescription.value,
  ogType: 'website',
  twitterCard: 'summary_large_image',
})
useHead(() => ({
  script: [{
    key: 'blog-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'Blog',
          name: 'GoVista Journal',
          url: `${siteUrl}${localePath('/blog')}`,
          blogPost: posts.value.map(post => ({
            '@type': 'BlogPosting',
            headline: post.title,
            url: `${siteUrl}${localePath(`/blog/${post.slug}`)}`,
            datePublished: post.published_at,
          })),
        },
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
            { '@type': 'ListItem', position: 2, name: t('nav.blog'), item: `${siteUrl}${localePath('/blog')}` },
          ],
        },
      ],
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div>
    <section class="page-hero journal-page-hero">
      <div class="container"><p class="section-eyebrow">GoVista Journal</p><h1>{{ t('home.journalTitle') }}</h1><p>{{ locale === 'hy' ? 'Տեղական պատմություններ, խորհուրդներ և փոքր գաղտնիքներ՝ ձեր ճանապարհորդությունից առաջ։' : locale === 'ru' ? 'Местные истории, советы и маленькие секреты перед путешествием.' : 'Local stories, useful advice and small secrets before your journey.' }}</p></div>
    </section>
    <section id="catalog" class="section">
      <div class="container"><CollectionState :status="status" :error="error" :empty="!posts.length" @retry="refresh" /></div>
      <div v-if="!error && status !== 'pending'" class="container blog-listing">
        <article v-for="(post, index) in posts" :key="post.id" v-reveal="index * 70" class="blog-list-card">
          <NuxtLink :to="localePath(`/blog/${post.slug}`)"><img :src="post.image" :alt="post.title" width="1200" height="800" loading="lazy" decoding="async"></NuxtLink>
          <div><span>{{ post.category }}</span><h2><NuxtLink :to="localePath(`/blog/${post.slug}`)">{{ post.title }}</NuxtLink></h2><p>{{ post.excerpt }}</p><footer><small><Clock3 :size="14" />{{ post.reading_time }} {{ t('ui.minute') }}</small><NuxtLink :to="localePath(`/blog/${post.slug}`)">{{ t('common.readMore') }} <ArrowRight :size="16" /></NuxtLink></footer></div>
        </article>
      </div>
      <div class="container"><CatalogPagination v-if="!error" :meta="data?.meta" /></div>
    </section>
  </div>
</template>
