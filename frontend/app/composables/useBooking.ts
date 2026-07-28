export function useBooking() {
  const isOpen = useState('booking-open', () => false)
  const selected = useState<any>('booking-selected', () => null)

  const openBooking = (item: any = null) => {
    selected.value = item
    isOpen.value = true
  }

  const closeBooking = () => {
    isOpen.value = false
  }

  return { isOpen, selected, openBooking, closeBooking }
}
