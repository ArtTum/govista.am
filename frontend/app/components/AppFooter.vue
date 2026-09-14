<script setup>
import { ArrowUpRight, Camera, Mail, MapPin, MessageCircle, Phone } from '@lucide/vue'
import { toInternationalPhone } from '~/utils/phone'

defineProps({ settings: { type: Object, default: () => ({}) } })
const { t, locale, localePath } = useLocale()
const travel = useTravelText()
</script>

<template>
  <footer class="site-footer">
    <div class="footer-shape"></div>
    <div class="container footer-main">
      <div class="footer-brand">
        <img src="/brand/govista-logo-light.png" alt="GoVista — Travel Beyond Limits" width="1528" height="426" loading="lazy" decoding="async">
        <p>{{ t('home.servicesTitle') }}.</p>
        <div class="footer-socials">
          <a v-if="settings.instagram" :href="settings.instagram" aria-label="Instagram"><Camera :size="18" /></a>
          <a v-if="settings.facebook" :href="settings.facebook" aria-label="Facebook"><MessageCircle :size="18" /></a>
        </div>
      </div>
      <div class="footer-links">
        <h4>{{ t('nav.tours') }}</h4>
        <NuxtLink :to="localePath('/domestic-tours')">{{ t('nav.domestic') }}</NuxtLink>
        <NuxtLink :to="localePath('/international-tours')">{{ t('nav.international') }}</NuxtLink>
        <NuxtLink :to="localePath('/stays')">{{ t('nav.stays') }}</NuxtLink>
        <NuxtLink :to="localePath('/cars')">{{ t('nav.cars') }}</NuxtLink>
        <NuxtLink :to="localePath('/destinations')">{{ t('nav.destinations') }}</NuxtLink>
      </div>
      <div class="footer-links">
        <h4>GoVista</h4>
        <NuxtLink :to="localePath('/travel')">{{ travel.allServices }}</NuxtLink>
        <NuxtLink :to="localePath('/account')">{{ travel.account }}</NuxtLink>
        <NuxtLink :to="localePath('/about')">{{ t('nav.about') }}</NuxtLink>
        <NuxtLink :to="localePath('/blog')">{{ t('nav.blog') }}</NuxtLink>
        <NuxtLink :to="localePath('/privacy')">{{ t('ui.privacy') }}</NuxtLink>
        <NuxtLink :to="localePath('/terms')">{{ t('ui.terms') }}</NuxtLink>
        <NuxtLink :to="localePath('/photo-credits')">{{ locale === 'hy' ? 'Լուսանկարների հեղինակներ' : locale === 'ru' ? 'Авторы фотографий' : 'Photo credits' }}</NuxtLink>
      </div>
      <div class="footer-contact">
        <h4>{{ t('nav.contact') }}</h4>
        <a v-if="settings.phone" :href="`tel:${toInternationalPhone(settings.phone)}`"><Phone :size="16" />{{ settings.phone }}</a>
        <a v-if="settings.email" :href="`mailto:${settings.email}`"><Mail :size="16" />{{ settings.email }}</a>
        <span v-if="settings.address"><MapPin :size="16" />{{ settings.address }}</span>
        <a class="footer-cta" v-if="settings.whatsapp" :href="settings.whatsapp">WhatsApp <ArrowUpRight :size="17" /></a>
      </div>
    </div>
    <div class="container footer-bottom">
      <span>© {{ new Date().getFullYear() }} GoVista. {{ t('ui.made') }}</span>
      <span>HY · RU · EN</span>
    </div>
  </footer>
</template>
