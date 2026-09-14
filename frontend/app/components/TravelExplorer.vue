<script setup>
import { ArrowLeft, ArrowUpRight, Search, Compass, Plus, X, Loader2, Plane, MapPin } from '@lucide/vue'
const props = defineProps({ service: { type: String, required: true } })
const copy = useTravelText()
const { locale, localePath } = useLocale()
const { openBooking } = useBooking()
const api = useGovistaApi()
const cities = [
  ['Yerevan', 'Երևան', 'Ереван', 40.1811, 44.5136], ['Dubai', 'Դուբայ', 'Дубай', 25.2048, 55.2708],
  ['Paris', 'Փարիզ', 'Париж', 48.8566, 2.3522], ['Rome', 'Հռոմ', 'Рим', 41.9028, 12.4964],
  ['Tbilisi', 'Թբիլիսի', 'Тбилиси', 41.7151, 44.8271], ['London', 'Լոնդոն', 'Лондон', 51.5074, -0.1278],
  ['Sharm El Sheikh', 'Շարմ էլ Շեյխ', 'Шарм-эль-Шейх', 27.9158, 34.33], ['Hurghada', 'Հուրգադա', 'Хургада', 27.2579, 33.8116],
]
const cityName = city => city[locale.value === 'hy' ? 1 : locale.value === 'ru' ? 2 : 0]
const types = { tours: 'tour', hotels: 'accommodation', flights: 'flight', cars: 'transport', transfers: 'transfer', activities: 'activity', places: 'place', packages: 'package' }
const transferHint = computed(() => ({hy: 'Ընտրված քաղաքի համար առցանց տրանսֆերի որոնումը կատարվում է օդանավակայանից մինչև քաղաքի կենտրոն։ Հյուրանոցի ճշգրիտ հասցեն նշեք հայտում։',ru: 'Онлайн-поиск трансфера выполняется из аэропорта до центра выбранного города. Укажите точный адрес отеля в заявке.',en: 'Online transfers are searched from the airport to the selected city centre. Include the exact hotel address in your request.'}[locale.value]))
const form = reactive({ origin: '', destination: '', start_date: '', end_date: '', departure_time: '', adults: 2, children: [], rooms: 1, cabin: 'economy', booker_country: '', currency: 'USD' })
const loading = ref(false)
const result = ref(null)
const errorMessage = ref('')
const fieldErrors = ref({})
const searched = ref(null)
const resultsEl = ref(null)
const locationSuggestions = ref([])
const locationSource = ref(false)
let locationTimer, locationSequence = 0
onBeforeUnmount(() => { clearTimeout(locationTimer); locationSequence++ })
function suggestLocations() {
  clearTimeout(locationTimer)
  const sequence = ++locationSequence
  if (!['hotels', 'transfers', 'places'].includes(props.service) || form.destination.trim().length < 3 || locationSuggestions.value.some(item => item.label === form.destination)) return
  locationTimer = setTimeout(async () => {
    try {
      const response = await api('/v1/travel/locations', { query: { search: form.destination.trim(), locale: locale.value, kind: props.service === 'transfers' ? 'address' : 'city' }, retry: 0 })
      if (sequence === locationSequence) { locationSuggestions.value = response.data; locationSource.value = response.available }
    } catch { if (sequence === locationSequence) locationSuggestions.value = [] }
  }, 450)
}
const hasOrigin = ['flights', 'transfers', 'packages', 'cars'].includes(props.service)
const hasDates = props.service !== 'places'
const hasEnd = ['hotels', 'flights', 'cars', 'packages'].includes(props.service)
const dateRequired = ['hotels', 'flights', 'cars', 'transfers'].includes(props.service)
const today = new Date().toLocaleDateString('en-CA')
const minimumEnd = computed(() => {
  if (props.service !== 'hotels' || !form.start_date) return form.start_date || today
  const date = new Date(`${form.start_date}T12:00:00`); date.setDate(date.getDate() + 1)
  return date.toLocaleDateString('en-CA')
})
watch(() => form.start_date, () => { if (form.end_date && form.end_date < minimumEnd.value) form.end_date = '' })
watch(() => form.adults, () => { form.rooms = Math.min(form.rooms, form.adults) })
const body = () => {
  const city = cities.find(city => city.slice(0, 3).some(name => name.toLowerCase() === form.destination.trim().toLowerCase()))
  const suggested = locationSuggestions.value.find(item => item.label === form.destination.trim())
  return {
    service: props.service, locale: locale.value, destination: form.destination.trim(), origin: form.origin.trim(), adults: Number(form.adults), children: [...form.children], currency: form.currency,
    ...(hasDates && form.start_date ? { start_date: form.start_date } : {}),
    ...(hasEnd && form.end_date ? { end_date: form.end_date } : {}),
    ...(props.service === 'hotels' ? { rooms: Number(form.rooms), booker_country: form.booker_country.toLowerCase(), platform: window.innerWidth < 768 ? 'mobile' : 'desktop' } : {}),
    ...(props.service === 'flights' ? { cabin: form.cabin } : {}),
    ...(props.service === 'transfers' ? { departure_time: form.departure_time } : {}),
    ...(suggested ? { latitude: suggested.latitude, longitude: suggested.longitude } : city ? { latitude: city[3], longitude: city[4] } : {}),
  }
}
async function search(page = 1) {
  if (loading.value) return
  loading.value = true; errorMessage.value = ''; fieldErrors.value = {}
  try {
    const query = page === 1 ? body() : searched.value
    result.value = await api('/v1/travel/search', { method: 'POST', body: { ...query, page }, timeout: 24_000 })
    searched.value = query
    await nextTick(); resultsEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  } catch (error) {
    result.value = null
    fieldErrors.value = error.data?.errors || {}
    errorMessage.value = copy.value.searchError
  } finally { loading.value = false }
}
function request(item = null) {
  const query = searched.value || body()
  const details = Object.fromEntries(Object.entries(query).filter(([key]) => ['service', 'origin', 'destination', 'adults', 'children', 'rooms', 'cabin', 'departure_time'].includes(key)))
  if (item && item.provider !== 'local') Object.assign(details, { provider: item.provider, offer_id: item.id })
  openBooking({ id: item?.provider === 'local' ? item.item_id : null, title: item?.title || `${copy.value.services[props.service]}${query.destination ? ` · ${query.destination}` : ''}`, type: item?.type || types[props.service] }, { start_date: query.start_date || '', end_date: query.end_date || '', participants: Number(query.adults) + (query.children?.length || 0), request_details: details })
}
const price = item => item.price == null ? copy.value.onRequest : `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(Number(item.price))} ${item.currency || ''}`
</script>

<template>
  <div class="travel-page">
    <section class="container travel-intro compact">
      <NuxtLink :to="localePath('/travel')" class="travel-back"><ArrowLeft :size="16" /> {{ copy.allServices }}</NuxtLink>
      <h1>{{ copy.services[service] }}<span>.</span></h1><p>{{ copy.descriptions[service] }}</p>
    </section>
    <div class="container">
      <nav class="travel-tabs" :aria-label="copy.allServices"><NuxtLink v-for="(label, key) in copy.services" :key="key" :to="localePath(`/travel/${key}`)" :aria-current="service === key ? 'page' : undefined">{{ label }}</NuxtLink></nav>
      <form class="travel-search-form" :aria-busy="loading" @submit.prevent="search()">
        <div class="travel-fields">
          <label v-if="hasOrigin">{{ copy.origin }}<input v-model.trim="form.origin" :placeholder="['flights', 'transfers'].includes(service) ? copy.airport : copy.origin" :required="service === 'flights'" :pattern="service === 'flights' ? '[A-Z]{3}' : undefined" maxlength="190" :aria-invalid="Boolean(fieldErrors.origin)"></label>
          <label>{{ copy.destination }}<input v-model.trim="form.destination" @input="suggestLocations" :list="service === 'flights' ? undefined : 'travel-cities'" :placeholder="service === 'flights' ? copy.airport : copy.sampleCities" :required="['flights', 'transfers', 'activities'].includes(service)" :pattern="service === 'flights' ? '[A-Z]{3}' : undefined" maxlength="190" :aria-invalid="Boolean(fieldErrors.destination)"><datalist id="travel-cities"><option v-for="item in locationSuggestions" :key="item.label" :value="item.label" /><option v-for="city in cities" :key="city[0]" :value="cityName(city)" /></datalist></label>
          <label v-if="hasDates">{{ copy.date }}<input v-model="form.start_date" type="date" :min="today" :required="dateRequired" :aria-invalid="Boolean(fieldErrors.start_date)"></label>
          <label v-if="hasEnd">{{ copy.returnDate }}<input v-model="form.end_date" type="date" :min="minimumEnd" :required="['hotels', 'cars'].includes(service)" :aria-invalid="Boolean(fieldErrors.end_date)"></label>
          <label v-if="service === 'transfers'">{{ copy.time }}<input v-model="form.departure_time" type="time" required :aria-invalid="Boolean(fieldErrors.departure_time)"></label>
          <label>{{ copy.adults }}<input v-model.number="form.adults" type="number" min="1" max="9" required :aria-invalid="Boolean(fieldErrors.adults)"></label>
          <label v-if="service === 'hotels'">{{ copy.rooms }}<input v-model.number="form.rooms" type="number" min="1" :max="form.adults" required :aria-invalid="Boolean(fieldErrors.rooms)"></label>
          <label v-if="service === 'hotels'">{{ copy.country }}<input v-model.trim="form.booker_country" pattern="[A-Za-z]{2}" minlength="2" maxlength="2" required :aria-invalid="Boolean(fieldErrors.booker_country)"></label>
          <label v-if="service === 'flights'">{{ copy.cabin }}<select v-model="form.cabin"><option v-for="cabin in ['economy', 'premium_economy', 'business', 'first']" :key="cabin" :value="cabin">{{ copy[cabin] }}</option></select></label>
          <label v-if="['hotels', 'activities'].includes(service)">{{ copy.currency }}<select v-model="form.currency"><option>USD</option><option>EUR</option><option>GBP</option></select></label>
        </div>
        <div v-if="service !== 'places'" class="travel-children">
          <label v-for="(_, index) in form.children" :key="index">{{ copy.age }} {{ index + 1 }}<input v-model.number="form.children[index]" type="number" min="0" max="17" required><button type="button" :aria-label="`${copy.remove} ${index + 1}`" @click="form.children.splice(index, 1)"><X :size="15" /></button></label>
          <button v-if="form.children.length < 6" type="button" class="travel-text-button" @click="form.children.push(5)"><Plus :size="15" /> {{ copy.addChild }}</button>
        </div>
        <div class="travel-search-actions"><p>{{ copy.requestNote }}</p><button class="button-primary" :disabled="loading"><Loader2 v-if="loading" :size="18" class="spin" /><Search v-else :size="18" /> {{ loading ? copy.loading : copy.search }}</button></div>
        <p v-if="service === 'transfers' && !locationSuggestions.some(item => item.label === form.destination)" class="travel-notice">{{ transferHint }}</p>
        <p v-if="locationSource" class="travel-attribution"><a href="https://www.geoapify.com/" target="_blank" rel="noopener noreferrer">Geoapify</a> · <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a></p>
        <p v-if="errorMessage" class="travel-error" role="alert">{{ errorMessage }}</p>
      </form>
      <section ref="resultsEl" class="travel-results" aria-live="polite">
        <template v-if="result">
          <p v-for="source in result.sources.filter(s => s.status !== 'available')" :key="source.name" class="travel-notice">{{ source.name }} · {{ ['location_required', 'airport_required'].includes(source.status) ? copy.locationRequired : copy.unavailable }}</p>
          <div v-if="result.data.length" class="travel-results-heading"><h2>{{ copy.results }}</h2><p>{{ copy.pricing }}</p></div>
          <div v-if="result.data.length" class="travel-offers">
            <article v-for="item in result.data" :key="`${item.provider}-${item.id}`" class="travel-offer">
              <div v-if="item.image" class="travel-offer-image"><img :src="item.image" :alt="item.title" loading="lazy" width="600" height="400"></div>
              <div v-else class="travel-offer-symbol"><Plane v-if="service === 'flights'" :size="34" /><MapPin v-else-if="service === 'places'" :size="34" /><Compass v-else :size="34" /></div>
              <div class="travel-offer-body"><small>{{ item.source }}</small><h3>{{ item.title }}</h3><p>{{ item.description }}</p>
                <p v-if="item.attribution" class="travel-attribution"><a href="https://www.geoapify.com/" target="_blank" rel="noopener noreferrer">Geoapify</a> · <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a></p>
                <div class="travel-offer-bottom"><div v-if="service !== 'places'"><small>{{ item.price_basis === 'total' ? copy.total : copy.from }}</small><strong>{{ price(item) }}</strong></div>
                  <NuxtLink v-if="item.action === 'details'" :to="item.url">{{ copy.details }} <ArrowUpRight :size="17" /></NuxtLink>
                  <a v-else-if="item.action === 'redirect'" :href="item.url" target="_blank" rel="noopener noreferrer sponsored">{{ copy.partner }} <ArrowUpRight :size="17" /></a>
                  <button v-else @click="request(item)">{{ copy.request }} <ArrowUpRight :size="17" /></button>
                </div>
              </div>
            </article>
          </div>
          <nav v-if="result.meta.last_page > 1" class="travel-pagination" aria-label="Pagination"><button :disabled="loading || result.meta.current_page <= 1" @click="search(result.meta.current_page - 1)">{{ copy.previous }}</button><span>{{ result.meta.current_page }} / {{ result.meta.last_page }}</span><button :disabled="loading || result.meta.current_page >= result.meta.last_page" @click="search(result.meta.current_page + 1)">{{ copy.next }}</button></nav>
          <div v-if="!result.data.length" class="travel-empty"><Compass :size="36" /><h2>{{ copy.noResults }}</h2><p>{{ copy.noResultsText }}</p><button class="button-primary" @click="request()">{{ copy.request }} <ArrowUpRight :size="18" /></button></div>
        </template>
        <div v-else class="travel-concierge"><div><p class="section-eyebrow">GoVista concierge</p><h2>{{ copy.request }}</h2><p>{{ copy.requestNote }}</p></div><button class="button-primary" @click="request()">{{ copy.request }} <ArrowUpRight :size="18" /></button></div>
      </section>
    </div>
  </div>
</template>
