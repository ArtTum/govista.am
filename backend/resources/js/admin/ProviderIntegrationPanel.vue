<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Cable, Loader2, Save } from '@lucide/vue';

const props = defineProps({ api: { type: Function, required: true }, provider: { type: Object, required: true } });
const emit = defineEmits(['updated']);
const environment = ref('sandbox'), busy = ref(''), error = ref(''), notice = ref('');
const drafts = reactive({}), baselines = reactive({});
const fields = { bearer: ['api_key'], api_key: ['api_key'], basic: ['username', 'password'], tourvisio: ['agency', 'username', 'password'] };
const labels = { api_key: 'API բանալի', agency: 'Agency կոդ', username: 'Օգտանուն', password: 'Գաղտնաբառ' };
const statuses = { not_configured: 'Լրացման կարիք ունի', not_tested: 'Պատրաստ է ստուգման', adapter_pending: 'Սպասում է ադապտերին', authenticated: 'API մուտքը ստուգված է', error: 'Ստուգումը չի հաջողվել' };
const adapterReady = computed(() => props.provider.integration.adapter === 'tourvisio');
const saved = computed(() => props.provider.integration.profiles[environment.value]);
const draft = computed(() => drafts[environment.value]);
const dirty = computed(() => JSON.stringify(draft.value) !== baselines[environment.value]);
const canTest = computed(() => !busy.value && !dirty.value && !saved.value.missing.length && adapterReady.value);
const canSearch = computed(() => canTest.value && saved.value.search_allowed);
function resetDraft(env, profile) {
  const { version, base_url, documentation_url, auth_type, api_key_header, access_confirmed, search_allowed, booking_allowed } = profile;
  drafts[env] = { version, base_url, documentation_url, auth_type, api_key_header, access_confirmed, search_allowed, booking_allowed, credentials: {}, clear_credentials: false };
  baselines[env] = JSON.stringify(drafts[env]);
}
watch(() => props.provider.integration, integration => {
  for (const env of ['sandbox', 'live']) {
    if (!drafts[env] || JSON.stringify(drafts[env]) === baselines[env]) resetDraft(env, integration.profiles[env]);
  }
}, { immediate: true });
const departures = ref([]), arrivals = ref([]), offers = ref(null), limited = ref(false);
const search = reactive({ departure: '', arrival: '', check_in: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10), nights: 7, adults: 2, child_ages: '', currency: 'USD', nationality: 'AM' });
watch(environment, () => { error.value = ''; notice.value = ''; departures.value = []; arrivals.value = []; offers.value = null; search.departure = ''; search.arrival = ''; });
watch(() => search.departure, () => { arrivals.value = []; search.arrival = ''; offers.value = null; });
watch(() => JSON.stringify(search), () => { offers.value = null; });
function key(location) { return JSON.stringify([location.id, location.type]); }
function location(value, prefix) { const [id, type] = JSON.parse(value); return { [`${prefix}_id`]: id, [`${prefix}_type`]: type }; }
function authChanged() { draft.value.credentials = {}; }
async function action(kind) {
  if (busy.value) return;
  busy.value = kind; error.value = ''; notice.value = '';
  const env = environment.value, url = `/admin/providers/${props.provider.code}/integration/${env}`;
  try {
    let response;
    if (kind === 'save') {
      const payload = JSON.parse(JSON.stringify(draft.value));
      response = await props.api.put(url, payload);
      resetDraft(env, response.data.integration.profiles[env]);
      emit('updated', response.data);
      departures.value = []; arrivals.value = []; offers.value = null; search.departure = ''; search.arrival = '';
      notice.value = 'API կարգավորումները պահպանված են։ Գաղտնի տվյալները հետ չեն ցուցադրվում։';
      return;
    }
    let payload = {}, endpoint = kind;
    if (kind === 'departures' || kind === 'arrivals') {
      endpoint = `dictionary/${kind}`;
      if (kind === 'arrivals') payload = location(search.departure, 'departure');
    }
    if (kind === 'search') {
      const ages = search.child_ages.trim() ? search.child_ages.split(',').map(age => age.trim()) : [];
      if (ages.length > 3 || ages.some(age => !/^\d{1,2}$/.test(age) || Number(age) > 17)) {
        error.value = 'Նշեք մինչև 3 երեխայի տարիք՝ 0–17, բաժանված ստորակետով։'; return;
      }
      payload = { ...location(search.departure, 'departure'), ...location(search.arrival, 'arrival'), check_in: search.check_in, nights: search.nights, adults: search.adults, children: ages.map(Number), currency: search.currency, nationality: search.nationality };
      offers.value = null;
    }
    response = await props.api.post(`${url}/${endpoint}`, payload, { timeout: 30000 });
    const data = response.data;
    emit('updated', data.provider);
    if (kind === 'inspect') notice.value = data.missing.length ? `Դեռ անհրաժեշտ է՝ ${data.missing.join(', ')}։` : (data.adapter_ready ? 'Կարգավորումները լրացված են։ Կարող եք ստուգել API մուտքը։' : 'Կարգավորումները լրացված են։ API փաստաթղթերով պետք է պատրաստել մատակարարի ադապտերը։');
    if (kind === 'test') notice.value = 'Մատակարարը հաստատեց API մուտքը։ Որոնման հասանելիությունը ստուգեք առանձին։';
    if (kind === 'departures') { departures.value = data.data; search.departure = ''; notice.value = `Բեռնվել է ${data.data.length} մեկնման վայր։`; }
    if (kind === 'arrivals') { arrivals.value = data.data; search.arrival = ''; notice.value = `Բեռնվել է ${data.data.length} ուղղություն։`; }
    if (kind === 'search') { offers.value = data.data; limited.value = data.limited; notice.value = `Ստացվել է ${data.count} առաջարկ։`; }
  } catch (e) {
    if (e.response?.data?.provider) emit('updated', e.response.data.provider);
    error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Հարցումը չհաջողվեց։ Կրկին փորձեք։';
  } finally { busy.value = ''; }
}
</script>

<template>
  <section class="provider-integration" :aria-label="`${provider.name} API կարգավորումներ`" :aria-busy="Boolean(busy)">
    <div class="integration-heading"><Cable :size="21" /><div><h4>API ինտեգրում</h4><p>{{ adapterReady ? 'TourVisio · մուտք և փաթեթների որոնման փորձարկում' : 'Մուտքի տվյալներ և ինտեգրման նախապատրաստում' }}</p></div></div>
    <p class="provider-footnote">{{ adapterReady ? 'Օգտագործեք TravelOne-ից ստացած TourVisio API հասցեն ու գործակալային տվյալները։ B2B կայքի մուտքը կարող է առանձին լինել։' : 'API-ի հասանելիությունն ու ձևաչափը պետք է հաստատի մատակարարը։ Պահպանված բանալիները կապ չեն ակտիվացնում․ փաստաթղթերը ստանալուց հետո պետք է ավելացնել համապատասխան ադապտերը։' }}</p>
    <div class="integration-environments" role="group" aria-label="API միջավայր">
      <button v-for="(label, env) in { sandbox: 'Փորձնական', live: 'Իրական' }" :key="env" type="button" :aria-pressed="environment === env" :disabled="Boolean(busy)" @click="environment = env">{{ label }} <small>{{ props.provider.integration.profiles[env].credential_fields_set.length ? 'Տվյալներ կան' : 'Դատարկ է' }}</small></button>
    </div>
    <div class="integration-state"><span :class="['status-chip', `status-${saved.status}`]">{{ statuses[saved.status] }}</span><small>{{ saved.checked_at ? new Date(saved.checked_at).toLocaleString('hy-AM') : 'Արտաքին ստուգում չի կատարվել' }}</small></div>
    <p class="provider-footnote">Յուրաքանչյուր միջավայրի տվյալները պահպանվում են առանձին։ Փորձնական սերվերի հասցեն պետք է տրամադրի մատակարարը․ այս ընտրությունն ինքնուրույն sandbox չի ստեղծում։</p>
    <form @submit.prevent="action('save')">
      <fieldset :disabled="Boolean(busy)" class="integration-fields">
        <label class="platform-field">API սերվերի հասցե<input v-model.trim="draft.base_url" type="url" maxlength="500" placeholder="https://supplier-api.com/v2" autocomplete="off"><small>Մատակարարի տված բազային HTTPS հասցեն՝ առանց բանալիի։ {{ adapterReady ? 'Համակարգն ավելացնում է /api/… մեթոդների ճանապարհը։' : '' }}</small></label>
        <label class="platform-field">API փաստաթղթերի հղում<input v-model.trim="draft.documentation_url" type="url" maxlength="500" placeholder="https://supplier.com/api-docs" autocomplete="off"></label>
        <label class="platform-field">Մուտքի եղանակ<select v-model="draft.auth_type" @change="authChanged"><option v-if="adapterReady" value="tourvisio">TourVisio · Agency / User / Password</option><template v-else><option value="bearer">Bearer token</option><option value="api_key">API key header</option><option value="basic">Basic · օգտանուն և գաղտնաբառ</option></template></select></label>
        <label v-if="draft.auth_type === 'api_key'" class="platform-field">Բանալիի header-ի անուն<input v-model.trim="draft.api_key_header" required pattern="X-[A-Za-z0-9-]{1,60}" maxlength="62" placeholder="X-API-Key"></label>
        <label v-for="field in fields[draft.auth_type]" :key="field" class="platform-field">{{ labels[field] }}<input v-model="draft.credentials[field]" type="password" autocomplete="new-password" maxlength="2000" :disabled="draft.clear_credentials" :placeholder="saved.credential_fields_set.includes(field) ? 'Պահպանված է · դատարկ թողնելիս մնում է' : 'Մուտքագրեք մատակարարից ստացած տվյալը'"></label>
        <p class="provider-footnote">API հասցեն կամ մուտքի եղանակը փոխելիս նախկին բանալիները մաքրվում են․ նոր հասցեի համար նորից լրացրեք դրանք։</p>
        <label class="platform-check"><input v-model="draft.access_confirmed" type="checkbox"> Մատակարարը հաստատել է այս միջավայրի API հասանելիությունը</label>
        <label class="platform-check"><input v-model="draft.search_allowed" type="checkbox"> Ունենք API-ով փաթեթների որոնման թույլտվություն</label>
        <label class="platform-check"><input v-model="draft.booking_allowed" type="checkbox"> Պայմանագրով ունենք նաև API ամրագրման թույլտվություն</label>
        <p class="provider-footnote">Ամրագրման թույլտվությունը գրանցվում է որպես պայմանագրային տեղեկություն։ Ավտոմատ ամրագրումը, վճարումը և այս չորս API-ների հանրային որոնումը դեռ միացված չեն։</p>
        <label class="platform-check"><input v-model="draft.clear_credentials" type="checkbox"> Մաքրել միայն այս միջավայրի պահպանված մուտքի տվյալները</label>
      </fieldset>
      <p v-if="dirty" class="integration-unsaved" role="status">Կան չպահպանված փոփոխություններ։ Ստուգման համար նախ պահպանեք։</p>
      <div class="platform-actions"><button class="button button-primary" :disabled="Boolean(busy)"><Loader2 v-if="busy === 'save'" class="spin" :size="15" /><Save v-else :size="15" /> Պահպանել API կարգավորումները</button><button type="button" class="button button-ghost" :disabled="Boolean(busy) || dirty" @click="action('inspect')">Ստուգել լրացվածությունը</button><button v-if="adapterReady" type="button" class="button button-ghost" :disabled="!canTest" @click="action('test')">Ստուգել API մուտքը</button></div>
    </form>
    <p v-if="saved.missing.length" class="provider-footnote">Պահպանված կարգավորումներում բացակայում է՝ {{ saved.missing.join(', ') }}։</p>
    <p v-if="error || saved.last_error" class="platform-error" role="alert">{{ error || saved.last_error }}</p>
    <p v-if="notice" class="platform-success" role="status">{{ notice }}</p>
    <p v-if="busy" class="provider-footnote" role="status"><Loader2 class="spin" :size="14" /> Հարցումն ընթացքի մեջ է…</p>
    <details v-if="adapterReady" class="integration-search">
      <summary>Փաթեթների որոնման փորձարկում</summary>
      <p class="provider-footnote">Որոնումն ու տեղեկատուների բեռնումը օգտագործում են ընտրված միջավայրի հաշիվը և քվոտան։ Գները ցուցադրվում են ամբողջ խմբի համար և պահանջում են վերահաստատում մատակարարից։</p>
      <p v-if="!canSearch && !busy" class="provider-footnote">Նախ պահպանեք API տվյալները և հաստատեք որոնման թույլտվությունը։</p>
      <form @submit.prevent="action('search')">
        <fieldset class="integration-fields" :disabled="!canSearch">
          <button type="button" class="button button-ghost" @click="action('departures')">Բեռնել մեկնման վայրերը</button>
          <label class="platform-field">Մեկնման վայր<select v-model="search.departure" required><option value="">Ընտրեք մատակարարի ցանկից</option><option v-for="item in departures" :key="key(item)" :value="key(item)">{{ item.name }} · {{ item.id }}</option></select></label>
          <button type="button" class="button button-ghost" :disabled="!search.departure" @click="action('arrivals')">Բեռնել ուղղությունները</button>
          <label class="platform-field">Ուղղություն<select v-model="search.arrival" required><option value="">Ընտրեք ուղղությունը</option><option v-for="item in arrivals" :key="key(item)" :value="key(item)">{{ item.name }} · {{ item.id }}</option></select></label>
          <div class="platform-form-row"><label class="platform-field">Մեկնման օր<input v-model="search.check_in" type="date" required :min="new Date().toISOString().slice(0,10)"></label><label class="platform-field">Գիշերներ<input v-model.number="search.nights" type="number" min="1" max="28" required></label></div>
          <div class="platform-form-row"><label class="platform-field">Մեծահասակներ<input v-model.number="search.adults" type="number" min="1" max="6" required></label><label class="platform-field">Երեխաների տարիքներ<input v-model="search.child_ages" maxlength="10" placeholder="Օրինակ՝ 4, 10"></label></div>
          <div class="platform-form-row"><label class="platform-field">Արժույթ<select v-model="search.currency"><option v-for="currency in ['USD','EUR','AMD','RUB','GBP','AED']" :key="currency">{{ currency }}</option></select></label><label class="platform-field">Քաղաքացիության կոդ<input v-model.trim="search.nationality" maxlength="2" pattern="[A-Z]{2}" required placeholder="AM"></label></div>
          <button class="button button-primary" :disabled="!search.departure || !search.arrival">Որոնել փաթեթներ</button>
        </fieldset>
      </form>
      <div v-if="offers !== null && !dirty" class="integration-results" aria-live="polite"><p v-if="!offers.length">Այս պայմաններով առաջարկ չկա։ Փորձեք այլ օր կամ ուղղություն։</p><p v-if="limited" class="provider-footnote">Ցուցադրված է արդյունքների մի մասը։ Նեղացրեք որոնման պայմանները։</p><article v-for="(offer, index) in offers" :key="`${offer.offer_id}-${index}`"><div><strong>{{ offer.hotel || 'Հյուրանոց' }} <small v-if="offer.stars">· {{ offer.stars }} ★</small></strong><p>{{ offer.room }} · {{ offer.meal }}</p><p>{{ offer.check_in }} · {{ offer.nights }} գիշեր · {{ offer.adults }} մեծահասակ<span v-if="offer.children.length"> · {{ offer.children.length }} երեխա</span></p></div><div><strong>{{ offer.price === null ? 'Գինը պետք է ճշտել' : `${Number(offer.price).toLocaleString('hy-AM')} ${offer.currency}` }}</strong><small>Ամբողջ խմբի համար</small><span>{{ offer.available ? 'Մատակարարը նշել է՝ հասանելի' : 'Հասանելիությունը հաստատված չէ' }}</span></div></article></div>
    </details>
  </section>
</template>
