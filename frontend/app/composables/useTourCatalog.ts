export function useTourCatalog(scope?: () => string) {
  const api = useGovistaApi()
  const { locale } = useLocale()
  const route = useRoute()
  const router = useRouter()
  const normalize = (value: unknown, allowed: string[]) => allowed.includes(String(value)) ? String(value) : 'all'
  const update = (values: Record<string, string | undefined>) => router.replace({ query: { ...route.query, page: undefined, ...values } })
  const activeScope = computed({
    get: () => scope?.() || normalize(route.query.scope, ['domestic', 'international']),
    set: value => { void update({ scope: value === 'all' ? undefined : value }) },
  })
  const activeType = computed({
    get: () => normalize(route.query.type, ['group', 'private', 'package']),
    set: value => { void update({ type: value === 'all' ? undefined : value }) },
  })
  const search = ref(String(route.query.search || ''))
  let searchTimer: ReturnType<typeof setTimeout>
  watch(search, value => {
    clearTimeout(searchTimer)
    if (value === String(route.query.search || '')) return
    searchTimer = setTimeout(() => { void update({ search: value.trim() || undefined }) }, 300)
  })
  watch(() => route.query.search, value => { search.value = String(value || '') })
  onBeforeUnmount(() => clearTimeout(searchTimer))
  const page = computed(() => Math.max(1, Number.parseInt(String(route.query.page || '1'), 10) || 1))
  const query = computed(() => ({
    locale: locale.value,
    travel_scope: activeScope.value === 'all' ? undefined : activeScope.value,
    type: activeType.value === 'all' ? undefined : activeType.value,
    search: String(route.query.search || '').trim() || undefined,
    page: page.value,
    per_page: 12,
  }))
  const result = useAsyncData(
    () => `tour-catalog-${JSON.stringify(query.value)}`,
    () => api<any>('/v1/tours', { query: query.value }),
  )
  const tours = computed(() => result.data.value?.data || [])
  const resetFilters = () => {
    clearTimeout(searchTimer)
    search.value = ''
    void update({ search: undefined, type: undefined, scope: undefined })
  }
  return { ...result, activeScope, activeType, search, tours, resetFilters }
}
