<script setup>
import {
  Award,
  ArrowRight,
  ArrowUpRight,
  BedDouble,
  CalendarDays,
  CarFront,
  Check,
  ChevronLeft,
  ChevronRight,
  Compass,
  Globe2,
  Headphones,
  HeartHandshake,
  House,
  MapPin,
  PlaneTakeoff,
  Search,
  ShieldCheck,
  Sparkles,
  Star,
  Users,
} from '@lucide/vue'
import { toInternationalPhone } from '~/utils/phone'

const api = useGovistaApi()
const { locale, t, localePath } = useLocale()
const { openBooking } = useBooking()
const travel = useTravelText()
const route = useRoute()
const config = useRuntimeConfig()
const siteUrl = String(config.public.siteUrl).replace(/\/$/, '')

if (!['hy', 'ru', 'en'].includes(String(route.params.locale))) {
  await navigateTo('/hy', { redirectCode: 301 })
}

const { data, error } = await useAsyncData(
  () => `home-content-${locale.value}`,
  () => api('/v1/home', { query: { locale: locale.value } }),
  { watch: [locale] },
)

useContentError(error)

const settings = computed(() => data.value?.settings || {})
const featuredTours = computed(() => data.value?.featured_tours || [])
const domesticTours = computed(() => data.value?.domestic_tours || [])
const internationalTours = computed(() => data.value?.international_tours || [])
const accommodations = computed(() => data.value?.accommodations || [])
const cars = computed(() => data.value?.cars || [])
const destinations = computed(() => data.value?.destinations || [])
const services = computed(() => data.value?.services || [])
const experienceServices = computed(() => services.value.filter(service => ['events', 'custom'].includes(service.type)))
const posts = computed(() => data.value?.posts || [])
const testimonials = computed(() => data.value?.testimonials || [])
const faqs = computed(() => data.value?.faqs || [])
const activeTestimonial = ref(0)
const openFaq = ref(0)
const searchForm = reactive({ q: '', date: '', guests: 2 })
const trustIcons = [Globe2, Award, HeartHandshake, ShieldCheck]

const heroImage = computed(() => settings.value.hero_image || featuredTours.value[1]?.image || featuredTours.value[0]?.image)
const spotlightTour = computed(() => featuredTours.value[0] || null)
const spotlightPrice = computed(() => String(Math.round(Number(spotlightTour.value?.price || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '))
const journeyCategories = computed(() => [
  {
    title: t('nav.domestic'),
    eyebrow: locale.value === 'hy' ? 'Հայաստանը ներսից' : locale.value === 'ru' ? 'Армения изнутри' : 'Inside Armenia',
    copy: locale.value === 'hy' ? 'Օրվա տուրեր և բազմօրյա երթուղիներ Հայաստանի բոլոր մարզերում։' : locale.value === 'ru' ? 'Однодневные туры и многодневные маршруты по всей Армении.' : 'Day tours and multi-day routes across every region of Armenia.',
    to: localePath('/domestic-tours'),
    icon: House,
  },
  {
    title: t('nav.international'),
    eyebrow: locale.value === 'hy' ? 'Աշխարհը մոտ է' : locale.value === 'ru' ? 'Мир ближе' : 'The world is close',
    copy: locale.value === 'hy' ? 'Պատրաստ արտաքին փաթեթներ և անհատական ուղևորություններ Հայաստանից։' : locale.value === 'ru' ? 'Готовые зарубежные пакеты и индивидуальные поездки из Армении.' : 'Ready outbound packages and tailor-made travel from Armenia.',
    to: localePath('/international-tours'),
    icon: PlaneTakeoff,
  },
  {
    title: t('nav.stays'),
    eyebrow: locale.value === 'hy' ? 'Ապրեք հարմարավետ' : locale.value === 'ru' ? 'Живите с комфортом' : 'Stay beautifully',
    copy: locale.value === 'hy' ? 'Համարներ, բնակարաններ և տներ՝ ընտրված ու ստուգված GoVista-ի կողմից։' : locale.value === 'ru' ? 'Номера, апартаменты и дома, отобранные и проверенные GoVista.' : 'Rooms, apartments and homes selected and verified by GoVista.',
    to: localePath('/stays'),
    icon: BedDouble,
  },
  {
    title: t('nav.cars'),
    eyebrow: locale.value === 'hy' ? 'Ձեր ճանապարհը' : locale.value === 'ru' ? 'Ваша дорога' : 'Your road',
    copy: locale.value === 'hy' ? 'Վարձով մեքենաներ՝ վարորդով կամ առանց վարորդի, պարզ պայմաններով։' : locale.value === 'ru' ? 'Автомобили с водителем или без, на понятных условиях.' : 'Rental cars with or without a chauffeur, on clear terms.',
    to: localePath('/cars'),
    icon: CarFront,
  },
])

function submitSearch() {
  navigateTo({ path: localePath('/tours'), query: { search: searchForm.q.trim() || undefined, date: searchForm.date, guests: searchForm.guests } })
}

function testimonialMove(direction) {
  if (!testimonials.value.length) return
  activeTestimonial.value = (activeTestimonial.value + direction + testimonials.value.length) % testimonials.value.length
}

function formatDate(value) {
  const [year, month, day] = String(value || '').slice(0, 10).split('-')
  return year && month && day ? `${day}.${month}.${year}` : ''
}

useSeoMeta({
  title: () => `${settings.value.site_name || 'GoVista'} — ${settings.value.hero_title || 'Armenia'}`,
  description: () => settings.value.hero_subtitle,
  ogTitle: () => settings.value.hero_title,
  ogDescription: () => settings.value.hero_subtitle,
})

const structuredData = computed(() => ({
  '@context': 'https://schema.org',
  '@graph': [
    {
      '@type': 'TravelAgency',
      '@id': `${siteUrl}/#organization`,
      name: settings.value.site_name || 'GoVista',
      url: siteUrl,
      logo: `${siteUrl}/brand/govista-logo.png`,
      image: `${siteUrl}/og.png`,
      telephone: toInternationalPhone(settings.value.phone) || undefined,
      email: settings.value.email,
      address: settings.value.address
        ? { '@type': 'PostalAddress', streetAddress: settings.value.address, addressCountry: 'AM' }
        : undefined,
      sameAs: [settings.value.instagram, settings.value.facebook].filter(Boolean),
      priceRange: '$$',
    },
    {
      '@type': 'WebSite',
      '@id': `${siteUrl}/#website`,
      url: siteUrl,
      name: settings.value.site_name || 'GoVista',
      publisher: { '@id': `${siteUrl}/#organization` },
      potentialAction: {
        '@type': 'SearchAction',
        target: `${siteUrl}/${locale.value}/tours?search={search_term_string}`,
        'query-input': 'required name=search_term_string',
      },
    },
  ],
}))

useHead(() => ({
  script: [{
    key: 'govista-structured-data',
    type: 'application/ld+json',
    innerHTML: JSON.stringify(structuredData.value).replace(/</g, '\\u003c'),
  }],
}))
</script>

<template>
  <div class="home-page">
    <section class="hero">
      <div class="container hero-content">
        <div class="hero-copy">
          <p class="hero-eyebrow"><Sparkles :size="14" />{{ settings.hero_eyebrow }}</p>
          <h1>{{ settings.hero_title }}</h1>
          <p class="hero-lead">{{ settings.hero_subtitle }}</p>
          <div class="hero-actions">
            <NuxtLink :to="localePath('/tours')" class="primary-cta">{{ t('common.explore') }} <ArrowUpRight :size="19" /></NuxtLink>
            <button class="watch-button" @click="openBooking()"><span><ArrowUpRight :size="19" /></span>{{ t('common.book') }}</button>
          </div>
          <div class="hero-proof">
            <div class="hero-proof-icons">
              <span><ShieldCheck :size="17" /></span>
              <span><Star :size="16" fill="currentColor" /></span>
              <span><HeartHandshake :size="17" /></span>
            </div>
            <div>
              <strong>{{ spotlightTour?.rating || '4.9' }} / 5</strong>
              <span>{{ t('home.storiesEyebrow') }}</span>
            </div>
          </div>
        </div>

        <div class="hero-visual">
          <div class="hero-scene">
            <img v-if="heroImage" :src="heroImage" alt="" width="960" height="1080" fetchpriority="high">
            <span class="hero-scene-label"><MapPin :size="15" />{{ locale === 'hy' ? 'Հայաստան' : locale === 'ru' ? 'Армения' : 'Armenia' }}</span>
          </div>
          <div class="hero-compass" aria-hidden="true"><Compass :size="36" :stroke-width="1.3" /></div>
          <NuxtLink v-if="spotlightTour" :to="localePath(`/tours/${spotlightTour.slug}`)" class="hero-pick">
            <img :src="spotlightTour.image" alt="" width="160" height="180">
            <div>
              <small><Sparkles :size="12" />{{ t('common.featured') }}</small>
              <h2>{{ spotlightTour.title }}</h2>
              <span>{{ t('common.from') }} <strong>{{ spotlightPrice }} {{ spotlightTour.currency }}</strong></span>
            </div>
            <i><ArrowUpRight :size="20" /></i>
          </NuxtLink>
        </div>
      </div>

      <form class="hero-search container" @submit.prevent="submitSearch">
        <label class="hero-search-field hero-search-main">
          <MapPin :size="20" />
          <span><small>{{ t('nav.destinations') }}</small><input v-model="searchForm.q" :placeholder="locale === 'hy' ? 'Ո՞ւր եք ուզում գնալ' : locale === 'ru' ? 'Куда хотите поехать?' : 'Where would you like to go?'"></span>
        </label>
        <label class="hero-search-field">
          <CalendarDays :size="20" />
          <span><small>{{ t('forms.date') }}</small><DatePicker v-model="searchForm.date" placement="top" /></span>
        </label>
        <label class="hero-search-field">
          <Users :size="20" />
          <span><small>{{ t('forms.guests') }}</small><GuestSelect v-model="searchForm.guests" placement="top" /></span>
        </label>
        <button :aria-label="t('ui.search')"><Search :size="21" /><span>{{ t('common.explore') }}</span></button>
      </form>

    </section>

    <section class="trust-strip">
      <div class="container">
        <div v-for="(stat, index) in settings.stats || []" :key="index" class="trust-stat" v-reveal="index * 80">
          <i><component :is="trustIcons[index % trustIcons.length]" :size="19" /></i>
          <div><strong>{{ stat.value }}</strong><span>{{ stat[locale] || stat.hy || stat.en }}</span></div>
        </div>
      </div>
    </section>

    <section id="tours" class="section journey-hub-section">
      <div class="container">
        <SectionHeader
          :eyebrow="locale === 'hy' ? 'Ամեն ինչ մեկ տեղում' : locale === 'ru' ? 'Всё в одном месте' : 'Everything in one place'"
          :title="locale === 'hy' ? 'Ճամփորդեք ձեր ձևով' : locale === 'ru' ? 'Путешествуйте по-своему' : 'Travel your way'"
        />
        <div class="journey-hub-grid">
          <NuxtLink v-for="(category, index) in journeyCategories" :key="category.to" v-reveal="index * 70" :to="category.to" class="journey-hub-card">
            <span class="journey-hub-icon"><component :is="category.icon" :size="21" /></span>
            <div><small>{{ category.eyebrow }}</small><h3>{{ category.title }}</h3><p>{{ category.copy }}</p></div>
            <i><ArrowUpRight :size="20" /></i>
          </NuxtLink>
        </div>
        <div class="home-travel-links"><NuxtLink v-for="key in ['flights', 'hotels', 'transfers', 'activities', 'places', 'packages']" :key="key" :to="localePath(`/travel/${key}`)">{{ travel.services[key] }} <ArrowUpRight :size="15" /></NuxtLink></div>
      </div>
    </section>

    <section class="section section-tours home-collection-section">
      <div class="container">
        <SectionHeader
          :eyebrow="locale === 'hy' ? 'Բացահայտեք Հայաստանը' : locale === 'ru' ? 'Откройте Армению' : 'Discover Armenia'"
          :title="t('nav.domestic')"
          :link="localePath('/domestic-tours')"
          :link-label="t('common.viewAll')"
        />
        <div class="tours-grid"><TourCard v-for="(tour, index) in domesticTours" :key="tour.id" :tour="tour" :index="index" /></div>
      </div>
    </section>

    <section class="section section-tours home-collection-section outbound-home-section">
      <div class="container">
        <SectionHeader
          :eyebrow="locale === 'hy' ? 'Երևանից դեպի աշխարհ' : locale === 'ru' ? 'Из Еревана в мир' : 'From Yerevan to the world'"
          :title="t('nav.international')"
          :link="localePath('/international-tours')"
          :link-label="t('common.viewAll')"
        />
        <div class="tours-grid"><TourCard v-for="(tour, index) in internationalTours" :key="tour.id" :tour="tour" :index="index" /></div>
      </div>
    </section>

    <section class="section rental-showcase-section">
      <div class="container">
        <SectionHeader
          :eyebrow="locale === 'hy' ? 'Ձեր տունը ճանապարհին' : locale === 'ru' ? 'Ваш дом в путешествии' : 'Your home on the road'"
          :title="t('nav.stays')"
          :link="localePath('/stays')"
          :link-label="t('common.viewAll')"
        />
        <div class="rental-grid"><RentalCard v-for="(item, index) in accommodations" :key="item.id" :item="item" :index="index" /></div>
      </div>
    </section>

    <section class="section rental-showcase-section car-showcase-section">
      <div class="container">
        <SectionHeader
          :eyebrow="locale === 'hy' ? 'Ազատ շարժվեք' : locale === 'ru' ? 'Двигайтесь свободно' : 'Move freely'"
          :title="t('nav.cars')"
          :link="localePath('/cars')"
          :link-label="t('common.viewAll')"
        />
        <div class="rental-grid"><RentalCard v-for="(item, index) in cars" :key="item.id" :item="item" :index="index" /></div>
      </div>
    </section>

    <section v-if="experienceServices.length" class="section experience-section">
      <div class="container">
        <SectionHeader :eyebrow="t('home.servicesEyebrow')" :title="t('home.servicesTitle')" light />
        <div class="experience-grid compact">
          <article v-for="(service, index) in experienceServices" :key="service.id" v-reveal="index * 70" :class="['experience-card', `experience-${index + 1}`]">
            <img :src="service.image" :alt="service.title" width="1200" height="800" loading="lazy" decoding="async">
            <div class="experience-overlay"></div>
            <div class="experience-icon">
              <Sparkles v-if="service.type === 'custom'" :size="21" />
              <Headphones v-else-if="service.type === 'events'" :size="21" />
              <Compass v-else :size="21" />
            </div>
            <div class="experience-copy">
              <span>0{{ index + 1 }}</span>
              <h3>{{ service.title }}</h3>
              <p>{{ service.description }}</p>
              <button @click="openBooking(service)">{{ t('common.details') }} <ArrowRight :size="17" /></button>
            </div>
          </article>
        </div>
        <div class="service-values">
          <span><Check :size="17" /> {{ locale === 'hy' ? 'Անվճար խորհրդատվություն' : locale === 'ru' ? 'Бесплатная консультация' : 'Free consultation' }}</span>
          <span><Check :size="17" /> {{ t('ui.support') }}</span>
          <span><Check :size="17" /> {{ locale === 'hy' ? 'Տեղական մասնագետներ' : locale === 'ru' ? 'Местные эксперты' : 'Local experts' }}</span>
        </div>
      </div>
    </section>

    <section class="section destinations-section">
      <div class="container">
        <SectionHeader
          :eyebrow="t('home.destinationsEyebrow')"
          :title="t('home.destinationsTitle')"
          :link="localePath('/destinations')"
          :link-label="t('common.viewAll')"
        />
        <div class="destination-rail">
          <NuxtLink v-for="(destination, index) in destinations" :key="destination.id" v-reveal="index * 60" :to="localePath(`/destinations/${destination.slug}`)" class="destination-card">
            <img :src="destination.image" :alt="destination.title" width="1200" height="900" loading="lazy" decoding="async">
            <div class="destination-gradient"></div>
            <span>{{ destination.region }}</span>
            <h3>{{ destination.title }}</h3>
            <p>{{ destination.description }}</p>
            <i><ArrowRight :size="19" /></i>
          </NuxtLink>
        </div>
      </div>
    </section>

    <section class="section story-section">
      <div class="container story-layout">
        <div class="story-image" v-reveal>
        <img :src="featuredTours[3]?.image || heroImage" alt="Armenia journey" width="1200" height="900" loading="lazy" decoding="async">
        <div class="story-image-card"><Star :size="19" fill="currentColor" /><strong>4.9 / 5</strong><small>{{ t('common.reviews') }}</small></div>
      </div>
        <div class="story-copy" v-reveal="100">
        <p class="section-eyebrow">{{ locale === 'hy' ? 'Ինչու GoVista' : locale === 'ru' ? 'Почему GoVista' : 'Why GoVista' }}</p>
        <h2>{{ locale === 'hy' ? 'Մենք ցույց ենք տալիս ոչ միայն վայրը, այլև նրա հոգին' : locale === 'ru' ? 'Мы показываем не только место, но и его душу' : 'We show you more than a place — we share its soul' }}</h2>
        <p>{{ locale === 'hy' ? 'Մեր ճանապարհորդությունները ստեղծում են Հայաստանում ապրող մարդիկ։ Մենք գիտենք լավագույն տեսարանը առավոտյան, փոքրիկ ընտանեկան գինեգործարանը և այն ճանապարհը, որտեղ արժե մի պահ լռել։' : locale === 'ru' ? 'Наши путешествия создают люди, живущие в Армении. Мы знаем лучший утренний вид, маленькую семейную винодельню и дорогу, где стоит остановиться и помолчать.' : 'Our journeys are designed by people who live here. We know the best morning view, the tiny family winery and the road where it is worth stopping just to listen.' }}</p>
        <div class="story-points">
          <div><span>01</span><strong>{{ locale === 'hy' ? 'Մարդկային մոտեցում' : locale === 'ru' ? 'Человеческий подход' : 'Human by design' }}</strong></div>
          <div><span>02</span><strong>{{ locale === 'hy' ? 'Իրական տեղական փորձ' : locale === 'ru' ? 'Настоящий местный опыт' : 'Genuinely local' }}</strong></div>
          <div><span>03</span><strong>{{ locale === 'hy' ? 'Անթերի կազմակերպում' : locale === 'ru' ? 'Безупречная организация' : 'Seamless care' }}</strong></div>
        </div>
          <NuxtLink :to="localePath('/about')" class="text-cta">{{ t('nav.about') }} <ArrowRight :size="18" /></NuxtLink>
        </div>
      </div>
    </section>

    <section v-if="testimonials.length" class="section testimonial-section">
      <div class="container testimonial-layout">
        <div class="testimonial-intro">
          <p class="section-eyebrow">{{ t('home.storiesEyebrow') }}</p>
          <h2>{{ t('home.storiesTitle') }}</h2>
          <div class="testimonial-controls">
            <button :aria-label="t('ui.previous')" @click="testimonialMove(-1)"><ChevronLeft :size="20" /></button>
            <button :aria-label="t('ui.next')" @click="testimonialMove(1)"><ChevronRight :size="20" /></button>
          </div>
        </div>
        <div class="testimonial-card" v-reveal>
          <div class="quote-mark">“</div>
          <div class="stars"><Star v-for="n in 5" :key="n" :size="16" fill="currentColor" /></div>
          <blockquote>{{ testimonials[activeTestimonial]?.message }}</blockquote>
          <footer>
            <div class="testimonial-avatar">{{ testimonials[activeTestimonial]?.name?.charAt(0) }}</div>
            <div><strong>{{ testimonials[activeTestimonial]?.name }}</strong><span>{{ testimonials[activeTestimonial]?.country }} · {{ testimonials[activeTestimonial]?.source }}</span></div>
          </footer>
        </div>
      </div>
    </section>

    <section class="section journal-section">
      <div class="container">
        <SectionHeader
          :eyebrow="t('home.journalEyebrow')"
          :title="t('home.journalTitle')"
          :link="localePath('/blog')"
          :link-label="t('common.viewAll')"
        />
        <div class="journal-grid">
          <article v-for="(post, index) in posts" :key="post.id" v-reveal="index * 80" class="journal-card" :class="{ featured: index === 0 }">
            <NuxtLink :to="localePath(`/blog/${post.slug}`)" class="journal-image"><img :src="post.image" :alt="post.title" width="1200" height="800" loading="lazy" decoding="async"><span>{{ post.category }}</span></NuxtLink>
            <div>
              <small>{{ formatDate(post.published_at) }} · {{ post.reading_time }} {{ t('ui.minute') }}</small>
              <h3><NuxtLink :to="localePath(`/blog/${post.slug}`)">{{ post.title }}</NuxtLink></h3>
              <p>{{ post.excerpt }}</p>
              <NuxtLink :to="localePath(`/blog/${post.slug}`)">{{ t('common.readMore') }} <ArrowRight :size="16" /></NuxtLink>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section faq-section">
      <div class="container faq-layout">
        <div class="faq-heading">
          <p class="section-eyebrow">{{ t('home.faqEyebrow') }}</p>
          <h2>{{ t('home.faqTitle') }}</h2>
          <p>{{ settings.phone }}<br>{{ settings.email }}</p>
        </div>
        <div class="faq-list">
          <article v-for="(faq, index) in faqs" :key="faq.id" :class="{ open: openFaq === index }">
            <button :aria-expanded="openFaq === index" :aria-controls="`faq-answer-${faq.id}`" @click="openFaq = openFaq === index ? -1 : index"><span>{{ faq.question }}</span><i>{{ openFaq === index ? '−' : '+' }}</i></button>
            <div v-if="openFaq === index" :id="`faq-answer-${faq.id}`"><p>{{ faq.answer }}</p></div>
          </article>
        </div>
      </div>
    </section>

    <section class="cta-section">
      <div class="cta-media" :style="{ backgroundImage: `linear-gradient(90deg, rgba(8,29,44,.88), rgba(8,29,44,.35)), url('${destinations[0]?.image || heroImage}')` }"></div>
      <div class="container cta-content">
        <p class="section-eyebrow">{{ locale === 'hy' ? 'Պատրա՞ստ եք' : locale === 'ru' ? 'Готовы?' : 'Ready when you are' }}</p>
        <h2>{{ locale === 'hy' ? 'Ստեղծենք ձեր Հայաստանը' : locale === 'ru' ? 'Создадим вашу Армению' : 'Let’s create your Armenia' }}</h2>
        <p>{{ locale === 'hy' ? 'Պատմեք ձեր ցանկությունների մասին։ Մեր travel designer-ը 24 ժամում կպատրաստի անհատական առաջարկ։' : locale === 'ru' ? 'Расскажите о своих пожеланиях. Наш travel-дизайнер подготовит персональное предложение в течение 24 часов.' : 'Tell us what you dream of. Your travel designer will send a personal proposal within 24 hours.' }}</p>
        <button @click="openBooking()">{{ t('common.book') }} <ArrowRight :size="19" /></button>
      </div>
    </section>
  </div>
</template>
