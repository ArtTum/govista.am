<script setup>
import { ArrowLeft, BedDouble, CalendarDays, CarFront, Check, MapPin, ShieldCheck, Star } from '@lucide/vue'

const props = defineProps({
  type: { type: String, required: true, validator: value => ['accommodation', 'transport', 'transfer'].includes(value) },
})
const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const { openBooking } = useBooking()
const route = useRoute()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const absoluteImage = value => value?.startsWith('/') ? `${siteUrl}${value}` : value
const isStay = computed(() => props.type === 'accommodation')
const isTransfer = computed(() => props.type === 'transfer')
const travel = useTravelText()
const kindLabel = computed(() => isStay.value ? t('nav.stays') : isTransfer.value ? travel.value.services.transfers : t('nav.cars'))
const routeSegment = computed(() => isStay.value ? 'stays' : isTransfer.value ? 'transfers' : 'cars')

const { data, error } = await useAsyncData(
  () => `rental-${props.type}-${locale.value}-${route.params.slug}`,
  () => api(`/v1/services/${route.params.slug}`, { query: { locale: locale.value } }),
  { watch: [locale] },
)

useContentError(error)
if (data.value?.service && data.value.service.type !== props.type) {
  throw createError({ statusCode: 404, statusMessage: 'Rental not found' })
}

const item = computed(() => data.value?.service || {})
const related = computed(() => data.value?.related || [])
const formattedPrice = computed(() => String(Math.round(Number(item.value.price_from || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '))

useSeoMeta({
  title: () => `${item.value.title || ''} — GoVista`,
  description: () => item.value.description,
  ogTitle: () => `${item.value.title || ''} — GoVista`,
  ogDescription: () => item.value.description,
  ogType: 'website',
  ogImage: () => absoluteImage(item.value.image),
  twitterCard: 'summary_large_image',
  twitterImage: () => absoluteImage(item.value.image),
})

useHead(() => ({
  script: [{
    key: 'rental-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': isStay.value ? 'LodgingBusiness' : isTransfer.value ? 'Service' : 'Product',
          name: item.value.title,
          description: item.value.description,
          image: [item.value.image, ...(item.value.gallery || [])].filter(Boolean).map(absoluteImage),
          address: isStay.value ? { '@type': 'PostalAddress', addressLocality: item.value.location, addressCountry: 'AM' } : undefined,
          aggregateRating: Number(item.value.review_count || 0) ? {
            '@type': 'AggregateRating',
            ratingValue: Number(item.value.rating),
            reviewCount: Number(item.value.review_count),
          } : undefined,
          offers: {
            '@type': 'Offer',
            url: `${siteUrl}${localePath(`/${routeSegment.value}/${item.value.slug}`)}`,
            price: Number(item.value.price_from || 0),
            priceCurrency: item.value.currency,
            availability: item.value.supplier ? undefined : 'https://schema.org/InStock',
            eligibleDuration: item.value.supplier?.minimum_days ? { '@type': 'QuantitativeValue', minValue: item.value.supplier.minimum_days, unitCode: 'DAY' } : undefined,
          },
        },
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
            { '@type': 'ListItem', position: 2, name: kindLabel.value, item: `${siteUrl}${localePath(`/${routeSegment.value}`)}` },
            { '@type': 'ListItem', position: 3, name: item.value.title, item: `${siteUrl}${localePath(`/${routeSegment.value}/${item.value.slug}`)}` },
          ],
        },
      ],
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div class="rental-detail-page">
    <section class="rental-detail-hero">
      <div class="rental-detail-media" :style="{ backgroundImage: `linear-gradient(90deg, rgba(5,20,32,.82), rgba(5,20,32,.16)), url('${item.image}')` }"></div>
      <div class="container rental-detail-hero-copy">
        <NuxtLink :to="localePath(`/${routeSegment}`)" class="back-link"><ArrowLeft :size="17" />{{ kindLabel }}</NuxtLink>
        <p class="rental-detail-kind"><BedDouble v-if="isStay" :size="16" /><CarFront v-else :size="16" />{{ kindLabel }}</p>
        <h1>{{ item.title }}</h1>
        <div class="detail-meta"><span><MapPin :size="18" />{{ item.location }}</span><span v-if="Number(item.review_count) > 0"><Star :size="17" fill="currentColor" />{{ item.rating }} ({{ item.review_count }} {{ t('common.reviews') }})</span><span v-if="item.supplier?.name">{{ item.supplier.name }}</span></div>
      </div>
    </section>

    <section class="section rental-detail-section">
      <div class="container rental-detail-layout">
        <div>
          <p class="section-eyebrow">{{ t('ui.verified') }}</p>
          <h2>{{ locale === 'hy' ? 'Ամեն ինչ այս տարբերակի մասին' : locale === 'ru' ? 'Всё об этом варианте' : 'Everything about this option' }}</h2>
          <p class="detail-description">{{ item.description }}</p>
          <SupplierDetails v-if="item.supplier" :item="item" />
          <div class="rental-detail-features">
            <span v-for="feature in item.features || []" :key="feature"><Check :size="17" />{{ feature }}</span>
          </div>
          <div v-if="item.gallery?.length" class="rental-detail-gallery">
            <img v-for="(image, index) in item.gallery" :key="image" :src="image" :alt="`${item.title} ${index + 1}`" width="1200" height="800" loading="lazy">
          </div>
        </div>
        <aside class="booking-card">
          <p>{{ t('common.from') }}</p>
          <div class="booking-price">{{ formattedPrice }} <span>{{ item.currency }}</span></div>
          <small>{{ item.unit }}</small>
          <div class="booking-divider"></div>
          <div class="rental-booking-note"><CalendarDays :size="18" /><span>{{ isStay ? t('forms.checkIn') : t('forms.date') }}<strong>{{ locale === 'hy' ? 'Ընտրեք ամրագրման ժամանակ' : locale === 'ru' ? 'Выберите при бронировании' : 'Choose while booking' }}</strong></span></div>
          <p v-if="item.supplier" class="supplier-request-note">{{ travel.requestNote }}</p>
          <button @click="openBooking(item)">{{ isStay || isTransfer ? t('common.book') : t('common.rent') }}</button>
          <div class="booking-assurance"><ShieldCheck :size="18" /><span>{{ t('ui.verified') }}<br><strong>{{ t('ui.support') }}</strong></span></div>
        </aside>
      </div>
    </section>

    <section v-if="related.length" class="section related-section">
      <div class="container">
        <SectionHeader :eyebrow="t('ui.related')" :title="locale === 'hy' ? 'Այլ տարբերակներ' : locale === 'ru' ? 'Другие варианты' : 'More options for you'" />
        <div class="rental-grid"><RentalCard v-for="(relatedItem, index) in related" :key="relatedItem.id" :item="relatedItem" :index="index" /></div>
      </div>
    </section>
  </div>
</template>
