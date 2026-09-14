<script setup>
import { Globe2, MapPinned, Search } from '@lucide/vue'

const props = defineProps({
  scope: { type: String, required: true, validator: value => ['domestic', 'international'].includes(value) },
})
const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const { data, status, error, refresh, activeScope, activeType, search, tours, resetFilters } = useTourCatalog(() => props.scope)

const typeFilters = computed(() => [
  ['all', locale.value === 'hy' ? 'Բոլոր ձևաչափերը' : locale.value === 'ru' ? 'Все форматы' : 'All formats'],
  ['group', t('tourTypes.group')],
  ['private', t('tourTypes.private')],
  ['package', t('tourTypes.package')],
])

const copy = computed(() => {
  const messages = {
    hy: {
      domestic: ['Ներքին տուրեր Հայաստանում', 'Մեկօրյա էքսկուրսիաներից մինչև բազմօրյա անհատական ծրագրեր՝ Հայաստանի բոլոր մարզերում։', 'Ներքին տուրեր Հայաստանում | GoVista'],
      international: ['Արտաքին տուրեր Հայաստանից', 'Պատրաստ փաթեթներ և անհատական ճամփորդություններ՝ Եվրոպա, ծովափ և էկզոտիկ ուղղություններ։', 'Արտաքին տուրեր Հայաստանից | GoVista'],
    },
    ru: {
      domestic: ['Туры по Армении', 'От однодневных экскурсий до многодневных частных маршрутов по всем регионам Армении.', 'Туры по Армении | GoVista'],
      international: ['Зарубежные туры из Армении', 'Готовые пакеты и индивидуальные путешествия в Европу, на море и по экзотическим направлениям.', 'Зарубежные туры из Армении | GoVista'],
    },
    en: {
      domestic: ['Tours across Armenia', 'From easy day trips to multi-day private routes across every region of Armenia.', 'Armenia Tours & Excursions | GoVista'],
      international: ['Outbound tours from Armenia', 'Ready-made packages and tailor-made journeys to Europe, beach escapes and exotic destinations.', 'Outbound Tours from Armenia | GoVista'],
    },
  }
  return messages[locale.value][props.scope]
})

const routePath = computed(() => props.scope === 'domestic' ? '/domestic-tours' : '/international-tours')

useSeoMeta({
  title: () => copy.value[2],
  description: () => copy.value[1],
  ogTitle: () => copy.value[2],
  ogDescription: () => copy.value[1],
  ogType: 'website',
  twitterCard: 'summary_large_image',
})

useHead(() => ({
  script: [{
    key: `${props.scope}-tours-structured-data`,
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'ItemList',
          name: copy.value[0],
          numberOfItems: tours.value.length,
          itemListElement: tours.value.map((tour, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            name: tour.title,
            url: `${siteUrl}${localePath(`/tours/${tour.slug}`)}`,
          })),
        },
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
            { '@type': 'ListItem', position: 2, name: copy.value[0], item: `${siteUrl}${localePath(routePath.value)}` },
          ],
        },
      ],
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div>
    <section class="page-hero tours-page-hero" :class="`scope-page-${scope}`">
      <div class="container">
        <p class="section-eyebrow"><MapPinned v-if="scope === 'domestic'" :size="15" /><Globe2 v-else :size="15" /> GoVista collection</p>
        <h1>{{ copy[0] }}</h1>
        <p>{{ copy[1] }}</p>
      </div>
    </section>
    <section id="catalog" class="section listing-section">
      <div class="container">
        <div class="listing-toolbar">
          <div class="filter-pills"><button v-for="filter in typeFilters" :key="filter[0]" :class="{ active: activeType === filter[0] }" :aria-pressed="activeType === filter[0]" @click="activeType = filter[0]">{{ filter[1] }}</button></div>
          <label class="listing-search"><Search :size="17" /><input v-model="search" type="search" :aria-label="t('ui.search')" :placeholder="locale === 'hy' ? 'Որոնել տուր...' : locale === 'ru' ? 'Найти тур...' : 'Search tours...'"></label>
        </div>
        <CollectionState :status="status" :error="error" :empty="!tours.length" @retry="refresh"><button class="primary-cta" @click="resetFilters">{{ t('ui.reset') }}</button></CollectionState>
        <div v-if="!error && status !== 'pending' && tours.length" class="tours-grid">
          <TourCard v-for="(tour, index) in tours" :key="tour.id" :tour="tour" :index="index" />
        </div>
        <CatalogPagination v-if="!error" :meta="data?.meta" />
      </div>
    </section>
  </div>
</template>
