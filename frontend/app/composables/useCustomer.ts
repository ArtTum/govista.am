let customerRestore: Promise<void> | null = null

export function useCustomer() {
  const token = useState<string>('customer-token', () => '')
  const customer = useState<any>('customer-profile', () => null)
  const ready = useState('customer-ready', () => false)
  const api = useGovistaApi()
  const clear = () => {
    token.value = ''
    customer.value = null
    if (import.meta.client) sessionStorage.removeItem('govista-customer')
  }
  const request = async <T>(path: string, options: Record<string, any> = {}) => {
    const requestToken = token.value
    try {
      return await api<T>(path, { cache: 'no-store', ...options, headers: { ...options.headers, ...(token.value ? { Authorization: `Bearer ${token.value}` } : {}) } })
    } catch (error: any) {
      if (error.statusCode === 401 && token.value === requestToken) clear()
      throw error
    }
  }
  const restore = async () => {
    if (!import.meta.client || ready.value) return
    if (customerRestore) return customerRestore
    customerRestore = (async () => {
      try {
        token.value = sessionStorage.getItem('govista-customer') || ''
        if (token.value) customer.value = await request('/v1/account/me')
      } catch { clear() }
      finally { ready.value = true; customerRestore = null }
    })()
    return customerRestore
  }
  const authenticate = async (mode: string, body: any) => {
    const result = await api<any>(`/v1/account/${mode}`, { method: 'POST', body })
    token.value = result.token
    customer.value = result.user
    if (import.meta.client) sessionStorage.setItem('govista-customer', result.token)
  }
  const logout = async () => {
    await request('/v1/account/logout', { method: 'POST' })
    clear()
  }
  return { token, customer, ready, restore, authenticate, logout, request }
}
