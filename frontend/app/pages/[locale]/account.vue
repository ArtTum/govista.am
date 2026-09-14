<script setup>
import { ArrowUpRight, Compass, Loader2, LogOut, RefreshCw, UserRound } from '@lucide/vue'
const copy = useTravelText()
const { localePath, locale } = useLocale()
const route = useRoute()
const api = useGovistaApi()
const recoveryText = computed(() => ({ hy: ['Մոռացե՞լ եք գաղտնաբառը', 'Ուղարկել վերականգնման հղում', 'Պահպանել նոր գաղտնաբառը', 'Եթե հաշիվը գոյություն ունի, վերականգնման հղումը կստանաք էլ․ փոստով։', 'Գաղտնաբառը թարմացվել է։ Կարող եք մուտք գործել։', 'Էլ․ փոստի ուղարկումը դեռ կարգավորված չէ։ Վերականգնման համար կապվեք GoVista-ի հետ։'], ru: ['Забыли пароль?', 'Отправить ссылку', 'Сохранить новый пароль', 'Если аккаунт существует, ссылка придёт на почту.', 'Пароль обновлён. Вы можете войти.', 'Отправка почты ещё не настроена. Свяжитесь с GoVista для восстановления.'], en: ['Forgot password?', 'Send reset link', 'Save new password', 'If the account exists, a reset link will arrive by email.', 'Password updated. You can now sign in.', 'Email delivery is not configured yet. Contact GoVista for recovery.'] }[locale.value]))
const { customer, restore, authenticate, logout, request } = useCustomer()
const mode = ref(route.query.reset_token ? 'reset' : 'login')
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)
const errorMessage = ref('')
const fieldErrors = ref({})
const orders = ref(null)
const activeOrder = ref(null)
const notice = ref('')
let orderSequence = 0
useHead({ meta: [{ name: 'referrer', content: 'no-referrer' }] })
useSeoMeta({ title: () => `${copy.value.account} | GoVista`, robots: 'noindex,nofollow' })
onMounted(async () => { await restore(); if (customer.value) await loadOrders() })
watch(customer, value => { if (!value) { orderSequence++; orders.value = null; activeOrder.value = null } })
const setError = error => { fieldErrors.value = error.data?.errors || {}; errorMessage.value = error.statusCode === 401 ? copy.value.expired : error.statusCode === 409 ? `${copy.value.accountError} ${copy.value.refresh}` : copy.value.accountError }
async function submit() {
  if (loading.value) return
  loading.value = true; errorMessage.value = ''; fieldErrors.value = {}
  try {
    if (mode.value === 'forgot') {
      await api('/v1/account/forgot-password', { method: 'POST', body: { email: form.email, locale: locale.value } })
      notice.value = recoveryText.value[3]
      return
    }
    if (mode.value === 'reset') {
      await api('/v1/account/reset-password', { method: 'POST', body: { email: String(route.query.email || ''), token: String(route.query.reset_token || ''), password: form.password, password_confirmation: form.password_confirmation } })
      mode.value = 'login'; form.password = ''; form.password_confirmation = ''; notice.value = recoveryText.value[4]
      await navigateTo(localePath('/account'), { replace: true })
      return
    }
    await authenticate(mode.value, form)
    form.password = ''; form.password_confirmation = ''
    await loadOrders()
  } catch (error) { setError(error); if (mode.value === 'forgot' && error.statusCode === 503) errorMessage.value = recoveryText.value[5] }
  finally { loading.value = false }
}
async function loadOrders(page = 1) {
  const sequence = ++orderSequence
  const userId = customer.value?.id
  errorMessage.value = ''
  try {
    const response = await request(`/v1/account/orders?page=${page}`)
    if (sequence === orderSequence && customer.value?.id === userId) orders.value = response
  } catch (error) { if (sequence === orderSequence) setError(error) }
}
async function refresh() {
  if (loading.value) return
  loading.value = true
  try { await loadOrders(orders.value?.current_page || 1); if (activeOrder.value) activeOrder.value = orders.value?.data.find(order => order.id === activeOrder.value.id) || null }
  finally { loading.value = false }
}
async function signOut() {
  if (loading.value) return
  loading.value = true
  try { await logout() } catch (error) { setError(error) }
  finally { loading.value = false }
}
async function action(action) {
  if (loading.value) return
  loading.value = true; errorMessage.value = ''; notice.value = ''
  try {
    activeOrder.value = await request(`/v1/account/orders/${activeOrder.value.id}/action`, { method: 'POST', body: { action, version: activeOrder.value.version } })
    notice.value = copy.value.updated
    await loadOrders(orders.value?.current_page || 1)
  } catch (error) { setError(error) }
  finally { loading.value = false }
}
const formatDate = (value, withTime = false) => {
  if (!value) return '—'
  const date = new Date(value)
  if (locale.value === 'hy') {
    const months = ['հնվ', 'փտվ', 'մրտ', 'ապր', 'մյս', 'հնս', 'հլս', 'օգս', 'սեպ', 'հոկ', 'նոյ', 'դեկ']
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}${withTime ? `, ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}` : ''}`
  }
  return date.toLocaleString(locale.value, { year: 'numeric', month: 'short', day: 'numeric', ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}) })
}
const orderTitle = order => order.item_title || copy.value.services[({tour:'tours',group:'tours',private:'tours',package:'packages',accommodation:'hotels',transport:'cars',flight:'flights',transfer:'transfers',activity:'activities',place:'places'})[order.type]] || ({hy: 'Անհատական ճամփորդություն', ru: 'Индивидуальная поездка', en: 'Tailored journey'}[locale.value])
const money = order => `${new Intl.NumberFormat(locale.value).format(Number(order.total_price || 0))} ${order.currency}`
const canAccept = computed(() => activeOrder.value?.status === 'quoted' && new Date(activeOrder.value.quote_expires_at).getTime() > Date.now())
</script>

<template>
  <div class="travel-page account-page">
    <section class="container travel-intro compact"><p class="section-eyebrow">GoVista / {{ copy.account }}</p><h1>{{ customer?.name || copy.account }}<span>.</span></h1><p>{{ copy.accountIntro }}</p></section>
    <div class="container">
      <p v-if="errorMessage" class="travel-error" role="alert">{{ errorMessage }}</p>
      <p v-if="notice" class="travel-notice" role="status">{{ notice }}</p>
      <div v-if="!customer" class="account-auth">
        <div class="account-auth-story"><UserRound :size="42" /><h2>{{ copy.noOrders }}</h2><p>{{ copy.accountIntro }}</p><NuxtLink :to="localePath('/travel')">{{ copy.allServices }} <ArrowUpRight :size="18" /></NuxtLink></div>
        <form class="account-auth-form" :aria-busy="loading" @submit.prevent="submit">
          <div class="account-auth-tabs"><button v-for="key in ['login', 'register']" :key="key" type="button" :class="{ active: mode === key }" :aria-pressed="mode === key" :disabled="loading" @click="mode = key; fieldErrors = {}; errorMessage = ''">{{ key === 'login' ? copy.signIn : copy.register }}</button></div>
          <label v-if="mode === 'register'">{{ copy.name }}<input v-model.trim="form.name" autocomplete="name" maxlength="120" required :aria-invalid="Boolean(fieldErrors.name)"></label>
          <label v-if="mode !== 'reset'">{{ copy.email }}<input v-model.trim="form.email" type="email" autocomplete="email" maxlength="190" required :aria-invalid="Boolean(fieldErrors.email)"><small v-if="fieldErrors.email">{{ copy.accountError }}</small></label>
          <label v-if="mode !== 'forgot'">{{ copy.password }}<input v-model="form.password" type="password" :autocomplete="mode !== 'login' ? 'new-password' : 'current-password'" :minlength="mode !== 'login' ? 10 : 1" maxlength="128" required :aria-invalid="Boolean(fieldErrors.password)"><small v-if="mode !== 'login'">{{ copy.passwordHint }}</small></label>
          <label v-if="['register', 'reset'].includes(mode)">{{ copy.confirmPassword }}<input v-model="form.password_confirmation" type="password" autocomplete="new-password" minlength="10" maxlength="128" required></label>
          <button v-if="mode === 'login'" type="button" class="travel-text-button" @click="mode = 'forgot'; errorMessage = ''">{{ recoveryText[0] }}</button>
          <button class="button-primary" :disabled="loading"><Loader2 v-if="loading" class="spin" :size="18" />{{ mode === 'forgot' ? recoveryText[1] : mode === 'reset' ? recoveryText[2] : mode === 'login' ? copy.signIn : copy.register }}</button>
        </form>
      </div>
      <template v-else>
        <div class="account-toolbar"><NuxtLink :to="localePath('/travel')">{{ copy.allServices }} <ArrowUpRight :size="16" /></NuxtLink><div><button :disabled="loading" @click="refresh"><RefreshCw :size="16" />{{ copy.refresh }}</button><button :disabled="loading" @click="signOut"><LogOut :size="16" />{{ copy.signOut }}</button></div></div>
        <section v-if="activeOrder" class="account-order-detail">
          <button class="travel-back" @click="activeOrder = null; notice = ''">← {{ copy.back }}</button>
          <div class="account-order-heading"><div><small>{{ activeOrder.reference }}</small><h2>{{ orderTitle(activeOrder) }}</h2></div><span class="travel-status" :class="`is-${activeOrder.status}`">{{ copy.statuses[activeOrder.status] }}</span></div>
          <p>{{ formatDate(activeOrder.start_date) }} <template v-if="activeOrder.end_date">— {{ formatDate(activeOrder.end_date) }}</template> · {{ activeOrder.participants }} {{ copy.adults }} / {{ copy.children }}</p>
          <p v-if="activeOrder.request_details?.origin || activeOrder.request_details?.destination">{{ activeOrder.request_details.origin }} → {{ activeOrder.request_details.destination }}</p>
          <PackageRequestSummary v-if="activeOrder.request_details?.package_snapshot" :snapshot="activeOrder.request_details.package_snapshot" />
          <p v-if="activeOrder.message" class="travel-preserve">{{ activeOrder.message }}</p>
          <div v-if="activeOrder.total_price !== null" class="account-quote">
            <p class="section-eyebrow">{{ copy.total }}</p><strong>{{ money(activeOrder) }}</strong>
            <p v-if="activeOrder.quote_expires_at">{{ copy.expires }} {{ formatDate(activeOrder.quote_expires_at, true) }}</p>
            <h3>{{ copy.conditions }}</h3><p class="travel-preserve">{{ activeOrder.quote_terms }}</p>
            <div v-if="activeOrder.components?.length" class="account-components"><div v-for="(component, index) in activeOrder.components" :key="index"><span>{{ component.title }}</span><span>{{ component.sell_price }} {{ component.currency }}</span><small>{{ copy.statuses[component.status] }}</small></div></div>
            <template v-if="activeOrder.status === 'quoted'"><p>{{ canAccept ? copy.acceptNote : copy.quoteExpired }}</p><button class="button-primary" :disabled="loading || !canAccept" @click="action('accept')">{{ copy.accept }}</button></template>
          </div>
          <p v-if="activeOrder.confirmation_reference"><strong>{{ copy.confirmation }}:</strong> {{ activeOrder.confirmation_reference }}</p>
          <section class="account-history"><h3>{{ copy.history }}</h3><ol><li v-for="event in activeOrder.events" :key="event.id"><span></span><div><small>{{ formatDate(event.created_at, true) }}</small><strong>{{ copy.statuses[event.status] }}</strong><p v-if="event.message" class="travel-preserve">{{ event.message }}</p></div></li></ol></section>
          <button v-if="['new', 'reviewing', 'quoted', 'accepted', 'confirmed'].includes(activeOrder.status)" class="travel-cancel" :disabled="loading" @click="action('cancel')">{{ ['accepted', 'confirmed'].includes(activeOrder.status) ? copy.requestCancel : copy.cancel }}</button>
        </section>
        <template v-else-if="orders?.data?.length">
          <div class="account-orders"><button v-for="order in orders.data" :key="order.id" class="account-order-card" @click="activeOrder = order; notice = ''"><div><small>{{ order.reference }} · {{ formatDate(order.created_at) }}</small><h2>{{ orderTitle(order) }}</h2><span class="travel-status" :class="`is-${order.status}`">{{ copy.statuses[order.status] }}</span></div><ArrowUpRight :size="24" /></button></div>
          <nav v-if="orders.last_page > 1" class="travel-pagination" aria-label="Pagination"><button :disabled="loading || orders.current_page === 1" @click="loadOrders(orders.current_page - 1)">{{ copy.previous }}</button><span>{{ orders.current_page }} / {{ orders.last_page }}</span><button :disabled="loading || orders.current_page === orders.last_page" @click="loadOrders(orders.current_page + 1)">{{ copy.next }}</button></nav>
        </template>
        <div v-else-if="orders" class="travel-empty"><Compass :size="36" /><h2>{{ copy.noOrders }}</h2><p>{{ copy.noOrdersText }}</p><NuxtLink :to="localePath('/travel')" class="button-primary">{{ copy.allServices }} <ArrowUpRight :size="18" /></NuxtLink></div>
        <div v-else-if="!errorMessage" class="travel-empty" role="status"><Loader2 class="spin" :size="26" /></div>
      </template>
    </div>
  </div>
</template>
