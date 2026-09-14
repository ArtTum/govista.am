export default defineNuxtPlugin((nuxtApp) => {
  const observers = new WeakMap<Element, IntersectionObserver>()
  nuxtApp.vueApp.directive('reveal', {
    getSSRProps() {
      return {}
    },
    mounted(el, binding) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return
      el.classList.add('reveal')
      if (binding.value) el.style.setProperty('--reveal-delay', `${Math.min(Number(binding.value), 240)}ms`)

      const observer = new IntersectionObserver(
        ([entry]) => {
          if (entry?.isIntersecting) {
            el.classList.add('is-visible')
            observer.disconnect()
            observers.delete(el)
          }
        },
        { threshold: 0.12 },
      )

      observer.observe(el)
      observers.set(el, observer)
    },
    unmounted(el) {
      observers.get(el)?.disconnect()
      observers.delete(el)
    },
  })
})
