<script setup>
const route = useRoute()
const services = ['tours', 'hotels', 'flights', 'cars', 'transfers', 'activities', 'places', 'packages']
if (!services.includes(String(route.params.service))) throw createError({ statusCode: 404 })
const copy = useTravelText()
const service = computed(() => String(route.params.service))
useSeoMeta({ title: () => `${copy.value.services[service.value]} | GoVista`, description: () => copy.value.descriptions[service.value], robots: 'noindex,follow' })
definePageMeta({ key: route => route.fullPath })
</script>
<template><PackageExplorer v-if="service === 'packages'" /><TravelExplorer v-else :key="service" :service="service" /></template>
