<script setup>
import { ArrowUpRight, ChevronDown, Menu, Phone, UserRound, X } from '@lucide/vue'
import { toInternationalPhone } from '~/utils/phone'

defineProps({ settings: { type: Object, default: () => ({}) } })

const { locale, t, localePath, switchLocale, locales } = useLocale()
const { openBooking } = useBooking()
const travel = useTravelText()
const route = useRoute()
const menuOpen = ref(false)
const langOpen = ref(false)
const scrolled = ref(false)
const header = ref(null)
const languageMenu = ref(null)
const closeMenu = () => { menuOpen.value = false }
useModalFocus(menuOpen, header, closeMenu, 'menu-open', '#main-content, .site-footer')
const solidHeader = computed(() => /^\/(hy|ru|en)\/(blog\/.+|travel(?:\/.*)?|account|services\/.+)/.test(route.path))
function outsideLanguage(event) {
  if (!languageMenu.value?.contains(event.target)) langOpen.value = false
}
const resize = () => { if (window.innerWidth > 1100) menuOpen.value = false }

const nav = computed(() => [
  { label: t('nav.domestic'), to: localePath('/domestic-tours') },
  { label: t('nav.international'), to: localePath('/international-tours') },
  { label: t('nav.stays'), to: localePath('/stays') },
  { label: t('nav.cars'), to: localePath('/cars') },
  { label: t('nav.destinations'), to: localePath('/destinations') },
  { label: travel.value.allServices, to: localePath('/travel') },
])

onMounted(() => {
  const handle = () => { scrolled.value = window.scrollY > 30 }
  handle()
  window.addEventListener('scroll', handle, { passive: true })
  document.addEventListener('pointerdown', outsideLanguage)
  window.addEventListener('resize', resize)
  onBeforeUnmount(() => {
    window.removeEventListener('scroll', handle)
    window.removeEventListener('resize', resize)
    document.removeEventListener('pointerdown', outsideLanguage)
  })
})

watch(() => route.fullPath, () => { menuOpen.value = false; langOpen.value = false })
</script>

<template>
  <header ref="header" class="site-header" :class="{ scrolled: scrolled || solidHeader, 'menu-active': menuOpen }">
    <div class="header-inner container">
      <NuxtLink :to="localePath('')" class="brand" aria-label="GoVista home">
        <img class="brand-logo brand-logo-light" src="/brand/govista-logo-light.png" alt="GoVista — Travel Beyond Limits" width="1528" height="426">
        <img class="brand-logo brand-logo-dark" src="/brand/govista-logo.png" alt="" aria-hidden="true" width="1528" height="426">
      </NuxtLink>

      <nav class="desktop-nav" :aria-label="t('ui.navigation')">
        <NuxtLink v-for="item in nav" :key="item.label" :to="item.to">{{ item.label }}</NuxtLink>
      </nav>

      <div class="header-actions">
        <NuxtLink :to="localePath('/account')" class="header-account" :aria-label="travel.account" :title="travel.account"><UserRound :size="19" /></NuxtLink>
        <a v-if="settings.phone" class="header-phone" :href="`tel:${toInternationalPhone(settings.phone)}`"><Phone :size="16" /> {{ settings.phone }}</a>
        <div ref="languageMenu" class="lang-switch" @keydown.esc.stop.prevent="langOpen = false; languageMenu?.querySelector('button')?.focus()">
          <button :aria-label="t('ui.language')" :aria-expanded="langOpen" aria-controls="language-options" @click="langOpen = !langOpen">{{ locale.toUpperCase() }} <ChevronDown :size="14" /></button>
          <div v-if="langOpen" id="language-options" class="lang-menu">
            <button v-for="item in locales" :key="item" :class="{ active: item === locale }" @click="switchLocale(item); langOpen = false">{{ item.toUpperCase() }}</button>
          </div>
        </div>
        <button class="header-book" @click="openBooking()">{{ t('common.book') }} <ArrowUpRight :size="15" /></button>
        <button class="mobile-menu-button" :aria-label="t('common.menu')" :aria-expanded="menuOpen" aria-controls="mobile-navigation" @click="menuOpen = !menuOpen">
          <X v-if="menuOpen" :size="23" />
          <Menu v-else :size="23" />
        </button>
      </div>
    </div>

    <transition name="mobile-nav">
      <div v-if="menuOpen" id="mobile-navigation" class="mobile-nav">
        <nav class="container">
          <NuxtLink v-for="(item, index) in nav" :key="item.label" :to="item.to"><span>0{{ index + 1 }}</span>{{ item.label }}</NuxtLink>
          <button @click="openBooking(); menuOpen = false">{{ t('common.book') }}</button>
        </nav>
      </div>
    </transition>
  </header>
</template>
