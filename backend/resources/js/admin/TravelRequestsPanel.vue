<script setup>
import { onMounted, onBeforeUnmount, ref, reactive, computed } from 'vue';
import { ArrowLeft, ArrowUpRight, CalendarCheck2, Loader2, Plus, RefreshCw, X } from '@lucide/vue';
const props = defineProps({ api: { type: Function, required: true } });
const rows = ref(null), selected = ref(null), reports = ref(null), busy = ref(false), error = ref(''), notice = ref('');
const search = ref(''), status = ref('');
const form = reactive({ action: 'review', note: '', internal: false, total_price: '', currency: 'AMD', quote_terms: '', quote_expires_at: '', confirmation_reference: '', components: [], component_references: [], component_index: 0, component_status: 'pending', supplier_reference: '' });
const statuses = { new: 'Նոր հայտ', reviewing: 'Մշակվում է', quoted: 'Առաջարկված է', accepted: 'Հաճախորդը համաձայնել է', confirmed: 'Հաստատված է', completed: 'Ավարտված է', cancelled: 'Չեղարկված է', cancellation_requested: 'Չեղարկման հարցում' };
const actions = { review: 'Սկսել մշակումը', quote: 'Ուղարկել առաջարկ անձնական բաժին', accept_guest: 'Գրանցել հյուրի համաձայնությունը', confirm: 'Գրանցել իրական հաստատումը', complete: 'Ավարտել', cancel: 'Չեղարկել', note: 'Ավելացնել նշում', component: 'Թարմացնել փաթեթի բաղադրիչը' };
const allowed = { new: ['review', 'quote', 'note', 'cancel'], reviewing: ['quote', 'note', 'cancel'], quoted: ['quote', 'accept_guest', 'note', 'cancel'], accepted: ['confirm', 'component', 'note', 'cancel'], confirmed: ['complete', 'note', 'cancel'], completed: ['note'], cancelled: ['note'], cancellation_requested: ['component', 'note', 'cancel'] };
const types = { flight: 'Ավիատոմս', accommodation: 'Կացարան', transport: 'Մեքենա', transfer: 'Տրանսֆեր', tour: 'Տուր', activity: 'Էքսկուրսիա', custom: 'Այլ' };
const detailLabels = { origin: 'Մեկնում', destination: 'Ուղղություն', adults: 'Մեծահասակներ', children: 'Երեխաների տարիքներ', rooms: 'Սենյակներ', cabin: 'Դաս', departure_time: 'Ժամ', provider: 'Աղբյուր', offer_id: 'Առաջարկի ID', service: 'Ծառայություն' };
Object.assign(detailLabels, {destination_code:'Ուղղության կոդ', nights_min:'Գիշերներ՝ նվազագույն', nights_max:'Գիշերներ՝ առավելագույն', meal_plan:'Սնունդ', package_offer_id:'Կատալոգի առաջարկ', supplier_reference:'Մատակարարի ներքին համար', date_to:'Մեկնման վերջնաժամկետ', stars:'Հյուրանոցի նվազագույն կարգ',direct_only:'Միայն ուղիղ թռիչք'});
const displayDetails = computed(() => Object.fromEntries(Object.entries(selected.value?.request_details || {}).filter(([key]) => key !== 'package_snapshot')));
let sequence = 0;
onBeforeUnmount(() => { sequence++; });
async function load(page = 1) {
  const seq = ++sequence; busy.value = true; error.value = '';
  try {
    const [list, summary] = await Promise.all([props.api.get('/admin/travel/requests', { params: { page, search: search.value, status: status.value } }), props.api.get('/admin/travel/reports')]);
    if (seq !== sequence) return;
    rows.value = list.data; reports.value = summary.data;
  } catch { if (seq === sequence) error.value = 'Չհաջողվեց բեռնել հայտերը։'; }
  finally { if (seq === sequence) busy.value = false; }
}
onMounted(load);
function resetForm(order) {
  selected.value = order;
  const expiry = new Date(Date.now() + 48 * 3600_000); expiry.setMinutes(expiry.getMinutes() - expiry.getTimezoneOffset());
  Object.assign(form, { action: (allowed[order.status] || ['note'])[0], note: '', internal: false, total_price: order.total_price || '', currency: order.currency || 'AMD', quote_terms: order.quote_terms || '', quote_expires_at: expiry.toISOString().slice(0, 16), confirmation_reference: order.confirmation_reference || '', components: structuredClone(order.components || []), component_references: (order.components || []).map(c => c.supplier_reference || '') });
}
async function open(id) {
  busy.value = true; error.value = ''; notice.value = '';
  try { const { data } = await props.api.get(`/admin/travel/requests/${id}`); resetForm(data); }
  catch { error.value = 'Չհաջողվեց բացել հայտը։'; }
  finally { busy.value = false; }
}
function addComponent() { form.components.push({ type: 'accommodation', title: '', cost: 0, sell_price: 0, currency: form.currency, status: 'pending', supplier_reference: '' }); }
function componentTotal() { form.total_price = form.components.reduce((total, c) => total + Number(c.sell_price || 0), 0).toFixed(2); }
async function save() {
  if (busy.value) return;
  busy.value = true; error.value = ''; notice.value = '';
  const payload = { version: selected.value.version, action: form.action, note: form.note, internal: form.internal };
  if (form.action === 'quote') Object.assign(payload, { total_price: form.total_price, currency: form.currency, quote_terms: form.quote_terms, quote_expires_at: new Date(form.quote_expires_at).toISOString(), components: form.components });
  if (form.action === 'confirm') Object.assign(payload, { confirmation_reference: form.confirmation_reference, component_references: form.component_references });
  if (form.action === 'component') Object.assign(payload, { component_index: form.component_index, component_status: form.component_status, supplier_reference: form.supplier_reference });
  try { const { data } = await props.api.put(`/admin/travel/requests/${selected.value.id}`, payload); resetForm(data); notice.value = 'Փոփոխությունը պահպանվել է։'; }
  catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Պահպանումը չհաջողվեց։'; }
  finally { busy.value = false; }
}
</script>
<template>
  <section class="platform-panel">
    <p v-if="error" class="platform-error" role="alert">{{ error }}</p><p v-if="notice" class="platform-success" role="status">{{ notice }}</p>
    <template v-if="!selected">
      <div class="platform-intro"><CalendarCheck2 :size="30" /><div><h2>Հայտեր և վաճառքի ընթացք</h2><p>Մշակեք պահանջները, կազմեք առաջարկներ և գրանցեք իրական հաստատումները։</p></div></div>
      <div v-if="reports" class="platform-metrics"><article><span>Գրանցված հաճախորդներ</span><strong>{{ reports.customers }}</strong></article><article v-for="total in reports.currencies" :key="total.currency"><span>Հաստատված պատվերների արժեք · {{ total.currency }}</span><strong>{{ Number(total.confirmed_value).toLocaleString('hy-AM') }}</strong><small>{{ total.orders }} պատվեր · վճարումների հաշվետվություն չէ</small></article></div>
      <form class="platform-filters" @submit.prevent="load()"><input v-model.trim="search" placeholder="Համար, անուն կամ էլ․ փոստ" maxlength="120"><select v-model="status"><option value="">Բոլոր կարգավիճակները</option><option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option></select><button class="button button-primary" :disabled="busy"><Loader2 v-if="busy" class="spin" :size="16" /> Որոնել</button></form>
      <div v-if="rows?.data.length" class="platform-request-list"><button v-for="row in rows.data" :key="row.id" :disabled="busy" @click="open(row.id)"><div><small>{{ row.reference }} · {{ row.type }}</small><h3>{{ row.item_title || 'Անհատական հայտ' }}</h3><p>{{ row.name }} · {{ row.email }}</p></div><span class="status-chip">{{ statuses[row.status] }}</span><ArrowUpRight :size="20" /></button></div>
      <p v-else-if="!busy" class="platform-note">Հայտեր չեն գտնվել։</p>
      <div v-if="rows?.last_page > 1" class="platform-actions"><button class="button button-ghost" :disabled="busy || rows.current_page === 1" @click="load(rows.current_page - 1)">Նախորդ</button><span>{{ rows.current_page }} / {{ rows.last_page }}</span><button class="button button-ghost" :disabled="busy || rows.current_page === rows.last_page" @click="load(rows.current_page + 1)">Հաջորդ</button></div>
    </template>
    <template v-else>
      <div class="platform-actions"><button class="button button-ghost" :disabled="busy" @click="selected = null; notice = ''; load(rows?.current_page || 1)"><ArrowLeft :size="16" /> Հայտեր</button><button class="button button-ghost" :disabled="busy" @click="open(selected.id)"><RefreshCw :size="16" /> Թարմացնել</button></div>
      <div class="platform-request-detail"><div><small>{{ selected.reference }}</small><h2>{{ selected.item_title || 'Անհատական հայտ' }}</h2><span class="status-chip">{{ statuses[selected.status] }}</span></div><dl><dt>Հաճախորդ</dt><dd>{{ selected.name }}</dd><dt>Էլ․ փոստ</dt><dd>{{ selected.email }}</dd><dt>Հեռախոս</dt><dd>{{ selected.phone || '—' }}</dd><dt>Ամսաթվեր</dt><dd>{{ selected.start_date?.slice(0, 10) || '—' }} / {{ selected.end_date?.slice(0, 10) || '—' }}</dd><dt>Մասնակիցներ</dt><dd>{{ selected.participants }}</dd><template v-for="(value, key) in displayDetails" :key="key"><dt>{{ detailLabels[key] || key }}</dt><dd>{{ Array.isArray(value) ? value.join(', ') : value }}</dd></template><dt>Հայտի տեքստ</dt><dd class="platform-preserve">{{ selected.message || '—' }}</dd></dl><p v-if="selected.confirmation_reference">Հաստատում՝ <strong>{{ selected.confirmation_reference }}</strong></p></div>
      <section v-if="selected.request_details?.package_snapshot" class="package-snapshot"><h3>Հայտին կցված փաթեթի տվյալները</h3><p>{{selected.request_details.package_snapshot.source}} · {{selected.request_details.package_snapshot.hotel_name}}</p><p>{{selected.request_details.package_snapshot.nights}} գիշեր · {{selected.request_details.package_snapshot.meal_plan}} · {{selected.request_details.package_snapshot.room_type}}</p><p>{{selected.request_details.package_snapshot.price || 'Գինը հարցմամբ'}} {{selected.request_details.package_snapshot.currency}} · {{selected.request_details.package_snapshot.price_basis==='per_person' ? 'մեկ անձի համար' : 'խմբի համար'}}</p><p class="platform-preserve">{{selected.request_details.package_snapshot.terms}}</p><p class="platform-preserve">{{selected.request_details.package_snapshot.exclusions}}</p><p>Սա հայտի պահին պահպանված պատճենն է։ Նախքան վերջնական առաջարկը վերահաստատեք գինը և պայմանները։</p></section>
      <div class="platform-workspace">
        <form class="platform-editor" @submit.prevent="save">
          <h3>Հաջորդ քայլը</h3><label class="platform-field">Գործողություն<select v-model="form.action" :disabled="busy"><option v-for="action in (allowed[selected.status] || ['note']).filter(a => a !== 'accept_guest' || !selected.user_id)" :key="action" :value="action">{{ actions[action] }}</option></select></label>
          <template v-if="form.action === 'quote'">
            <div class="platform-form-row"><label class="platform-field">Ընդհանուր գին<input v-model="form.total_price" type="number" min="0.01" max="99999999.99" step="0.01" required></label><label class="platform-field">Արժույթ<select v-model="form.currency"><option v-for="code in ['AMD', 'USD', 'EUR', 'RUB', 'GBP', 'AED']" :key="code">{{ code }}</option></select></label></div>
            <label class="platform-field">Առաջարկն ուժի մեջ է մինչև<input v-model="form.quote_expires_at" type="datetime-local" required></label><label class="platform-field">Ներառված ծառայություններ և չեղարկման պայմաններ<textarea v-model="form.quote_terms" rows="5" maxlength="6000" required></textarea></label>
            <div class="platform-component-heading"><h4>Փաթեթի բաղադրիչներ</h4><button type="button" :disabled="form.components.length >= 20" @click="addComponent"><Plus :size="16" /> Ավելացնել</button></div>
            <div v-for="(component, index) in form.components" :key="index" class="platform-component"><button type="button" class="component-remove" aria-label="Հեռացնել բաղադրիչը" @click="form.components.splice(index, 1)"><X :size="16" /></button><label class="platform-field">Տեսակ<select v-model="component.type"><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></label><label class="platform-field">Անվանում<input v-model="component.title" required maxlength="190"></label><div class="platform-form-row"><label class="platform-field">Ինքնարժեք<input v-model.number="component.cost" type="number" min="0" step="0.01" required></label><label class="platform-field">Վաճառքի գին<input v-model.number="component.sell_price" type="number" min="0" step="0.01" required></label></div><label class="platform-field">Արժույթ<select v-model="component.currency"><option v-for="code in ['AMD', 'USD', 'EUR', 'RUB', 'GBP', 'AED']" :key="code">{{ code }}</option></select></label><small>Տարբերություն՝ {{ (Number(component.sell_price) - Number(component.cost)).toFixed(2) }} {{ component.currency }} · ինքնարժեքը հաճախորդին չի ցուցադրվում</small></div>
            <button v-if="form.components.length" type="button" class="button button-ghost" @click="componentTotal">Ընդհանուր գինը վերցնել բաղադրիչներից</button>
            <p class="provider-footnote">Բոլոր բաղադրիչները պետք է ունենան առաջարկի արժույթը։ Գների գումարը պետք է հավասար լինի ընդհանուր գնին։</p>
          </template>
          <template v-if="form.action === 'confirm'"><p class="platform-note">Հաստատեք միայն մատակարարից փաստացի հաստատում ստանալուց հետո։ Այս գործողությունը չի թողարկում տոմս և գումար չի գանձում։</p><label class="platform-field">Հաստատման համար<input v-model="form.confirmation_reference" required maxlength="190"></label><label v-for="(component, index) in selected.components" :key="index" class="platform-field">{{ component.title }} · մատակարարի հաստատման համար<input v-model="form.component_references[index]" required maxlength="190"></label></template>
          <template v-if="form.action === 'component'"><label class="platform-field">Բաղադրիչ<select v-model.number="form.component_index"><option v-for="(component, index) in selected.components" :key="index" :value="index">{{ component.title }} · {{ component.status }}</option></select></label><label class="platform-field">Փաստացի կարգավիճակ<select v-model="form.component_status"><option value="pending">Սպասում է</option><option value="confirmed">Հաստատված է</option><option value="failed">Չհաջողվեց</option><option value="cancelled">Չեղարկված է</option></select></label><label class="platform-field">Մատակարարի հաստատման համար<input v-model="form.supplier_reference" :required="form.component_status === 'confirmed'" maxlength="190"></label><p class="platform-note">Մեկ բաղադրիչի հաստատումը չի հաստատում ամբողջ փաթեթը։ Ձախողված բաղադրիչները պահանջում են առանձին մշակում։</p></template>
          <p v-if="form.action === 'accept_guest'" class="platform-note">Նշեք, թե երբ և ինչպես է հյուրն ընդունել առաջարկի գինը և պայմանները։</p>
          <p v-if="form.action === 'cancel'" class="platform-note">Նախ ավարտեք մատակարարների չեղարկումները և անհրաժեշտ վերադարձները, ապա նշեք արդյունքը։</p>
          <label class="platform-field">Հաղորդագրություն / նշում<textarea v-model="form.note" rows="4" maxlength="3000" :required="['note', 'accept_guest'].includes(form.action)"></textarea></label><label v-if="form.action === 'note'" class="platform-check"><input v-model="form.internal" type="checkbox"> Միայն ադմինի ներքին նշում</label>
          <p class="provider-footnote">Հանրային գրառումները երևում են հաճախորդի անձնական բաժնում։ Հյուրային հայտերի համար հաճախորդին տեղեկացրեք ձեր հաստատված կապի միջոցով։</p>
          <button class="button button-primary" :disabled="busy"><Loader2 v-if="busy" class="spin" :size="16" />{{ busy ? 'Պահպանվում է…' : 'Պահպանել քայլը' }}</button>
        </form>
        <aside class="platform-timeline"><h3>Փոփոխությունների պատմություն</h3><article v-for="event in selected.events" :key="event.id"><small>{{ new Date(event.created_at).toLocaleString('hy-AM') }} <b v-if="event.internal">· Ներքին</b></small><strong>{{ statuses[event.status] }}</strong><p v-if="event.message" class="platform-preserve">{{ event.message }}</p></article></aside>
      </div>
    </template>
  </section>
</template>
