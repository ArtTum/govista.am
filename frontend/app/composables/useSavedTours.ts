export function useSavedTours() {
  const savedSlugs = useState<string[]>('saved-tour-slugs', () => [])
  const loaded = useState('saved-tours-loaded', () => false)
  onMounted(() => {
    if (loaded.value) return
    try {
      const value = JSON.parse(localStorage.getItem('govista-saved-tours') || '[]')
      savedSlugs.value = Array.isArray(value) ? value.filter((item: unknown) => typeof item === 'string').slice(0, 200) : []
    } catch { savedSlugs.value = [] }
    loaded.value = true
  })
  const toggle = (slug: string) => {
    savedSlugs.value = savedSlugs.value.includes(slug) ? savedSlugs.value.filter(item => item !== slug) : [...savedSlugs.value, slug].slice(-200)
    try { localStorage.setItem('govista-saved-tours', JSON.stringify(savedSlugs.value)) } catch { /* Saving remains available for this visit when storage is disabled. */ }
  }
  return { savedSlugs, toggle }
}
