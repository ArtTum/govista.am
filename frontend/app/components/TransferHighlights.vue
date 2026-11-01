<script setup>
const api = useGovistaApi()
const { locale, localePath, t } = useLocale()
const copy = useTravelText()
const { data } = await useAsyncData(() => `transfer-highlights-${locale.value}`, () => api('/v1/services', { query: { type: 'transfer', locale: locale.value } }), { watch: [locale] })
</script>

<template>
  <section v-if="data?.length" class="container transfer-highlights">
    <SectionHeader eyebrow="Swift Rent" :title="copy.services.transfers" :link="localePath('/transfers')" :link-label="t('common.viewAll')" />
    <div class="rental-grid"><RentalCard v-for="(item, index) in data.slice(0, 3)" :key="item.id" :item="item" :index="index" /></div>
  </section>
</template>
