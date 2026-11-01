<script setup>
import { BedDouble, CarFront, Search } from '@lucide/vue'

const props = defineProps({
  type: { type: String, required: true, validator: value => ['accommodation', 'transport', 'transfer'].includes(value) },
})
const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')
const search = ref('')
const isStay = computed(() => props.type === 'accommodation')
const isTransfer = computed(() => props.type === 'transfer')
const travel = useTravelText()
const routeSegment = computed(() => isStay.value ? 'stays' : isTransfer.value ? 'transfers' : 'cars')
const kindLabel = computed(() => isStay.value ? t('nav.stays') : isTransfer.value ? travel.value.services.transfers : t('nav.cars'))

const { data, status, error, refresh } = await useAsyncData(
  () => `rental-catalog-${props.type}-${locale.value}`,
  () => api('/v1/services', { query: { locale: locale.value, type: props.type } }),
  { watch: [locale] },
)

const items = computed(() => {
  const list = data.value || []
  if (!search.value.trim()) return list
  const query = search.value.trim().toLocaleLowerCase(locale.value)
  return list.filter(item => `${item.title} ${item.location} ${item.description}`.toLocaleLowerCase(locale.value).includes(query))
})

const copy = computed(() => {
  const messages = {
    hy: {
      accommodation: ['Կացարաններ Հայաստանում', 'Ընտրված համարներ, բնակարաններ և առանձնատներ՝ պարզ պայմաններով ու GoVista-ի աջակցությամբ։', 'Կացարանների վարձույթ Հայաստանում | GoVista'],
      transport: ['Ավտոմեքենաների վարձույթ', 'Economy մեքենաներից մինչև ամենագնաց և մինիվեն՝ վարորդով կամ առանց վարորդի։', 'Ավտոմեքենաների վարձույթ Հայաստանում | GoVista'],
      transfer: ['Անհատական տրանսֆերներ', 'Swift Rent-ի վարորդներով՝ Զվարթնոցից քաղաքներ, հյուրանոցներ և հանգստավայրեր Հայաստանում ու Վրաստանում։', 'Օդանավակայանի տրանսֆերներ | GoVista'],
    },
    ru: {
      accommodation: ['Жильё в Армении', 'Отобранные номера, апартаменты и дома с понятными условиями и поддержкой GoVista.', 'Аренда жилья в Армении | GoVista'],
      transport: ['Аренда автомобилей', 'От экономичных машин до внедорожников и минивэнов — с водителем или без.', 'Аренда автомобилей в Армении | GoVista'],
      transfer: ['Индивидуальные трансферы', 'С водителями Swift Rent из Звартноца в города, отели и курорты Армении и Грузии.', 'Трансферы из аэропорта | GoVista'],
    },
    en: {
      accommodation: ['Places to stay in Armenia', 'Handpicked rooms, apartments and private homes with clear terms and GoVista support.', 'Stays & Holiday Rentals in Armenia | GoVista'],
      transport: ['Car rental in Armenia', 'From efficient city cars to SUVs and minivans, with or without a chauffeur.', 'Car Rental in Armenia | GoVista'],
      transfer: ['Private transfers', 'Swift Rent drivers from Zvartnots Airport to cities, hotels and resorts in Armenia and Georgia.', 'Airport Transfers | GoVista'],
    },
  }
  return messages[locale.value][props.type]
})

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
    key: `${props.type}-catalog-structured-data`,
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'ItemList',
          name: copy.value[0],
          numberOfItems: items.value.length,
          itemListElement: items.value.map((item, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            name: item.title,
            url: `${siteUrl}${localePath(`/${routeSegment.value}/${item.slug}`)}`,
          })),
        },
        {
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'GoVista', item: `${siteUrl}/${locale.value}` },
            { '@type': 'ListItem', position: 2, name: copy.value[0], item: `${siteUrl}${localePath(`/${routeSegment.value}`)}` },
          ],
        },
      ],
    }).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div>
    <section class="page-hero rental-page-hero" :class="isStay ? 'stay-hero' : 'car-hero'">
      <div class="container">
        <p class="section-eyebrow"><BedDouble v-if="isStay" :size="15" /><CarFront v-else :size="15" /> GoVista {{ isStay ? 'stays' : 'drive' }}</p>
        <h1>{{ copy[0] }}</h1>
        <p>{{ copy[1] }}</p>
        <NuxtLink v-if="!isStay" class="supplier-catalog-link" :to="localePath(isTransfer ? '/cars' : '/transfers')">{{ isTransfer ? t('nav.cars') : travel.services.transfers }} →</NuxtLink>
      </div>
    </section>
    <section class="section rental-listing-section">
      <div class="container">
        <div class="rental-listing-toolbar">
          <div><strong>{{ items.length }}</strong><span>{{ kindLabel }}</span></div>
          <label class="listing-search"><Search :size="17" /><input v-model="search" type="search" :aria-label="t('ui.search')" :placeholder="locale === 'hy' ? 'Որոնել...' : locale === 'ru' ? 'Поиск...' : 'Search...'"></label>
        </div>
        <CollectionState :status="status" :error="error" :empty="!items.length" @retry="refresh"><button class="primary-cta" @click="search = ''">{{ t('ui.reset') }}</button></CollectionState>
        <div v-if="!error && status !== 'pending' && items.length" class="rental-grid">
          <RentalCard v-for="(item, index) in items" :key="item.id" :item="item" :index="index" />
        </div>
      </div>
    </section>
  </div>
</template>
