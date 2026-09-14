export function toInternationalPhone(value: string | null | undefined): string {
  const phone = String(value ?? '').replace(/[^\d+]/g, '')

  if (/^0\d{8}$/.test(phone)) return `+374${phone.slice(1)}`
  if (/^374\d{8}$/.test(phone)) return `+${phone}`

  return phone
}
