export function useGovistaApi() {
  const config = useRuntimeConfig()

  return <T>(path: string, options: Record<string, any> = {}) => {
    const method = String(options.method || 'GET').toUpperCase()

    return $fetch<T>(path, {
      baseURL: import.meta.server ? config.apiBaseInternal : config.public.apiBase,
      timeout: 12_000,
      retry: method === 'GET' ? 1 : 0,
      ...options,
    })
  }
}
