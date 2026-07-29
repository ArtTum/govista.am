<script setup>
import { ArrowUpRight, ChevronDown, Menu, Phone, X } from '@lucide/vue'

defineProps({ settings: { type: Object, default: () => ({}) } })

const { locale, t, localePath, switchLocale, locales } = useLocale()
const { openBooking } = useBooking()
const route = useRoute()
const menuOpen = ref(false)
const langOpen = ref(false)
const scrolled = ref(false)

const nav = computed(() => [
  { label: t('nav.domestic'), to: localePath('/domestic-tours') },
  { label: t('nav.international'), to: localePath('/international-tours') },
  { label: t('nav.stays'), to: localePath('/stays') },
  { label: t('nav.cars'), to: localePath('/cars') },
  { label: t('nav.destinations'), to: localePath('/destinations') },
  { label: t('nav.about'), to: localePath('/about') },
])

onMounted(() => {
  const handle = () => { scrolled.value = window.scrollY > 30 }
  handle()
  window.addEventListener('scroll', handle, { passive: true })
  onBeforeUnmount(() => window.removeEventListener('scroll', handle))
})

watch(() => route.fullPath, () => { menuOpen.value = false })
</script>

<template>
  <header class="site-header" :class="{ scrolled, 'menu-active': menuOpen }">
    <div class="header-inner container">
      <NuxtLink :to="localePath('')" class="brand" aria-label="GoVista home">
        <img class="brand-logo brand-logo-light" src="/brand/govista-logo-light.png" alt="GoVista — Travel Beyond Limits" width="1528" height="426">
        <img class="brand-logo brand-logo-dark" src="/brand/govista-logo.png" alt="" aria-hidden="true" width="1528" height="426">
      </NuxtLink>

      <nav class="desktop-nav" aria-label="Main navigation">
        <NuxtLink v-for="item in nav" :key="item.label" :to="item.to">{{ item.label }}</NuxtLink>
      </nav>

      <div class="header-actions">
        <a v-if="settings.phone" class="header-phone" :href="`tel:${settings.phone.replace(/\s/g, '')}`"><Phone :size="16" /> {{ settings.phone }}</a>
        <div class="lang-switch">
          <button aria-label="Change language" @click="langOpen = !langOpen">{{ locale.toUpperCase() }} <ChevronDown :size="14" /></button>
          <div v-if="langOpen" class="lang-menu">
            <button v-for="item in locales" :key="item" :class="{ active: item === locale }" @click="switchLocale(item); langOpen = false">{{ item.toUpperCase() }}</button>
          </div>
        </div>
        <button class="header-book" @click="openBooking()">{{ t('common.book') }} <ArrowUpRight :size="15" /></button>
        <button class="mobile-menu-button" :aria-label="t('common.menu')" @click="menuOpen = !menuOpen">
          <X v-if="menuOpen" :size="23" />
          <Menu v-else :size="23" />
        </button>
      </div>
    </div>

    <transition name="mobile-nav">
      <div v-if="menuOpen" class="mobile-nav">
        <nav class="container">
          <NuxtLink v-for="(item, index) in nav" :key="item.label" :to="item.to"><span>0{{ index + 1 }}</span>{{ item.label }}</NuxtLink>
          <button @click="openBooking(); menuOpen = false">{{ t('common.book') }}</button>
        </nav>
      </div>
    </transition>
  </header>
</template>
