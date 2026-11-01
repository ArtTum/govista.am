<script setup>
import { ArrowUpRight, BedDouble, CarFront, Check, MapPin, Star } from '@lucide/vue'

const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Number, default: 0 },
})
const { t, localePath } = useLocale()
const travel = useTravelText()
const { openBooking } = useBooking()
const isStay = computed(() => props.item.type === 'accommodation')
const isTransfer = computed(() => props.item.type === 'transfer')
const kindLabel = computed(() => isStay.value ? t('nav.stays') : isTransfer.value ? travel.value.services.transfers : t('nav.cars'))
const detailPath = computed(() => localePath(`/${isStay.value ? 'stays' : isTransfer.value ? 'transfers' : 'cars'}/${props.item.slug}`))
const formattedPrice = computed(() => String(Math.round(Number(props.item.price_from || 0))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '))
</script>

<template>
  <article class="rental-card" v-reveal="index * 70">
    <NuxtLink :to="detailPath" class="rental-card-image">
      <img :src="item.image" :alt="item.title" width="1100" height="760" loading="lazy" decoding="async">
      <span class="rental-kind"><BedDouble v-if="isStay" :size="14" /><CarFront v-else :size="14" />{{ kindLabel }}</span>
      <span v-if="Number(item.review_count) > 0" class="rental-rating"><Star :size="13" fill="currentColor" />{{ item.rating }}</span>
    </NuxtLink>
    <div class="rental-card-body">
      <span class="rental-location"><MapPin :size="14" />{{ item.location }}</span>
      <small v-if="item.supplier?.name" class="supplier-name">{{ item.supplier.name }}</small>
      <h3><NuxtLink :to="detailPath">{{ item.title }}</NuxtLink></h3>
      <p>{{ item.description }}</p>
      <div class="rental-features">
        <span v-for="feature in (item.features || []).slice(0, 3)" :key="feature"><Check :size="13" />{{ feature }}</span>
      </div>
      <div class="rental-card-footer">
        <div><small>{{ t('common.from') }}</small><strong>{{ formattedPrice }} {{ item.currency }}</strong><span>{{ item.unit }}</span></div>
        <button @click="openBooking(item)">{{ isStay || isTransfer ? t('common.book') : t('common.rent') }} <ArrowUpRight :size="17" /></button>
      </div>
    </div>
  </article>
</template>
