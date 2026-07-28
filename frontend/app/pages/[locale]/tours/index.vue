<script setup>
import { Globe2, LayoutGrid, MapPinned, Search, SlidersHorizontal } from '@lucide/vue'

const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const route = useRoute()
const router = useRouter()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const allowedScopes = ['all', 'domestic', 'international']
const allowedTypes = ['all', 'group', 'private', 'package']
const normalize = (value, allowed) => allowed.includes(String(value)) ? String(value) : 'all'
const activeScope = ref(normalize(route.query.scope || 'all', allowedScopes))
const activeType = ref(normalize(route.query.type || 'all', allowedTypes))
const search = ref(String(route.query.search || ''))

const { data, status } = await useAsyncData(
  () => `tours-${locale.value}-${activeScope.value}-${activeType.value}`,
  () => api('/v1/tours', {
    query: {
      locale: locale.value,
      travel_scope: activeScope.value === 'all' ? undefined : activeScope.value,
      type: activeType.value === 'all' ? undefined : activeType.value,
      per_page: 48,
    },
  }),
  { watch: [locale, activeScope, activeType] },
)

const tours = computed(() => {
  const items = data.value?.data || []
  if (!search.value.trim()) return items
  const q = search.value.toLocaleLowerCase(locale.value)
  return items.filter(tour => `${tour.title} ${tour.location} ${tour.description}`.toLocaleLowerCase(locale.value).includes(q))
})

const scopeFilters = computed(() => [
  ['all', locale.value === 'hy' ? 'Բոլոր տուրերը' : locale.value === 'ru' ? 'Все туры' : 'All journeys', LayoutGrid],
  ['domestic', t('nav.domestic'), MapPinned],
  ['international', t('nav.international'), Globe2],
])

const typeFilters = computed(() => [
  ['all', locale.value === 'hy' ? 'Բոլոր ձևաչափերը' : locale.value === 'ru' ? 'Все форматы' : 'All formats'],
  ['group', t('tourTypes.group')],
  ['private', t('tourTypes.private')],
  ['package', t('tourTypes.package')],
])

const copy = computed(() => {
  const content = {
    hy: {
      all: ['Տուրեր Հայաստանում և արտերկրում', 'Ընտրեք Հայաստանի լավագույն ներքին տուրերից կամ ամբողջական արտաքին ճամփորդական փաթեթներից։', 'Տուրեր Հայաստանում և արտերկրում | GoVista', 'Ներքին և արտաքին տուրեր GoVista-ից՝ խմբային, անհատական և փաթեթային տարբերակներով։'],
      domestic: ['Բացահայտեք Հայաստանը նորովի', 'Մեկօրյա էքսկուրսիաներից մինչև բազմօրյա անհատական ծրագրեր՝ Հայաստանի բոլոր մարզերում։', 'Ներքին տուրեր Հայաստանում | GoVista', 'Բացահայտեք GoVista-ի ներքին տուրերը Հայաստանում՝ փորձառու գիդերով և հոգատար կազմակերպմամբ։'],
      international: ['Աշխարհը սկսվում է Երևանից', 'Պատրաստ արտաքին տուրեր և անհատական փաթեթներ՝ ծովափից մինչև եվրոպական քաղաքներ։', 'Արտաքին տուրեր Հայաստանից | GoVista', 'Արտաքին տուրեր Հայաստանից՝ Եվրոպա, ԱՄԷ, Եգիպտոս, Մալդիվներ և այլ ուղղություններ։'],
    },
    ru: {
      all: ['Туры по Армении и за рубеж', 'Выбирайте путешествия по Армении или готовые зарубежные пакеты с заботой GoVista.', 'Туры по Армении и за рубеж | GoVista', 'Внутренние и зарубежные туры GoVista: групповые, индивидуальные и пакетные путешествия.'],
      domestic: ['Откройте Армению по-новому', 'От однодневных экскурсий до многодневных частных маршрутов по всем регионам Армении.', 'Туры по Армении | GoVista', 'Авторские туры по Армении с опытными гидами и продуманной организацией GoVista.'],
      international: ['Мир начинается в Ереване', 'Готовые зарубежные туры и индивидуальные пакеты — от пляжей до европейских столиц.', 'Зарубежные туры из Армении | GoVista', 'Зарубежные туры из Армении в Европу, ОАЭ, Египет, на Мальдивы и по другим направлениям.'],
    },
    en: {
      all: ['Tours in Armenia and beyond', 'Choose from memorable Armenia tours and complete outbound travel packages, all managed by GoVista.', 'Armenia & Outbound Tours | GoVista', 'Domestic and outbound tours by GoVista, with group, private and complete travel package options.'],
      domestic: ['See Armenia differently', 'From easy day trips to multi-day private routes across every region of Armenia.', 'Armenia Tours & Excursions | GoVista', 'Discover curated Armenia tours with expert guides, thoughtful routes and personal GoVista care.'],
      international: ['The world starts in Yerevan', 'Ready-made outbound tours and tailor-made packages, from beach holidays to European city breaks.', 'Outbound Tours from Armenia | GoVista', 'Outbound tours from Armenia to Europe, the UAE, Egypt, the Maldives and more destinations.'],
    },
  }
  return content[locale.value][activeScope.value]
})

watch([activeScope, activeType], ([scope, type]) => {
  router.replace({
    query: {
      ...route.query,
      scope: scope === 'all' ? undefined : scope,
      type: type === 'all' ? undefined : type,
    },
  })
})

watch(() => route.query.scope, value => { activeScope.value = normalize(value || 'all', allowedScopes) })
watch(() => route.query.type, value => { activeType.value = normalize(value || 'all', allowedTypes) })

useSeoMeta({
  title: () => copy.value[2],
  description: () => copy.value[3],
  ogTitle: () => copy.value[2],
  ogDescription: () => copy.value[3],
  ogType: 'website',
  twitterCard: 'summary_large_image',
  twitterTitle: () => copy.value[2],
  twitterDescription: () => copy.value[3],
})

const structuredData = computed(() => ({
  '@context': 'https://schema.org',
  '@graph': [
    {
      '@type': 'BreadcrumbList',
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
        { '@type': 'ListItem', position: 2, name: t('nav.tours'), item: `${siteUrl}${localePath('/tours')}` },
      ],
    },
    {
      '@type': 'ItemList',
      name: copy.value[0],
      numberOfItems: tours.value.length,
      itemListElement: tours.value.map((tour, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        url: `${siteUrl}${localePath(`/tours/${tour.slug}`)}`,
        name: tour.title,
      })),
    },
  ],
}))

useHead(() => ({
  script: [{
    key: 'tours-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify(structuredData.value).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div>
    <section class="page-hero tours-page-hero">
      <div class="container">
        <p class="section-eyebrow">GoVista collection</p>
        <h1>{{ copy[0] }}</h1>
        <p>{{ copy[1] }}</p>
      </div>
    </section>
    <section class="section listing-section">
      <div class="container">
        <div class="scope-switch" aria-label="Tour direction">
          <button
            v-for="filter in scopeFilters"
            :key="filter[0]"
            :class="{ active: activeScope === filter[0] }"
            @click="activeScope = filter[0]"
          >
            <span><component :is="filter[2]" :size="20" /></span>
            <strong>{{ filter[1] }}</strong>
            <small>{{ filter[0] === 'domestic' ? 'Armenia' : filter[0] === 'international' ? 'Worldwide' : 'GoVista' }}</small>
          </button>
        </div>
        <div class="listing-toolbar">
          <div class="filter-pills">
            <button v-for="filter in typeFilters" :key="filter[0]" :class="{ active: activeType === filter[0] }" @click="activeType = filter[0]">{{ filter[1] }}</button>
          </div>
          <label class="listing-search"><Search :size="17" /><input v-model="search" :placeholder="locale === 'hy' ? 'Որոնել տուր...' : locale === 'ru' ? 'Найти тур...' : 'Search tours...'"></label>
        </div>
        <div v-if="status === 'pending'" class="page-loading">{{ locale === 'hy' ? 'Բեռնվում է...' : locale === 'ru' ? 'Загрузка...' : 'Loading...' }}</div>
        <div v-else-if="tours.length" class="tours-grid">
          <TourCard v-for="(tour, index) in tours" :key="tour.id" :tour="tour" :index="index" />
        </div>
        <div v-else class="no-results">
          <SlidersHorizontal :size="42" />
          <h2>{{ locale === 'hy' ? 'Համապատասխան տուր չի գտնվել' : locale === 'ru' ? 'Подходящих туров не найдено' : 'No matching tours' }}</h2>
        </div>
      </div>
    </section>
  </div>
</template>
