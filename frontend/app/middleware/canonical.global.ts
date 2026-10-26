export default defineNuxtRouteMiddleware((to) => {
  if (!/^\/(hy|ru|en)(?:\/|$)/.test(to.path)) return

  let path = to.path.replace(/\/+$/, '')
  const query = { ...to.query }
  const scope = String(query.scope)

  // The dedicated catalog pages replace the old scope-query URLs.
  if (/^\/(hy|ru|en)\/tours$/.test(path)
    && ['domestic', 'international'].includes(scope)) {
    path = path.replace(/\/tours$/, scope === 'domestic' ? '/domestic-tours' : '/international-tours')
    delete query.scope
  }

  if (path !== to.path) {
    return navigateTo({ path, query, hash: to.hash }, { redirectCode: 301, replace: true })
  }
})
