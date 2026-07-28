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
    <div class="error-orb error-orb-one"></div>
    <div class="error-orb error-orb-two"></div>
    <section>
      <img src="/brand/govista-logo-light.png" alt="GoVista — Travel Beyond Limits" width="1528" height="426">
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
  padding: 32px;
  color: #fff;
  background: radial-gradient(circle at 70% 20%, #173f58 0, #0b2538 34%, #061725 100%);
}
.error-page::after {
  content: "";
  position: absolute;
  inset: auto 0 0;
  height: 36%;
  opacity: .4;
  background: linear-gradient(145deg, transparent 40%, #16384b 41%, #0a1e2c 70%);
  clip-path: polygon(0 85%, 18% 43%, 31% 72%, 49% 18%, 64% 62%, 79% 31%, 100% 82%, 100% 100%, 0 100%);
}
.error-page section {
  position: relative;
  z-index: 2;
  width: min(720px, 100%);
  text-align: center;
}
.error-page img {
  width: min(250px, 65vw);
  margin: 0 auto 42px;
}
.error-page p {
  margin: 0 0 18px;
  color: #f4a35d;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: .2em;
  text-transform: uppercase;
}
.error-page h1 {
  margin: 0;
  font-family: "Playfair Display", "Noto Sans Armenian", Georgia, serif;
  font-size: clamp(46px, 7vw, 82px);
  font-weight: 500;
  line-height: 1.05;
  text-wrap: balance;
}
.error-page span {
  display: block;
  max-width: 560px;
  margin: 24px auto 36px;
  color: #c9d5dc;
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
  border: 1px solid #f4a35d;
  border-radius: 999px;
  color: #0b1f33;
  background: #f4a35d;
  font-weight: 800;
}
.error-page button.secondary {
  color: #fff;
  background: transparent;
}
.error-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(10px);
}
.error-orb-one { width: 320px; height: 320px; top: -120px; right: -80px; background: rgba(244, 163, 93, .15); }
.error-orb-two { width: 260px; height: 260px; bottom: -90px; left: -70px; background: rgba(17, 167, 170, .12); }
</style>
