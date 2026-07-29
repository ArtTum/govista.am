<script setup>
import { ArrowUpRight, BedDouble, CarFront, Check, MapPin, Star } from '@lucide/vue'

const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Number, default: 0 },
})
const { t, localePath } = useLocale()
const { openBooking } = useBooking()
const isStay = computed(() => props.item.type === 'accommodation')
const detailPath = computed(() => localePath(`/${isStay.value ? 'stays' : 'cars'}/${props.item.slug}`))
const formattedPrice = computed(() => String(Math.round(Number(props.item.price_from || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '))
</script>

<template>
  <article class="rental-card" v-reveal="index * 70">
    <NuxtLink :to="detailPath" class="rental-card-image">
      <img :src="item.image" :alt="item.title" width="1100" height="760" loading="lazy" decoding="async">
      <span class="rental-kind"><BedDouble v-if="isStay" :size="14" /><CarFront v-else :size="14" />{{ isStay ? t('nav.stays') : t('nav.cars') }}</span>
      <span class="rental-rating"><Star :size="13" fill="currentColor" />{{ item.rating }}</span>
    </NuxtLink>
    <div class="rental-card-body">
      <span class="rental-location"><MapPin :size="14" />{{ item.location }}</span>
      <h3><NuxtLink :to="detailPath">{{ item.title }}</NuxtLink></h3>
      <p>{{ item.description }}</p>
      <div class="rental-features">
        <span v-for="feature in (item.features || []).slice(0, 3)" :key="feature"><Check :size="13" />{{ feature }}</span>
      </div>
      <div class="rental-card-footer">
        <div><small>{{ t('common.from') }}</small><strong>{{ formattedPrice }} {{ item.currency }}</strong><span>{{ item.unit }}</span></div>
        <button @click="openBooking(item)">{{ isStay ? t('common.book') : t('common.rent') }} <ArrowUpRight :size="17" /></button>
      </div>
    </div>
  </article>
</template>
