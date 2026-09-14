<script setup>
import { ArrowLeft, ArrowUpRight } from '@lucide/vue'
const route = useRoute()
const { locale, localePath } = useLocale()
const copy = useTravelText()
const api = useGovistaApi()
const { openBooking } = useBooking()
const { data, error } = await useAsyncData(`travel-service-${locale.value}-${route.params.slug}`, () => api(`/v1/services/${route.params.slug}`, { query: { locale: locale.value } }))
if (error.value) throw createError({ statusCode: error.value.statusCode === 404 ? 404 : 503 })
const service = computed(() => data.value?.service)
useSeoMeta({ title: () => `${service.value?.title} | GoVista`, description: () => service.value?.description?.slice(0, 160) })
</script>
<template><div class="travel-page"><section v-if="service" class="container travel-service-detail"><NuxtLink :to="localePath('/travel')" class="travel-back"><ArrowLeft :size="16" />{{ copy.allServices }}</NuxtLink><img v-if="service.image" :src="service.image" :alt="service.title" width="1200" height="700"><p class="section-eyebrow">GoVista</p><h1>{{ service.title }}</h1><p class="travel-preserve">{{ service.description }}</p><ul v-if="service.features?.length"><li v-for="feature in service.features" :key="feature">{{ feature }}</li></ul><p>{{ copy.requestNote }}</p><button class="button-primary" @click="openBooking(service)">{{ copy.request }}<ArrowUpRight :size="18" /></button></section></div></template>
