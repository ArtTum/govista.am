<script setup>
import { ArrowUpRight } from '@lucide/vue'

defineProps({
  eyebrow: String,
  title: String,
  description: String,
  link: String,
  linkLabel: String,
  light: Boolean,
})
</script>

<template>
  <div class="section-header" :class="{ light }">
    <div>
      <p class="section-eyebrow">{{ eyebrow }}</p>
      <h2>{{ title }}</h2>
      <p v-if="description" class="section-description">{{ description }}</p>
    </div>
    <NuxtLink v-if="link" :to="link" class="arrow-link">{{ linkLabel }} <ArrowUpRight :size="18" /></NuxtLink>
  </div>
</template>
