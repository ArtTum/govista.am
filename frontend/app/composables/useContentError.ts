export function useContentError(error: { value: any }) {
  const check = (failure: any) => {
    if (!failure) return
    const status = Number(failure.statusCode || failure.status || failure.response?.status)
    const detail = { statusCode: status === 404 ? 404 : 503, statusMessage: status === 404 ? 'Page not found' : 'Service temporarily unavailable' }
    if (import.meta.server) throw createError(detail)
    showError(detail)
  }
  check(error.value)
  watch(() => error.value, check)
}
