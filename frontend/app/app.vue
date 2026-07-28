<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
const requestUrl = useRequestURL()
const socialImage = `${requestUrl.origin}/og.png`

useSeoMeta({
  ogImage: socialImage,
  ogImageAlt: 'GoVista — tours in Armenia and around the world',
  twitterCard: 'summary_large_image',
  twitterImage: socialImage,
  twitterImageAlt: 'GoVista — tours in Armenia and around the world',
})
</script>
