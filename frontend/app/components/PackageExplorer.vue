<script setup>
import { ArrowLeft, ArrowUpRight, Building2, CalendarDays, Check, ChevronDown, Compass, Loader2, MapPin, Palmtree, Plane, Plus, Search, ShieldCheck, Users, Waves, X } from '@lucide/vue'
const copy = usePackageText(), travel = useTravelText()
const { locale, localePath } = useLocale()
const { openBooking } = useBooking()
const api = useGovistaApi(), config = useRuntimeConfig()
const { data: options, error: optionsError, refresh: refreshOptions } = await useAsyncData('package-options', () => api('/v1/packages/options'))
const { data: initial, error: initialError } = await useAsyncData(`package-catalog-${locale.value}`, () => api('/v1/packages/offers', {query:{locale:locale.value}}))
const result = ref(initial.value), loading = ref(false), error = ref(''), fieldErrors = ref([]), resultsEl = ref(null)
const form = reactive({provider:'',destination_code:'',origin:'EVN',date_from:'',date_to:'',nights_min:1,nights_max:10,adults:2,children:[],meal_plan:'',stars:'',currency:'AMD',max_price:'',direct_only:false})
const searched = ref(null), includeLive = ref(false), liveOffers = ref([]), liveLoading = ref(false), liveMessage = ref(''), liveToken = ref(''), progress = ref(0)
const detail = ref(null), detailOpen = ref(false), detailPanel = ref(null)
useModalFocus(detailOpen, detailPanel, () => {detailOpen.value=false}, 'package-dialog-open')
let sequence=0, pollTimer
onBeforeUnmount(() => {sequence++;clearTimeout(pollTimer)})
const today = new Date().toLocaleDateString('en-CA')
const dirty = computed(() => searched.value && JSON.stringify(query()) !== JSON.stringify(searched.value))
const pickedDestinations = computed(() => ['sharm','hurghada','dubai','maldives'].map(code => options.value?.destinations.find(d=>d.code===code)).filter(Boolean))
const destinationName = code => options.value?.destinations.find(d=>d.code===code)?.names[locale.value] || ''
watch(() => form.date_from, value => {if(value && (!form.date_to || form.date_to < value)) form.date_to=value})
watch(() => form.nights_min, value => {if(form.nights_max < value) form.nights_max=value})
watch(() => form.provider, value => { if(value && value!=='tourvisor') includeLive.value=false })
function query() { return Object.fromEntries(Object.entries({locale:locale.value,provider:form.provider,destination_code:form.destination_code,origin:form.origin,date_from:form.date_from,date_to:form.date_to,nights_min:form.nights_min,nights_max:form.nights_max,adults:form.adults,children:[...form.children],meal_plan:form.meal_plan,stars:form.stars,...(form.max_price ? {currency:form.currency,max_price:form.max_price} : {})}).filter(([,value])=>value!=='')) }
const money = item => item.price == null ? copy.value.onRequest : `${new Intl.NumberFormat(locale.value==='hy' ? 'en-US' : locale.value,{maximumFractionDigits:2}).format(Number(item.price))} ${item.currency}`
const date = value => {
  if(!value) return '—'
  const instant = new Date(value.length===10 ? `${value}T12:00:00Z` : value)
  if(locale.value==='hy') {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Yerevan',day:'numeric',month:'numeric',year:'numeric'}).formatToParts(instant).map(part=>[part.type,part.value]))
    const months=['հունվ.','փետ.','մարտ','ապր.','մայիս','հունիս','հուլիս','օգոս.','սեպտ.','հոկտ.','նոյ.','դեկտ.']
    return `${parts.day} ${months[Number(parts.month)-1]} ${parts.year}`
  }
  return new Intl.DateTimeFormat(locale.value,{timeZone:'Asia/Yerevan',day:'numeric',month:'short',year:'numeric'}).format(instant)
}
const imageUrl = path => path?.startsWith('/storage/') ? `${String(config.public.apiBase).replace(/\/api\/?$/, '')}${path}` : path
const priceBasis = item => item.price_basis==='per_person' ? copy.value.perPerson : item.price_basis==='indicative_total' ? copy.value.indicative : copy.value.total
const returnDate = item => { if(item.live) return ''; const value = new Date(`${item.departure_date}T12:00:00`); value.setDate(value.getDate()+item.nights); return value.toLocaleDateString('en-CA') }
function quickDestination(code) {form.destination_code=code; document.getElementById('package-search')?.scrollIntoView({behavior:'smooth',block:'start'})}
async function search(page=1) {
  if(loading.value) return
  loading.value=true;error.value='';fieldErrors.value=[]
  if(page===1) {sequence++;clearTimeout(pollTimer);liveOffers.value=[];liveLoading.value=false;liveMessage.value='';liveToken.value='';progress.value=0}
  const seq=sequence, input=page===1 ? query() : searched.value || {locale:locale.value}
  try { result.value=await api('/v1/packages/offers',{query:{...input,page},retry:0}); searched.value=input; initialError.value=null; await nextTick();resultsEl.value?.scrollIntoView({behavior:'smooth',block:'start'}); if(page===1 && includeLive.value && options.value?.live_search) await startLive(seq,input) }
  catch(e) {error.value=copy.value.error; fieldErrors.value=Object.values(e.data?.errors || {}).flat(); result.value=null}
  finally {loading.value=false}
}
async function startLive(seq,input) {
  const difference = input.date_from && input.date_to ? (new Date(input.date_to)-new Date(input.date_from))/86400000 : -1
  if(input.origin!=='EVN' || !input.destination_code || difference<0 || difference>20 || input.adults>6 || input.children.length>3 || input.nights_max-input.nights_min>10) {liveMessage.value=copy.value.liveRequirements;return}
  liveLoading.value=true
  try {const response=await api('/v1/packages/search',{method:'POST',body:{locale:locale.value,origin:'EVN',destination_code:input.destination_code,date_from:input.date_from,date_to:input.date_to,nights_min:input.nights_min,nights_max:input.nights_max,adults:input.adults,children:input.children,stars:input.stars || null,direct_only:form.direct_only},retry:0}); if(seq!==sequence)return; if(!response.available){liveMessage.value=copy.value.noMapping;liveLoading.value=false;return}liveToken.value=response.token;poll(seq,input,0)}
  catch {if(seq===sequence){liveLoading.value=false;liveMessage.value=copy.value.liveUnavailable}}
}
async function poll(seq,input,attempt) {
  try {const response=await api(`/v1/packages/search/${liveToken.value}`,{retry:0,timeout:20000});if(seq!==sequence)return;progress.value=response.progress;liveOffers.value=response.data.filter(item=>(!input.meal_plan || item.meal_plan===input.meal_plan) && (!input.max_price || (item.currency===input.currency && item.price!=null && Number(item.price)<=Number(input.max_price))));
    if(response.complete || attempt>=18){liveLoading.value=false;if(!response.complete)liveMessage.value=copy.value.livePartial;return}
    pollTimer=setTimeout(()=>poll(seq,input,attempt+1),3500)
  } catch {if(seq===sequence){liveLoading.value=false;liveMessage.value=copy.value.liveUnavailable}}
}
async function showOffer(item) {
  error.value=''
  try {detail.value=item.live ? item : await api(`/v1/packages/offers/${item.id}`,{query:{locale:locale.value},retry:0});detailOpen.value=true}
  catch {error.value=copy.value.expired}
}
async function request(item=null) {
  const details={service:'packages',origin:form.origin,adults:Number(form.adults),children:[...form.children],nights_min:Number(form.nights_min),nights_max:Number(form.nights_max),...(form.destination_code ? {destination_code:form.destination_code,destination:destinationName(form.destination_code)} : {}),...(form.provider ? {provider:form.provider} : {}),...(form.meal_plan ? {meal_plan:form.meal_plan} : {}),...(form.stars ? {stars:Number(form.stars)} : {}),...(form.date_from && form.date_to ? {date_to:form.date_to} : {}),...(includeLive.value ? {direct_only:form.direct_only} : {})}
  let start=form.date_from,end='',title=`${copy.value.custom}${form.destination_code ? ` · ${destinationName(form.destination_code)}` : ''}`
  if(item) {delete details.date_to; Object.assign(details,{origin:item.origin,destination:item.destination,destination_code:item.destination_code,provider:item.provider,offer_id:String(item.id),adults:item.adults,children:[...item.children],nights_min:item.nights,nights_max:item.nights,...(item.live ? {search_token:liveToken.value} : {package_offer_id:item.id})}); title=item.title;start=item.departure_date;end=returnDate(item)}
  detailOpen.value=false;await nextTick()
  openBooking({title,type:'package'},{start_date:start,end_date:end,participants:details.adults+details.children.length,request_details:details})
}
</script>
<template>
  <div class="travel-page package-page">
    <section class="container package-hero"><div><NuxtLink :to="localePath('/travel')" class="travel-back"><ArrowLeft :size="16"/>{{travel.allServices}}</NuxtLink><p class="section-eyebrow">{{copy.eyebrow}}</p><h1>{{copy.title}}<span>.</span></h1><p class="package-lead">{{copy.subtitle}}</p></div><div class="package-hero-art" aria-hidden="true"><div class="package-sun"></div><div class="package-orbit"></div><Plane :size="48" class="package-hero-plane"/><span>EVN</span><strong>GoVista</strong><small>Travel beyond limits</small></div></section>
    <div class="container">
      <section class="package-destinations" :aria-label="copy.destinations"><button v-for="destination in pickedDestinations" :key="destination.code" :class="['package-destination',`destination-${destination.code}`]" :aria-pressed="form.destination_code===destination.code" @click="quickDestination(destination.code)"><Building2 v-if="destination.code==='dubai'" :size="25"/><Palmtree v-else-if="destination.code==='maldives'" :size="25"/><Waves v-else :size="25"/><span><small>{{destination.country}}</small><strong>{{destination.names[locale]}}</strong></span><ArrowUpRight :size="18"/></button></section>
      <p v-if="optionsError || initialError" class="travel-error" role="alert">{{copy.error}} <button class="travel-text-button" @click="refreshOptions();search()">{{copy.retry}}</button></p>
      <form id="package-search" class="travel-search-form package-search" :aria-busy="loading" @submit.prevent="search()">
        <div class="package-search-heading"><h2>{{copy.destinations}}</h2><span><Plane :size="15"/> {{form.origin || 'EVN'}}</span></div>
        <div class="travel-fields">
          <label>{{travel.origin}}<input v-model.trim="form.origin" required pattern="[A-Z]{3}" maxlength="3" placeholder="EVN"></label>
          <label>{{travel.destination}}<select v-model="form.destination_code"><option value="">{{copy.allDestinations}}</option><option v-for="destination in options?.destinations || []" :key="destination.code" :value="destination.code">{{destination.names[locale]}}</option></select></label>
          <label>{{copy.departureFrom}}<input v-model="form.date_from" type="date" :min="today"></label><label>{{copy.departureTo}}<input v-model="form.date_to" type="date" :min="form.date_from || today" :required="Boolean(form.date_from)"></label>
          <label>{{copy.nightsMin}}<input v-model.number="form.nights_min" type="number" min="1" max="28" required></label><label>{{copy.nightsMax}}<input v-model.number="form.nights_max" type="number" :min="form.nights_min" max="28" required></label>
          <label>{{travel.adults}}<input v-model.number="form.adults" type="number" min="1" max="9" required></label><label>{{copy.providers}}<select v-model="form.provider"><option value="">{{copy.allProviders}}</option><option value="local">GoVista</option><option v-for="provider in options?.providers || []" :key="provider.code" :value="provider.code">{{provider.name}}</option></select></label>
        </div>
        <div class="travel-children"><label v-for="(_,index) in form.children" :key="index">{{travel.age}} {{index+1}}<input v-model.number="form.children[index]" type="number" min="0" max="17" required><button type="button" :aria-label="`${travel.remove} ${index+1}`" @click="form.children.splice(index,1)"><X :size="15"/></button></label><button v-if="form.children.length<6" type="button" class="travel-text-button" @click="form.children.push(5)"><Plus :size="15"/>{{travel.addChild}}</button></div>
        <details class="package-extra-filters"><summary>{{copy.meal}} · {{copy.hotelStars}} · {{travel.currency}} <ChevronDown :size="16"/></summary><div class="travel-fields"><label>{{copy.meal}}<select v-model="form.meal_plan"><option value="">{{copy.anyMeal}}</option><option v-for="(name,key) in copy.meals" :key="key" :value="key">{{name}}</option></select></label><label>{{copy.hotelStars}}<select v-model="form.stars"><option value="">{{copy.anyStars}}</option><option v-for="n in 5" :key="n" :value="n">{{n}} ★ +</option></select></label><label>{{copy.budget}}<input v-model.number="form.max_price" type="number" min="1" max="999999999.99"></label><label>{{travel.currency}}<select v-model="form.currency"><option v-for="code in ['AMD','USD','EUR','RUB','GBP','AED']" :key="code">{{code}}</option></select></label></div><p>{{copy.budgetHint}}</p></details>
        <div v-if="options?.live_search && (!form.provider || form.provider==='tourvisor')" class="package-live-controls"><label><input v-model="includeLive" type="checkbox">{{copy.liveAction}} · Tourvisor</label><label v-if="includeLive"><input v-model="form.direct_only" type="checkbox">{{copy.direct}}</label></div>
        <p class="package-provider-note">{{copy.providerNote}}</p>
        <div class="travel-search-actions"><p><ShieldCheck :size="17"/>{{travel.requestNote}}</p><button class="button-primary" :disabled="loading"><Loader2 v-if="loading" class="spin" :size="18"/><Search v-else :size="18"/>{{loading ? travel.loading : travel.search}}</button></div>
      </form>
      <section ref="resultsEl" class="travel-results package-results" aria-live="polite">
        <p v-if="error" class="travel-error" role="alert">{{error}}</p><ul v-if="fieldErrors.length" class="travel-error"><li v-for="message in fieldErrors" :key="message">{{message}}</li></ul><p v-if="dirty" class="travel-notice">{{copy.changed}}</p>
        <div class="travel-results-heading"><div><p class="section-eyebrow">GoVista selection</p><h2>{{copy.published}}</h2></div><span v-if="result" class="package-count">{{result.meta.total}} {{copy.count}}</span></div><p class="package-list-note">{{copy.manualNote}}</p>
        <div v-if="result?.data.length" class="package-offer-grid"><article v-for="item in result.data" :key="item.id" class="package-offer-card"><div v-if="item.image" class="package-offer-visual"><img :src="imageUrl(item.image)" :alt="item.title" loading="lazy" width="600" height="380"></div><div v-else class="package-offer-visual package-offer-placeholder" aria-hidden="true"><Palmtree :size="44"/><span>{{item.origin}} → {{item.destination}}</span></div><div class="package-offer-content"><div class="package-offer-source"><small>{{item.source}}</small><span v-if="item.stars">{{item.stars}} ★</span></div><h3>{{item.title}}</h3><p class="package-hotel" v-if="item.hotel_name">{{item.hotel_name}}</p><div class="package-offer-facts"><span><CalendarDays :size="15"/>{{date(item.departure_date)}}</span><span>{{item.nights}} {{copy.nights}}</span><span><Users :size="15"/>{{item.adults}} {{copy.adults}}<template v-if="item.children.length"> · {{item.children.length}} {{copy.children}}</template></span><span>{{copy.meals[item.meal_plan] || item.meal_plan}}</span></div><div class="package-inclusion-tags"><span v-for="key in item.inclusions.slice(0,4)" :key="key"><Check :size="12"/>{{copy.inclusions[key]}}</span></div><div class="package-offer-footer"><div><small>{{priceBasis(item)}}</small><strong>{{money(item)}}</strong></div><button @click="showOffer(item)" :aria-label="`${copy.details}: ${item.title}`">{{copy.details}}<ArrowUpRight :size="17"/></button></div></div></article></div>
        <div v-if="result && !result.data.length && !loading" class="package-empty"><div class="package-empty-icon"><Compass :size="32"/></div><h3>{{copy.noOffers}}</h3><p>{{copy.emptyText}}</p><button class="button-primary" @click="request()">{{copy.request}}<ArrowUpRight :size="17"/></button></div>
        <nav v-if="result?.meta.last_page>1" class="travel-pagination" :aria-label="travel.results"><button :disabled="loading || result.meta.current_page<=1" @click="search(result.meta.current_page-1)">{{travel.previous}}</button><span>{{result.meta.current_page}} / {{result.meta.last_page}}</span><button :disabled="loading || result.meta.current_page>=result.meta.last_page" @click="search(result.meta.current_page+1)">{{travel.next}}</button></nav>
      </section>
      <section v-if="liveLoading || liveOffers.length || liveMessage" class="package-live-results" aria-live="polite"><h2>{{copy.live}}</h2><p>{{copy.livePricing}}</p><p v-if="liveLoading" class="travel-notice"><Loader2 :size="16" class="spin"/>{{copy.liveLoading}} {{progress}}%</p><p v-if="liveMessage" class="travel-notice">{{liveMessage}}</p><div class="package-offer-grid"><article v-for="item in liveOffers" :key="item.id" class="package-offer-card"><div class="package-offer-content"><div class="package-offer-source"><small>{{item.source}} · {{item.operator}}</small><span v-if="item.stars">{{item.stars}} ★</span></div><h3>{{item.title}}</h3><p class="package-hotel">{{item.description}}</p><div class="package-offer-facts"><span>{{date(item.departure_date)}}</span><span>{{item.nights}} {{copy.nights}}</span><span>{{item.adults}} {{copy.adults}} · {{item.children.length}} {{copy.children}}</span><span>{{copy.meals[item.meal_plan] || item.meal_plan}}</span></div><div class="package-offer-footer"><div><small>{{copy.indicative}}</small><strong>{{money(item)}}</strong></div><button @click="showOffer(item)">{{copy.details}}<ArrowUpRight :size="17"/></button></div></div></article></div></section>
      <div class="travel-concierge package-concierge"><div><p class="section-eyebrow">Made for you</p><h2>{{copy.custom}}</h2><p>{{copy.customText}}</p><NuxtLink :to="localePath('/international-tours')">{{copy.catalogue}} <ArrowUpRight :size="15"/></NuxtLink></div><button class="button-primary" @click="request()">{{copy.request}}<ArrowUpRight :size="18"/></button></div>
    </div>
    <Teleport to="body"><div v-if="detailOpen && detail" class="package-dialog-backdrop" @click.self="detailOpen=false"><section ref="detailPanel" role="dialog" aria-modal="true" aria-labelledby="package-dialog-title" tabindex="-1" class="package-dialog"><button class="package-dialog-close" :aria-label="copy.close" @click="detailOpen=false"><X :size="22"/></button><div v-if="detail.image" class="package-dialog-image"><img :src="imageUrl(detail.image)" :alt="detail.title"></div><div class="package-dialog-body"><p class="section-eyebrow">{{detail.source}}<template v-if="detail.operator"> · {{detail.operator}}</template></p><h2 id="package-dialog-title">{{detail.title}}</h2><p class="package-dialog-route"><Plane :size="18"/>{{detail.origin}} → {{detail.destination}} · {{detail.nights}} {{copy.nights}}</p><p>{{date(detail.departure_date)}}<template v-if="returnDate(detail)"> — {{date(returnDate(detail))}}</template></p><p>{{detail.adults}} {{copy.adults}} · {{detail.children.length}} {{copy.children}}<template v-if="detail.children.length"> ({{detail.children.join(', ')}})</template></p><p v-if="detail.hotel_name">{{detail.hotel_name}}<template v-if="detail.stars"> · {{detail.stars}} ★</template></p><p>{{copy.meals[detail.meal_plan] || detail.meal_plan}}<template v-if="detail.room_type"> · {{copy.room}}: {{detail.room_type}}</template></p><p v-if="detail.description" class="travel-preserve">{{detail.description}}</p><div class="package-dialog-price"><small>{{priceBasis(detail)}}</small><strong>{{money(detail)}}</strong><p>{{detail.live ? copy.livePricing : copy.manualNote}}</p><p v-if="detail.fuel_charge">{{copy.fuel}}: {{detail.fuel_charge}} {{detail.currency || ''}}</p><small v-if="detail.valid_until">{{copy.until}} {{date(detail.valid_until)}}</small><small v-if="detail.price_checked_at">{{copy.checked}} {{date(detail.price_checked_at)}}</small></div><h3>{{copy.includes}}</h3><ul v-if="detail.inclusions.length" class="package-detail-inclusions"><li v-for="key in detail.inclusions" :key="key"><Check :size="16"/>{{copy.inclusions[key]}}</li></ul><p v-else>{{copy.needsConfirmation}}</p><h3>{{copy.excludes}}</h3><p class="travel-preserve">{{detail.exclusions || copy.needsConfirmation}}</p><h3>{{copy.terms}}</h3><p class="travel-preserve">{{detail.terms || copy.needsConfirmation}}</p><button class="button-primary" @click="request(detail)">{{copy.request}}<ArrowUpRight :size="18"/></button></div></section></div></Teleport>
  </div>
</template>
