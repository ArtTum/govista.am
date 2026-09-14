<script setup lang="ts">
const props = defineProps<{
  error: {
    statusCode?: number
    statusMessage?: string
    url?: string
  }
}>()

const requestUrl = useRequestURL()
const locale = computed(() => {
  let pathname = requestUrl.pathname

  if (props.error?.url) {
    try {
      pathname = new URL(props.error.url, requestUrl.origin).pathname
    }
    catch {
      pathname = props.error.url
    }
  }

  const candidate = pathname.split('/')[1]
  return ['hy', 'ru', 'en'].includes(candidate) ? candidate : 'hy'
})

const copy = computed(() => ({
  hy: {
    eyebrow: 'GoVista',
    title404: 'Այս ճանապարհը դեռ քարտեզի վրա չէ',
    title500: 'Մի փոքր կանգառ ճանապարհին',
    text: 'Վերադարձեք գլխավոր էջ կամ ընտրեք մեկ այլ ճանապարհորդություն։',
    home: 'Գլխավոր էջ',
    tours: 'Տեսնել տուրերը',
  },
  ru: {
    eyebrow: 'GoVista',
    title404: 'Этого маршрута пока нет на карте',
    title500: 'Небольшая остановка в пути',
    text: 'Вернитесь на главную или выберите другое путешествие.',
    home: 'На главную',
    tours: 'Смотреть туры',
  },
  en: {
    eyebrow: 'GoVista',
    title404: 'This path is not on the map yet',
    title500: 'A short pause on the journey',
    text: 'Return home or choose another Armenia journey.',
    home: 'Back home',
    tours: 'Explore tours',
  },
}[locale.value]))

const statusCode = computed(() => Number(props.error?.statusCode || 500))
const go = (path: string) => clearError({ redirect: `/${locale.value}${path}` })

useHead(() => ({
  htmlAttrs: { lang: locale.value },
  title: `${statusCode.value} — GoVista`,
  meta: [{ name: 'robots', content: 'noindex, nofollow' }],
}))
</script>

<template>
  <main class="error-page">
    <section>
      <img src="/brand/govista-logo.png" alt="GoVista — Travel Beyond Limits" width="1528" height="426">
      <p>{{ copy.eyebrow }} · {{ statusCode }}</p>
      <h1>{{ statusCode === 404 ? copy.title404 : copy.title500 }}</h1>
      <span>{{ copy.text }}</span>
      <div>
        <button @click="go('')">{{ copy.home }}</button>
        <button class="secondary" @click="go('/tours')">{{ copy.tours }}</button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.error-page {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  display: grid;
  place-items: center;
  padding: 48px 24px;
  color: #17343d;
  background: #f7f7f1;
  font-family: Manrope, "Noto Sans Armenian", system-ui, sans-serif;
}
.error-page section {
  position: relative;
  z-index: 2;
  width: min(720px, 100%);
  text-align: center;
}
.error-page img {
  width: min(250px, 65vw);
  height: auto;
  display: block;
  margin: 0 auto 42px;
}
.error-page p {
  margin: 0 0 18px;
  color: #995323;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: .2em;
  text-transform: uppercase;
}
.error-page h1 {
  margin: 0;
  font: inherit;
  font-size: clamp(30px, 4.5vw, 54px);
  font-weight: 600;
  line-height: 1.25;
  letter-spacing: -.035em;
  overflow-wrap: anywhere;
  text-wrap: balance;
}
.error-page span {
  display: block;
  max-width: 560px;
  margin: 24px auto 36px;
  color: #627476;
  line-height: 1.8;
}
.error-page section > div {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
}
.error-page button {
  min-width: 160px;
  padding: 15px 24px;
  border: 1px solid #ffba75;
  border-radius: 11px;
  color: #102f38;
  background: #ffba75;
  font: inherit;
  font-size: 13px;
  font-weight: 800;
}
.error-page button.secondary {
  color: #102f38;
  border-color: #cbd7ce;
  background: transparent;
}
.error-page button:focus-visible {
  outline: 3px solid #087f72;
  outline-offset: 4px;
}
</style>
