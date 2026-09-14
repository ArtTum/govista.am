export default defineNuxtRouteMiddleware(to => {
  if (to.params.locale && !['hy', 'ru', 'en'].includes(String(to.params.locale))) {
    return navigateTo({ path: to.path.replace(/^\/[^/]+/, '/hy'), query: to.query, hash: to.hash }, { redirectCode: 301 })
  }
})
