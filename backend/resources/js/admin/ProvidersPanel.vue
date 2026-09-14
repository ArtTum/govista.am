<script setup>
import { onMounted, ref, reactive, computed } from 'vue';
import { Cable, ExternalLink, Loader2, Save, ShieldCheck } from '@lucide/vue';
import ProviderIntegrationPanel from './ProviderIntegrationPanel.vue';
const props = defineProps({ api: { type: Function, required: true } });
const providers = ref([]), busy = ref(''), error = ref(''), notice = ref(''), previews = reactive({}), drafts = reactive({});
const names = { api_key: 'API բանալի', affiliate_id: 'Affiliate ID', secret: 'Գաղտնի ստորագրության բանալի' };
const services = { activities: 'Էքսկուրսիաներ', hotels: 'Հյուրանոցներ', flights: 'Ավիատոմսեր', transfers: 'Տրանսֆերներ', places: 'Տեսարժան վայրեր', packages: 'Պատրաստի փաթեթներ' };
const statuses = { not_configured: 'Բանալի չկա', not_tested: 'Սպասում է ստուգման', connected: 'Կապը ստուգված է', error: 'Կապի սխալ' };
const partnerships = { not_started: 'Չի սկսվել', requested: 'Հարցումն ուղարկված է', approved: 'Պայմանագիրը հաստատված է', paused: 'Դադարեցված է' };
const filter = ref('all'), localSupplier = ref(''), destinations = ref([]), dictionaries = reactive({ departures: [], countries: [], regions: {} });
const shownProviders = computed(() => providers.value.filter(p => filter.value === 'all' || (filter.value === 'local_api' ? Boolean(p.integration) && (!localSupplier.value || p.code === localSupplier.value) : Boolean(p.package_provider) === (filter.value === 'packages'))));
function integrationUpdated(provider) {
  const index = providers.value.findIndex(p => p.code === provider.code);
  if (index >= 0) providers.value[index] = provider;
}
function setProvider(provider) {
  const index = providers.value.findIndex(p => p.code === provider.code);
  if (index >= 0) providers.value[index] = provider;
  else providers.value.push(provider);
  drafts[provider.code] = { enabled: Boolean(provider.enabled), environment: provider.environment, credentials: {}, clear_credentials: false };
  if (provider.package_provider) {
    drafts[provider.code].settings = { partnership_status: 'not_started', contract_reference: '', content_rights_confirmed: false, evn_confirmed: false, contact_name: '', contact_email: '', notes: '', departure_id: null, destination_ids: {}, ...structuredClone(provider.settings || {}) };
    for (const destination of destinations.value) drafts[provider.code].settings.destination_ids[destination.code] ||= { country_id: null, region_id: null };
  }
}
onMounted(async () => { try { const [list, options] = await Promise.all([props.api.get('/admin/providers'), props.api.get('/v1/packages/options')]); destinations.value = options.data.destinations; list.data.forEach(setProvider); } catch { error.value = 'Չհաջողվեց բեռնել կապերը։'; } });
async function save(code) {
  if (busy.value) return;
  busy.value = code; error.value = ''; notice.value = '';
  try { const payload = JSON.parse(JSON.stringify(drafts[code])); if (!providers.value.find(p => p.code === code).fields.length) delete payload.credentials; const { data } = await props.api.put(`/admin/providers/${code}`, payload); setProvider(data); delete previews[code]; notice.value = 'Կարգավորումները պահպանվել են։'; }
  catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Չհաջողվեց պահպանել կապը։'; }
  finally { busy.value = ''; }
}
async function test(code) {
  if (busy.value) return;
  busy.value = code; error.value = ''; notice.value = '';
  try { const { data } = await props.api.post(`/admin/providers/${code}/test`); setProvider(data.provider); previews[code] = data; notice.value = data.kind === 'dictionary' ? 'API-ն հասանելի է․ տեղեկատուն բեռնվել է։ Փաթեթների և EVN ծածկույթը ստուգեք առանձին։' : `Կապը աշխատում է։ Ստացվել է ${data.result_count} առաջարկ։`; }
  catch (e) { if (e.response?.data?.provider) setProvider(e.response.data.provider); error.value = e.response?.data?.message || 'Կապի ստուգումը չհաջողվեց։'; }
  finally { busy.value = ''; }
}
async function dictionary(kind, countryId = null) {
  if (busy.value) return;
  busy.value = 'dictionary'; error.value = '';
  try { const { data } = await props.api.get(`/admin/providers/tourvisor/dictionary/${kind}`, {params: { departure_id: drafts.tourvisor.settings.departure_id || undefined, country_id: countryId || undefined }}); if (kind === 'regions') dictionaries.regions[countryId] = data.data; else dictionaries[kind] = data.data; }
  catch (e) { error.value = e.response?.data?.message || 'Տեղեկատուն չի բեռնվել։'; }
  finally { busy.value = ''; }
}
</script>
<template>
  <section class="platform-panel">
    <div class="platform-intro"><Cable :size="30" /><div><h2>Ճամփորդական մատակարարներ</h2><p>Բանալիները պահվում են գաղտնագրված։ Փորձնական կապերը տեսանելի են միայն այստեղ։ Հանրային որոնումը օգտագործում է միացված իրական կապերը։</p></div></div>
    <p class="platform-note"><ShieldCheck :size="18" /> Այս կապերը որոնում և առաջարկների դիտում են ապահովում։ Վճարում, տոմսի թողարկում և մատակարարի ավտոմատ ամրագրում դեռ միացված չեն։</p>
    <nav class="provider-filters" aria-label="Մատակարարների տեսակ"><button type="button" v-for="(label, key) in {all:'Բոլորը', local_api:'Հայկական մատակարարներ · API', packages:'Տուրփաթեթներ', components:'Առանձին ծառայություններ'}" :key="key" :aria-pressed="filter === key" @click="filter = key">{{ label }}</button></nav>
    <label v-if="filter === 'local_api'" class="platform-field provider-picker">Ընտրել մատակարար<select v-model="localSupplier"><option value="">Բոլոր չորսը</option><option v-for="provider in providers.filter(p => p.integration)" :key="provider.code" :value="provider.code">{{ provider.name }}</option></select></label>
    <p v-if="error" class="platform-error" role="alert">{{ error }}</p><p v-if="notice" class="platform-success" role="status">{{ notice }}</p>
    <div :class="['provider-grid', { 'provider-grid-single': filter === 'local_api' && localSupplier }]">
      <article v-for="provider in providers" v-show="shownProviders.some(p => p.code === provider.code)" :key="provider.code" class="provider-card">
      <form @submit.prevent="save(provider.code)">
        <div class="provider-heading"><div><small>{{ provider.services.map(s => services[s]).join(' · ') }}</small><h3>{{ provider.name }}</h3></div><span :class="['status-chip', `status-${provider.status}`]">{{ provider.mode === 'manual' ? (provider.integration ? 'Ձեռքով / API կարգավորում' : 'Ձեռքով / CSV') : statuses[provider.status] }}</span></div>
        <p v-if="provider.description" class="provider-explanation">{{ provider.description }}</p>
        <a :href="provider.docs" target="_blank" rel="noopener noreferrer" class="provider-docs">Գրանցում և API փաստաթղթեր <ExternalLink :size="13" /></a>
        <a v-if="provider.portal" :href="provider.portal" target="_blank" rel="noopener noreferrer" class="provider-docs">Գործակալային հարթակ <ExternalLink :size="13" /></a>
        <fieldset v-if="provider.package_provider" class="provider-onboarding"><legend>Համագործակցության կարգավորում</legend>
          <p class="provider-footnote">{{ provider.next_step }}</p>
          <label class="platform-field">Համագործակցության փուլ<select v-model="drafts[provider.code].settings.partnership_status"><option v-for="(label, key) in partnerships" :key="key" :value="key">{{ label }}</option></select></label>
          <label class="platform-field">Պայմանագրի կամ թույլտվության համար<input v-model.trim="drafts[provider.code].settings.contract_reference" maxlength="190" placeholder="Հղում ձեր ներքին փաստաթղթին"></label>
          <div class="platform-form-row"><label class="platform-field">Կոնտակտային անձ<input v-model.trim="drafts[provider.code].settings.contact_name" maxlength="190"></label><label class="platform-field">Գործընկերոջ էլ․ փոստ<input v-model.trim="drafts[provider.code].settings.contact_email" type="email" maxlength="190"></label></div>
          <label class="platform-check"><input v-model="drafts[provider.code].settings.content_rights_confirmed" type="checkbox"> Ունենք առաջարկները և բովանդակությունը հրապարակելու իրավունք</label>
          <label class="platform-check"><input v-model="drafts[provider.code].settings.evn_confirmed" type="checkbox"> Մատակարարը հաստատել է Երևանից (EVN) առաջարկները</label>
          <label class="platform-field">Ներքին նշումներ<textarea v-model="drafts[provider.code].settings.notes" maxlength="4000" rows="3" placeholder="Պայմաններ, միջնորդավճար, հաջորդ քայլեր․ այստեղ գաղտնաբառեր մի գրեք"></textarea></label>
          <p class="provider-footnote">{{ provider.partnership_ready ? 'Կարող եք հրապարակել այս մատակարարից ստացված և ստուգված առաջարկները։' : 'Առաջարկների սևագրերը կարելի է պատրաստել հիմա։ Հրապարակման համար լրացրեք հաստատված պայմանագիրն ու հրապարակման իրավունքը։' }}</p>
        </fieldset>
        <label v-if="provider.mode !== 'manual'" class="platform-field">Միջավայր<select v-model="drafts[provider.code].environment" :disabled="Boolean(busy)"><option value="sandbox">Փորձնական (sandbox)</option><option value="live">Իրական (live)</option></select></label>
        <label v-for="field in provider.fields" :key="field" class="platform-field">{{ names[field] }}<input v-model.trim="drafts[provider.code].credentials[field]" type="password" autocomplete="new-password" maxlength="2000" :disabled="Boolean(busy)" :placeholder="provider.credential_fields_set.includes(field) ? 'Պահպանված է · թողեք դատարկ՝ պահպանելու համար' : 'Մուտքագրեք բանալին'"></label>
        <template v-if="provider.mode !== 'manual'"><label class="platform-check"><input v-model="drafts[provider.code].enabled" type="checkbox" :disabled="Boolean(busy)"> Միացնել որոնման կապը</label><label class="platform-check"><input v-model="drafts[provider.code].clear_credentials" type="checkbox" :disabled="Boolean(busy)"> Մաքրել պահպանված բանալիները</label></template>
        <details v-if="provider.code === 'tourvisor'" class="provider-mapping"><summary>EVN և ուղղությունների կոդեր</summary><p class="provider-footnote">Կոդերը վերցրեք մատակարարի տեղեկատուներից։ Ընտրեք հենց Երևանը։ Չկապված ուղղության համար աշխատում է անհատական հայտը։</p>
          <button type="button" class="button button-ghost" :disabled="Boolean(busy) || !provider.configured" @click="dictionary('departures')">Բեռնել մեկնման քաղաքները</button>
          <label class="platform-field">Երևանի (EVN) կոդ<select v-model.number="drafts[provider.code].settings.departure_id"><option :value="null">Ընտրեք Երևանը</option><option v-if="drafts[provider.code].settings.departure_id && !dictionaries.departures.some(d => d.id === drafts[provider.code].settings.departure_id)" :value="drafts[provider.code].settings.departure_id">Պահպանված կոդ՝ {{ drafts[provider.code].settings.departure_id }}</option><option v-for="item in dictionaries.departures" :key="item.id" :value="item.id">{{ item.name }} · {{ item.id }}</option></select></label>
          <button type="button" class="button button-ghost" :disabled="Boolean(busy) || !provider.configured || !drafts[provider.code].settings.departure_id" @click="dictionary('countries')">Բեռնել հասանելի երկրները</button>
          <div v-for="destination in destinations" :key="destination.code" class="provider-map-row"><strong>{{ destination.names.hy }}</strong><label class="platform-field">Երկիր<select v-model.number="drafts[provider.code].settings.destination_ids[destination.code].country_id" @change="drafts[provider.code].settings.destination_ids[destination.code].region_id = null"><option :value="null">Ընտրեք երկիրը</option><option v-if="drafts[provider.code].settings.destination_ids[destination.code].country_id && !dictionaries.countries.some(d => d.id === drafts[provider.code].settings.destination_ids[destination.code].country_id)" :value="drafts[provider.code].settings.destination_ids[destination.code].country_id">Պահպանված կոդ՝ {{ drafts[provider.code].settings.destination_ids[destination.code].country_id }}</option><option v-for="item in dictionaries.countries" :key="item.id" :value="item.id">{{ item.name }} · {{ item.id }}</option></select></label><button type="button" class="button button-ghost" :disabled="Boolean(busy) || !provider.configured || !drafts[provider.code].settings.destination_ids[destination.code].country_id" @click="dictionary('regions', drafts[provider.code].settings.destination_ids[destination.code].country_id)">Բեռնել հանգստավայրերը</button><label class="platform-field">Հանգստավայր<select v-model.number="drafts[provider.code].settings.destination_ids[destination.code].region_id"><option :value="null">Ընտրեք հանգստավայրը</option><option v-if="drafts[provider.code].settings.destination_ids[destination.code].region_id && !(dictionaries.regions[drafts[provider.code].settings.destination_ids[destination.code].country_id] || []).some(d => d.id === drafts[provider.code].settings.destination_ids[destination.code].region_id)" :value="drafts[provider.code].settings.destination_ids[destination.code].region_id">Պահպանված կոդ՝ {{ drafts[provider.code].settings.destination_ids[destination.code].region_id }}</option><option v-for="item in dictionaries.regions[drafts[provider.code].settings.destination_ids[destination.code].country_id] || []" :key="item.id" :value="item.id">{{ item.name }} · {{ item.id }}</option></select></label></div>
        </details>
        <p v-if="provider.code === 'tourvisor'" class="provider-footnote">Tourvisor-ի ստուգումները նույնպես օգտագործում են ձեր հաշիվը և քվոտան։ Փորձնական ռեժիմը սահմանափակում է հանրային որոնումը․ այն առանձին փորձնական API սերվեր չի ստեղծում։</p>
        <p v-if="provider.code === 'geoapify'" class="provider-footnote">Geoapify-ն առանձին sandbox չունի․ փորձնական ստուգումը նույնպես օգտագործում է հաշվի քվոտան։</p>
        <p v-if="drafts[provider.code].environment === 'live'" class="provider-footnote">Միացրեք պայմանագրի, օգտագործման պայմանների և ծախսերի հաստատումից հետո։ Հրապարակային որոնումները ծախսում են մատակարարի քվոտան։</p>
        <p v-if="provider.last_error" class="platform-error">{{ provider.last_error }}</p>
        <p v-if="provider.mode !== 'manual'" class="provider-footnote">Վերջին հաջող կապը՝ {{ provider.last_success_at ? new Date(provider.last_success_at).toLocaleString('hy-AM') : 'դեռ չի ստուգվել' }}</p>
        <div class="platform-actions"><button class="button button-primary" :disabled="Boolean(busy)"><Loader2 v-if="busy === provider.code" class="spin" :size="15" /><Save v-else :size="15" /> Պահպանել</button><button v-if="provider.mode !== 'manual'" class="button button-ghost" type="button" :disabled="Boolean(busy) || !provider.configured" @click="test(provider.code)">Ստուգել պահպանված կապը</button></div>
        <div v-if="previews[provider.code]" class="provider-preview"><strong>{{ previews[provider.code].result_count }} {{ previews[provider.code].kind === 'dictionary' ? 'տեղեկատուի գրառում' : 'առաջարկ' }}</strong><p v-for="item in previews[provider.code].results" :key="item.id">{{ item.title }} <span v-if="item.price">· {{ item.price }} {{ item.currency }}</span></p></div>
      </form>
      <ProviderIntegrationPanel v-if="provider.integration" :provider="provider" :api="api" @updated="integrationUpdated" />
      </article>
    </div>
  </section>
</template>
