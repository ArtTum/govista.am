<script setup>
import { ArrowUpRight, Plane, Hotel, Car, Bus, Compass, MapPin, Camera, Luggage } from '@lucide/vue'
const copy = useTravelText()
const { localePath } = useLocale()
const icons = { tours: Compass, hotels: Hotel, flights: Plane, cars: Car, transfers: Bus, activities: Camera, places: MapPin, packages: Luggage }
useSeoMeta({ title: () => `${copy.value.travel} | GoVista`, description: () => copy.value.intro })
</script>

<template>
  <div class="travel-page">
    <section class="travel-intro container">
      <p class="section-eyebrow">{{ copy.eyebrow }}</p>
      <h1>{{ copy.travel }}<span>.</span></h1>
      <p>{{ copy.intro }}</p>
    </section>
    <section class="container travel-service-grid" :aria-label="copy.allServices">
      <NuxtLink v-for="(icon, key) in icons" :key="key" :to="localePath(`/travel/${key}`)" class="travel-service-card" :class="`travel-tone-${key}`">
        <div class="travel-service-top"><component :is="icon" :size="28" /><ArrowUpRight :size="21" /></div>
        <h2>{{ copy.services[key] }}</h2>
        <p>{{ copy.descriptions[key] }}</p>
        <span>{{ copy.browse }} <ArrowUpRight :size="15" /></span>
      </NuxtLink>
    </section>
    <section class="container travel-concierge"><div><p class="section-eyebrow">GoVista concierge</p><h2>{{ copy.services.packages }}</h2><p>{{ copy.requestNote }}</p></div><NuxtLink :to="localePath('/travel/packages')" class="button-primary">{{ copy.request }} <ArrowUpRight :size="18" /></NuxtLink></section>
  </div>
</template>
