export function useBooking() {
  const isOpen = useState('booking-open', () => false)
  const selected = useState<any>('booking-selected', () => null)
  const preferences = useState<Record<string, any>>('booking-preferences', () => ({}))
  const route = useRoute()

  const openBooking = (item: any = null, defaults: Record<string, any> = {}) => {
    selected.value = item
    const guests = Number.parseInt(String(route.query.guests || 2), 10)
    preferences.value = {
      start_date: /^\d{4}-\d{2}-\d{2}$/.test(String(route.query.date || '')) ? String(route.query.date) : '',
      participants: Number.isFinite(guests) ? Math.min(100, Math.max(1, guests)) : 2,
      ...defaults,
    }
    isOpen.value = true
  }

  const closeBooking = () => {
    isOpen.value = false
  }

  return { isOpen, selected, preferences, openBooking, closeBooking }
}
