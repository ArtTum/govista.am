<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
const config = useRuntimeConfig()
const socialImage = `${String(config.public.siteUrl).replace(/\/$/, '')}/og.png`

useSeoMeta({
  ogImage: socialImage,
  ogImageAlt: 'GoVista — tours in Armenia and around the world',
  twitterCard: 'summary_large_image',
  twitterImage: socialImage,
  twitterImageAlt: 'GoVista — tours in Armenia and around the world',
})
</script>
