<script setup>
import { ArrowUpRight, Clock3, Globe2, Heart, MapPin, Star } from '@lucide/vue'

const props = defineProps({ tour: { type: Object, required: true }, index: { type: Number, default: 0 } })
const { t, localePath } = useLocale()
const { openBooking } = useBooking()
const saved = ref(false)

const formattedPrice = computed(() => String(Math.round(Number(props.tour.price || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '))
</script>

<template>
  <article class="tour-card" v-reveal="index * 80">
    <NuxtLink :to="localePath(`/tours/${tour.slug}`)" class="tour-image">
      <img :src="tour.image" :alt="tour.title" width="1200" height="800" loading="lazy" decoding="async">
      <div class="tour-badge-stack">
        <span class="tour-scope-badge" :class="`scope-${tour.travel_scope || 'domestic'}`"><Globe2 :size="11" />{{ t(`tourScopes.${tour.travel_scope || 'domestic'}`) }}</span>
        <span v-if="tour.featured" class="tour-badge">{{ t('common.featured') }}</span>
      </div>
      <button aria-label="Save tour" :class="{ saved }" @click.prevent="saved = !saved"><Heart :size="18" :fill="saved ? 'currentColor' : 'none'" /></button>
      <div class="tour-rating"><Star :size="13" fill="currentColor" /> {{ tour.rating }}</div>
    </NuxtLink>
    <div class="tour-card-body">
      <div class="tour-meta"><span><MapPin :size="14" />{{ tour.location }}</span><span><Clock3 :size="14" />{{ tour.duration }}</span></div>
      <h3><NuxtLink :to="localePath(`/tours/${tour.slug}`)">{{ tour.title }}</NuxtLink></h3>
      <p>{{ tour.subtitle || tour.description }}</p>
      <div class="tour-card-footer">
        <div class="tour-price"><small>{{ t('common.from') }}</small><strong>{{ formattedPrice }} <span>{{ tour.currency }}</span></strong><small>{{ t('common.perPerson') }}</small></div>
        <button aria-label="Book tour" @click="openBooking(tour)"><ArrowUpRight :size="20" /></button>
      </div>
    </div>
  </article>
</template>
