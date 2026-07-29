<script setup>
import { CalendarDays, Check, ChevronRight, Clock3, Globe2, MapPin, ShieldCheck, Star, Users, X } from '@lucide/vue'

const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const { openBooking } = useBooking()
const route = useRoute()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const absoluteImage = value => value?.startsWith('/') ? `${siteUrl}${value}` : value

const { data, error } = await useAsyncData(
  () => `tour-${locale.value}-${route.params.slug}`,
  () => api(`/v1/tours/${route.params.slug}`, { query: { locale: locale.value } }),
  { watch: [locale] },
)

if (error.value) throw createError({ statusCode: 404, statusMessage: 'Tour not found' })
const tour = computed(() => data.value?.tour || {})
const related = computed(() => data.value?.related || [])
const selectedDate = ref('')
const selectedGuests = ref(2)
const guestOptions = [2, 3, 4, '5+']
const formattedPrice = computed(() => String(Math.round(Number(tour.value.price || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '))

useSeoMeta({
  title: () => `${tour.value.title || 'Tour'} — GoVista`,
  description: () => tour.value.subtitle || tour.value.description,
  ogTitle: () => `${tour.value.title || 'Tour'} — GoVista`,
  ogDescription: () => tour.value.subtitle || tour.value.description,
  ogType: 'website',
  ogImage: () => absoluteImage(tour.value.image),
  twitterCard: 'summary_large_image',
  twitterTitle: () => `${tour.value.title || 'Tour'} — GoVista`,
  twitterDescription: () => tour.value.subtitle || tour.value.description,
  twitterImage: () => absoluteImage(tour.value.image),
})

const structuredData = computed(() => ({
  '@context': 'https://schema.org',
  '@graph': [
    {
      '@type': 'TouristTrip',
      '@id': `${siteUrl}${localePath(`/tours/${tour.value.slug}`)}#trip`,
      name: tour.value.title,
      description: tour.value.description,
      image: [tour.value.image, ...(tour.value.gallery || [])].filter(Boolean).map(absoluteImage),
      touristType: t(`tourTypes.${tour.value.type}`),
      itinerary: (tour.value.itinerary || []).join(' — '),
      provider: {
        '@type': 'TravelAgency',
        '@id': `${siteUrl}/#organization`,
        name: 'GoVista',
        url: siteUrl,
      },
      offers: {
        '@type': 'Offer',
        url: `${siteUrl}${localePath(`/tours/${tour.value.slug}`)}`,
        price: Number(tour.value.price || 0),
        priceCurrency: tour.value.currency,
        availability: 'https://schema.org/InStock',
      },
      aggregateRating: Number(tour.value.review_count || 0) > 0 ? {
        '@type': 'AggregateRating',
        ratingValue: Number(tour.value.rating || 0),
        reviewCount: Number(tour.value.review_count || 0),
        bestRating: 5,
      } : undefined,
    },
    {
      '@type': 'BreadcrumbList',
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
        { '@type': 'ListItem', position: 2, name: t('nav.tours'), item: `${siteUrl}${localePath('/tours')}` },
        { '@type': 'ListItem', position: 3, name: tour.value.title, item: `${siteUrl}${localePath(`/tours/${tour.value.slug}`)}` },
      ],
    },
  ],
}))

useHead(() => ({
  script: [{
    key: 'tour-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify(structuredData.value).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div class="tour-detail">
    <section class="detail-hero">
      <div class="detail-hero-image" :style="{ backgroundImage: `linear-gradient(90deg, rgba(5,20,32,.82), rgba(5,20,32,.12)), url('${tour.image}')` }"></div>
      <div class="container detail-hero-content">
        <div class="detail-breadcrumb"><NuxtLink :to="localePath('/tours')">{{ t('nav.tours') }}</NuxtLink><ChevronRight :size="14" /><span>{{ tour.title }}</span></div>
        <div class="detail-rating"><Star :size="15" fill="currentColor" />{{ tour.rating }} <span>({{ tour.review_count }} {{ t('common.reviews') }})</span></div>
        <h1>{{ tour.title }}</h1>
        <p>{{ tour.subtitle }}</p>
        <div class="detail-meta"><span><Globe2 :size="18" />{{ t(`tourScopes.${tour.travel_scope || 'domestic'}`) }}</span><span><MapPin :size="18" />{{ tour.location }}</span><span><Clock3 :size="18" />{{ tour.duration }}</span><span><Users :size="18" />{{ t(`tourTypes.${tour.type}`) }}</span></div>
      </div>
    </section>

    <section class="section detail-section">
      <div class="container detail-layout">
        <div class="detail-content">
          <p class="section-eyebrow">The experience</p>
          <h2>{{ locale === 'hy' ? 'Այս ճանապարհորդության մասին' : locale === 'ru' ? 'Об этом путешествии' : 'About this journey' }}</h2>
          <p class="detail-description">{{ tour.description }}</p>

          <div v-if="tour.highlights?.length" class="highlight-grid">
            <div v-for="highlight in tour.highlights" :key="highlight"><Check :size="17" />{{ highlight }}</div>
          </div>

          <div v-if="tour.gallery?.length" class="detail-gallery">
            <img v-for="(image, index) in tour.gallery.slice(0, 3)" :key="image" :src="image" :alt="`${tour.title} ${index + 1}`" width="1200" height="800" loading="lazy" decoding="async">
          </div>

          <div v-if="tour.itinerary?.length" class="itinerary">
            <p class="section-eyebrow">Itinerary</p>
            <h2>{{ locale === 'hy' ? 'Օրվա ծրագիրը' : locale === 'ru' ? 'Программа дня' : 'How the day unfolds' }}</h2>
            <div v-for="(item, index) in tour.itinerary" :key="item" class="itinerary-row"><span>{{ String(index + 1).padStart(2, '0') }}</span><div><strong>{{ item }}</strong><p>{{ locale === 'hy' ? 'Ժամանակը կարող է ճկվել՝ ըստ խմբի ռիթմի։' : locale === 'ru' ? 'Время может меняться в соответствии с ритмом группы.' : 'Timing may flex naturally with the pace of the group.' }}</p></div></div>
          </div>

          <div class="included-grid">
            <div><h3><Check :size="19" />{{ locale === 'hy' ? 'Ներառված է' : locale === 'ru' ? 'Включено' : 'Included' }}</h3><ul><li v-for="item in tour.included" :key="item">{{ item }}</li></ul></div>
            <div><h3><X :size="19" />{{ locale === 'hy' ? 'Ներառված չէ' : locale === 'ru' ? 'Не включено' : 'Not included' }}</h3><ul><li v-for="item in tour.excluded" :key="item">{{ item }}</li></ul></div>
          </div>
        </div>

        <aside class="booking-card">
          <p>{{ t('common.from') }}</p>
          <div class="booking-price">{{ formattedPrice }} <span>{{ tour.currency }}</span></div>
          <small>{{ t('common.perPerson') }}</small>
          <div class="booking-divider"></div>
          <label><CalendarDays :size="17" /><span>{{ t('forms.date') }}</span><DatePicker v-model="selectedDate" /></label>
          <label><Users :size="17" /><span>{{ t('forms.guests') }}</span><GuestSelect v-model="selectedGuests" :options="guestOptions" /></label>
          <button @click="openBooking(tour)">{{ t('common.book') }}</button>
          <div class="booking-assurance"><ShieldCheck :size="18" /><span>Free cancellation options<br><strong>Local support 24/7</strong></span></div>
        </aside>
      </div>
    </section>

    <section v-if="related.length" class="section related-section">
      <div class="container">
        <SectionHeader eyebrow="You may also like" :title="locale === 'hy' ? 'Նմանատիպ տուրեր' : locale === 'ru' ? 'Похожие туры' : 'More journeys to consider'" />
        <div class="tours-grid"><TourCard v-for="(item, index) in related" :key="item.id" :tour="item" :index="index" /></div>
      </div>
    </section>
  </div>
</template>
