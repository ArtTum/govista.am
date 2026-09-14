<script setup>
defineProps({ meta: Object })
const route = useRoute()
const { t } = useLocale()
const pageLink = page => ({ path: route.path, query: { ...route.query, page: page > 1 ? page : undefined }, hash: '#catalog' })
</script>

<template>
  <nav v-if="meta?.last_page > 1" class="catalog-pagination" :aria-label="t('ui.pagination')">
    <NuxtLink v-if="meta.current_page > 1" :to="pageLink(meta.current_page - 1)">{{ t('ui.previous') }}</NuxtLink>
    <span aria-live="polite">{{ meta.current_page }} / {{ meta.last_page }}</span>
    <NuxtLink v-if="meta.current_page < meta.last_page" :to="pageLink(meta.current_page + 1)">{{ t('ui.next') }}</NuxtLink>
  </nav>
</template>
