<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import {
    AlertCircle,
    BedDouble,
    CalendarCheck2,
    CarFront,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    Compass,
    FileText,
    ImageIcon,
    LayoutDashboard,
    Loader2,
    LogOut,
    Mail,
    MapPinned,
    Menu,
    MessageSquareQuote,
    Newspaper,
    Pencil,
    Plus,
    Save,
    Search,
    Settings,
    Sparkles,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';

const locales = [
    { code: 'hy', label: 'Հայերեն' },
    { code: 'ru', label: 'Русский' },
    { code: 'en', label: 'English' },
];

const resources = {
    tours: {
        label: 'Տուրեր',
        singular: 'տուր',
        icon: Compass,
        columns: ['title', 'travel_scope', 'type', 'price', 'featured', 'active'],
        fields: [
            ['slug', 'Slug', 'text', true],
            ['travel_scope', 'Ուղղություն', 'select', true, [['domestic', 'Ներքին տուր'], ['international', 'Արտաքին տուր']]],
            ['type', 'Տեսակ', 'select', true, [['group', 'Խմբային'], ['private', 'Անհատական'], ['package', 'Փաթեթ']]],
            ['title', 'Անվանում', 'localized', true],
            ['subtitle', 'Ենթավերնագիր', 'localized'],
            ['description', 'Նկարագրություն', 'localized-textarea', true],
            ['location', 'Վայր', 'localized'],
            ['duration', 'Տևողություն', 'localized'],
            ['price', 'Գին', 'number', true],
            ['old_price', 'Հին գին', 'number'],
            ['currency', 'Արժույթ', 'select', true, [['AMD', 'AMD'], ['USD', 'USD'], ['EUR', 'EUR'], ['RUB', 'RUB']]],
            ['image', 'Գլխավոր նկար', 'image'],
            ['gallery', 'Պատկերասրահ (URL-ներ)', 'list'],
            ['highlights', 'Կարևոր կետեր', 'localized-list'],
            ['itinerary', 'Երթուղի', 'localized-list'],
            ['included', 'Ներառված է', 'localized-list'],
            ['excluded', 'Ներառված չէ', 'localized-list'],
            ['rating', 'Գնահատական', 'number'],
            ['review_count', 'Կարծիքների քանակ', 'number'],
            ['sort_order', 'Հերթականություն', 'number'],
            ['featured', 'Գլխավոր էջում', 'boolean'],
            ['active', 'Ակտիվ', 'boolean'],
        ],
    },
    destinations: {
        label: 'Ուղղություններ',
        singular: 'ուղղություն',
        icon: MapPinned,
        columns: ['title', 'region', 'featured', 'active'],
        fields: [
            ['slug', 'Slug', 'text', true],
            ['title', 'Անվանում', 'localized', true],
            ['region', 'Մարզ', 'localized'],
            ['description', 'Նկարագրություն', 'localized-textarea', true],
            ['image', 'Գլխավոր նկար', 'image'],
            ['gallery', 'Պատկերասրահ (URL-ներ)', 'list'],
            ['sort_order', 'Հերթականություն', 'number'],
            ['featured', 'Գլխավոր էջում', 'boolean'],
            ['active', 'Ակտիվ', 'boolean'],
        ],
    },
    services: {
        label: 'Ծառայություններ',
        singular: 'ծառայություն',
        icon: CarFront,
        columns: ['title', 'type', 'price_from', 'active'],
        fields: [
            ['slug', 'Slug', 'text', true],
            ['type', 'Տեսակ', 'select', true, [['accommodation', 'Գիշերակաց'], ['transport', 'Տրանսպորտ'], ['events', 'Միջոցառումներ'], ['custom', 'Անհատական']]],
            ['title', 'Անվանում', 'localized', true],
            ['description', 'Նկարագրություն', 'localized-textarea', true],
            ['icon', 'Icon անուն', 'text'],
            ['image', 'Գլխավոր նկար', 'image'],
            ['price_from', 'Գին՝ սկսած', 'number'],
            ['currency', 'Արժույթ', 'select', true, [['AMD', 'AMD'], ['USD', 'USD'], ['EUR', 'EUR'], ['RUB', 'RUB']]],
            ['features', 'Առավելություններ', 'localized-list'],
            ['sort_order', 'Հերթականություն', 'number'],
            ['featured', 'Գլխավոր էջում', 'boolean'],
            ['active', 'Ակտիվ', 'boolean'],
        ],
    },
    posts: {
        label: 'Բլոգ',
        singular: 'հոդված',
        icon: Newspaper,
        columns: ['title', 'category', 'published_at', 'active'],
        fields: [
            ['slug', 'Slug', 'text', true],
            ['category', 'Կատեգորիա', 'text'],
            ['title', 'Վերնագիր', 'localized', true],
            ['excerpt', 'Կարճ նկարագրություն', 'localized-textarea', true],
            ['content', 'Բովանդակություն', 'localized-editor', true],
            ['image', 'Գլխավոր նկար', 'image'],
            ['reading_time', 'Ընթերցման րոպե', 'number'],
            ['published_at', 'Հրապարակման ամսաթիվ', 'datetime'],
            ['featured', 'Գլխավոր էջում', 'boolean'],
            ['active', 'Ակտիվ', 'boolean'],
        ],
    },
    pages: {
        label: 'Էջեր',
        singular: 'էջ',
        icon: FileText,
        columns: ['title', 'slug', 'active'],
        fields: [
            ['slug', 'Slug', 'text', true],
            ['title', 'Վերնագիր', 'localized', true],
            ['content', 'Բովանդակություն', 'localized-editor', true],
            ['seo_title', 'SEO վերնագիր', 'localized'],
            ['seo_description', 'SEO նկարագրություն', 'localized-textarea'],
            ['image', 'Գլխավոր նկար', 'image'],
            ['active', 'Ակտիվ', 'boolean'],
        ],
    },
    testimonials: {
        label: 'Կարծիքներ',
        singular: 'կարծիք',
        icon: MessageSquareQuote,
        columns: ['name', 'country', 'rating', 'active'],
        fields: [
            ['name', 'Անուն', 'text', true],
            ['country', 'Երկիր', 'localized'],
            ['message', 'Կարծիք', 'localized-textarea', true],
            ['avatar', 'Լուսանկար', 'image'],
            ['rating', 'Գնահատական', 'number'],
            ['source', 'Աղբյուր', 'text'],
            ['sort_order', 'Հերթականություն', 'number'],
            ['active', 'Ակտիվ', 'boolean'],
        ],
    },
    faqs: {
        label: 'ՀՏՀ',
        singular: 'հարց',
        icon: CircleHelp,
        columns: ['question', 'category', 'active'],
        fields: [
            ['question', 'Հարց', 'localized', true],
            ['answer', 'Պատասխան', 'localized-textarea', true],
            ['category', 'Կատեգորիա', 'text'],
            ['sort_order', 'Հերթականություն', 'number'],
            ['active', 'Ակտիվ', 'boolean'],
        ],
    },
    settings: {
        label: 'Կարգավորումներ',
        singular: 'կարգավորում',
        icon: Settings,
        columns: ['key', 'group', 'type'],
        fields: [
            ['group', 'Խումբ', 'text', true],
            ['key', 'Բանալի', 'text', true],
            ['type', 'Տեսակ', 'select', true, [['text', 'Տեքստ'], ['translated', 'Թարգմանվող'], ['url', 'Հղում'], ['json', 'JSON']]],
            ['value', 'Արժեք', 'json'],
        ],
    },
    bookings: {
        label: 'Ամրագրումներ',
        singular: 'ամրագրում',
        icon: CalendarCheck2,
        columns: ['reference', 'name', 'item_title', 'start_date', 'status'],
        readonly: true,
        fields: [
            ['status', 'Կարգավիճակ', 'select', true, [['new', 'Նոր'], ['confirmed', 'Հաստատված'], ['completed', 'Ավարտված'], ['cancelled', 'Չեղարկված']]],
            ['total_price', 'Ընդհանուր գին', 'number'],
            ['message', 'Նշում', 'textarea'],
        ],
    },
    'contact-messages': {
        label: 'Նամակներ',
        singular: 'նամակ',
        icon: Mail,
        columns: ['name', 'email', 'subject', 'status'],
        readonly: true,
        fields: [
            ['status', 'Կարգավիճակ', 'select', true, [['new', 'Նոր'], ['read', 'Կարդացված'], ['replied', 'Պատասխանված'], ['archived', 'Արխիվ']]],
        ],
    },
};

const token = ref(localStorage.getItem('govista_admin_token') || '');
const user = ref(null);
const authLoading = ref(true);
const loginLoading = ref(false);
const loginError = ref('');
const credentials = reactive({ email: 'admin@govista.am', password: '' });
const activeView = ref('dashboard');
const mobileMenu = ref(false);
const data = ref([]);
const dashboard = ref(null);
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const page = ref(1);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });
const modalOpen = ref(false);
const editingId = ref(null);
const activeLocale = ref('hy');
const form = reactive({});
const toast = reactive({ show: false, type: 'success', message: '' });
const errors = ref({});
const uploadField = ref('');

const api = axios.create({ baseURL: '/api' });
const publicSiteUrl = document.querySelector('meta[name="govista-site-url"]')?.content || '/';

api.interceptors.request.use((config) => {
    if (token.value) config.headers.Authorization = `Bearer ${token.value}`;
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && token.value) logout(false);
        return Promise.reject(error);
    },
);

const currentResource = computed(() => resources[activeView.value]);
const pageTitle = computed(() => activeView.value === 'dashboard' ? 'Կառավարման վահանակ' : currentResource.value?.label);

onMounted(async () => {
    if (token.value) {
        try {
            const { data } = await api.get('/admin/me');
            user.value = data;
            await loadDashboard();
        } catch {
            logout(false);
        }
    }
    authLoading.value = false;
});

async function login() {
    loginLoading.value = true;
    loginError.value = '';
    try {
        const { data } = await api.post('/admin/login', credentials);
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('govista_admin_token', data.token);
        await loadDashboard();
    } catch (error) {
        loginError.value = error.response?.data?.message || error.response?.data?.errors?.email?.[0] || 'Չհաջողվեց մուտք գործել։';
    } finally {
        loginLoading.value = false;
    }
}

async function logout(callApi = true) {
    if (callApi && token.value) {
        try { await api.post('/admin/logout'); } catch {}
    }
    token.value = '';
    user.value = null;
    localStorage.removeItem('govista_admin_token');
}

async function selectView(key) {
    activeView.value = key;
    mobileMenu.value = false;
    search.value = '';
    page.value = 1;
    if (key === 'dashboard') await loadDashboard();
    else await loadResource();
}

async function loadDashboard() {
    loading.value = true;
    try {
        const response = await api.get('/admin/dashboard');
        dashboard.value = response.data;
    } finally {
        loading.value = false;
    }
}

async function loadResource() {
    if (!currentResource.value) return;
    loading.value = true;
    try {
        const response = await api.get(`/admin/content/${activeView.value}`, {
            params: { page: page.value, search: search.value, per_page: 20 },
        });
        data.value = response.data.data;
        Object.assign(pagination, {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total,
        });
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    resetForm();
    modalOpen.value = true;
}

function openEdit(record) {
    editingId.value = record.id;
    resetForm(record);
    modalOpen.value = true;
}

function resetForm(record = {}) {
    for (const key of Object.keys(form)) delete form[key];
    errors.value = {};
    activeLocale.value = 'hy';

    for (const [key, , type] of currentResource.value.fields) {
        let value = record[key];
        if (type.startsWith('localized')) {
            const initial = value && typeof value === 'object' ? value : {};
            form[key] = {};
            for (const locale of locales) {
                const localeValue = initial[locale.code];
                form[key][locale.code] = Array.isArray(localeValue) ? localeValue.join('\n') : (localeValue || '');
            }
        } else if (type === 'list') {
            form[key] = Array.isArray(value) ? value.join('\n') : '';
        } else if (type === 'json') {
            form[key] = value === undefined ? '' : (typeof value === 'string' ? value : JSON.stringify(value, null, 2));
        } else if (type === 'boolean') {
            form[key] = value ?? (key === 'active');
        } else if (type === 'number') {
            form[key] = value ?? 0;
        } else if (type === 'select') {
            const options = currentResource.value.fields.find((field) => field[0] === key)?.[4];
            form[key] = value ?? options?.[0]?.[0] ?? '';
        } else {
            form[key] = value ?? '';
        }
    }
}

function payload() {
    const result = {};
    for (const [key, , type] of currentResource.value.fields) {
        const value = form[key];
        if (type === 'localized-list') {
            result[key] = Object.fromEntries(locales.map(({ code }) => [
                code,
                String(value?.[code] || '').split('\n').map((item) => item.trim()).filter(Boolean),
            ]));
        } else if (type.startsWith('localized')) {
            result[key] = Object.fromEntries(locales.map(({ code }) => [code, value?.[code] || '']));
        } else if (type === 'list') {
            result[key] = String(value || '').split('\n').map((item) => item.trim()).filter(Boolean);
        } else if (type === 'json') {
            if (!String(value || '').trim()) result[key] = null;
            else {
                try { result[key] = JSON.parse(value); }
                catch { result[key] = value; }
            }
        } else if (type === 'number') {
            result[key] = value === '' || value === null ? null : Number(value);
        } else {
            result[key] = value;
        }
    }
    return result;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        if (editingId.value) {
            await api.put(`/admin/content/${activeView.value}/${editingId.value}`, payload());
        } else {
            await api.post(`/admin/content/${activeView.value}`, payload());
        }
        modalOpen.value = false;
        notify('success', `Տվյալները հաջողությամբ ${editingId.value ? 'պահպանվել' : 'ստեղծվել'} են։`);
        await loadResource();
    } catch (error) {
        errors.value = error.response?.data?.errors || {};
        notify('error', error.response?.data?.message || 'Խնդրում ենք ստուգել լրացված դաշտերը։');
    } finally {
        saving.value = false;
    }
}

async function remove(record) {
    if (!confirm(`Ջնջե՞լ «${displayValue(record, currentResource.value.columns[0])}» գրառումը։`)) return;
    await api.delete(`/admin/content/${activeView.value}/${record.id}`);
    notify('success', 'Գրառումը ջնջված է։');
    await loadResource();
}

async function uploadImage(event, field) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploadField.value = field;
    const body = new FormData();
    body.append('file', file);
    try {
        const { data } = await api.post('/admin/upload', body);
        form[field] = data.url;
        notify('success', 'Նկարը բեռնված է։');
    } catch {
        notify('error', 'Նկարի բեռնումը չհաջողվեց։');
    } finally {
        uploadField.value = '';
        event.target.value = '';
    }
}

function displayValue(record, key) {
    const value = record?.[key];
    if (value && typeof value === 'object' && !Array.isArray(value)) return value.hy || value.en || Object.values(value)[0] || '—';
    if (typeof value === 'boolean') return value ? 'Այո' : 'Ոչ';
    if (key === 'price' || key === 'price_from' || key === 'total_price') {
        return value === null ? '—' : new Intl.NumberFormat('hy-AM').format(value);
    }
    if (key === 'type') {
        return { group: 'Խմբային', private: 'Անհատական', package: 'Փաթեթ', accommodation: 'Գիշերակաց', transport: 'Տրանսպորտ', events: 'Միջոցառում', custom: 'Անհատական' }[value] || value;
    }
    if (key === 'travel_scope') {
        return { domestic: 'Ներքին տուր', international: 'Արտաքին տուր' }[value] || value;
    }
    if (key === 'status') {
        return { new: 'Նոր', confirmed: 'Հաստատված', completed: 'Ավարտված', cancelled: 'Չեղարկված', read: 'Կարդացված', replied: 'Պատասխանված', archived: 'Արխիվ' }[value] || value;
    }
    if (String(key).includes('_at') && value) return new Date(value).toLocaleDateString('hy-AM');
    return value || '—';
}

function fieldError(key) {
    return errors.value[key]?.[0] || Object.entries(errors.value).find(([errorKey]) => errorKey.startsWith(`${key}.`))?.[1]?.[0];
}

function notify(type, message) {
    Object.assign(toast, { show: true, type, message });
    window.setTimeout(() => { toast.show = false; }, 3500);
}

let searchTimer;
function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        loadResource();
    }, 350);
}

function changePage(next) {
    if (next < 1 || next > pagination.last_page) return;
    page.value = next;
    loadResource();
}
</script>

<template>
    <div v-if="authLoading" class="app-loader">
        <div class="brand-loader"><img :src="'/brand/govista-logo-light.png'" alt="GoVista — Travel Beyond Limits"></div>
    </div>

    <main v-else-if="!token" class="login-page">
        <div class="login-visual">
            <div class="login-orb orb-one"></div>
            <div class="login-orb orb-two"></div>
            <div class="login-copy">
                <div class="login-mark"><img :src="'/brand/govista-logo-light.png'" alt="GoVista — Travel Beyond Limits"></div>
                <p class="eyebrow">Travel management system</p>
                <h1>Հայաստանը՝<br><em>ձեր տեսանկյունից</em></h1>
                <p>Կառավարեք տուրերը, բովանդակությունը, ամրագրումները և կայքի բոլոր լեզուները մեկ գեղեցիկ հարթակից։</p>
                <div class="login-badges">
                    <span><CheckCircle2 :size="16" /> HY · RU · EN</span>
                    <span><CheckCircle2 :size="16" /> Live content</span>
                </div>
            </div>
        </div>
        <div class="login-panel">
            <form class="login-card" @submit.prevent="login">
                <div class="mobile-brand"><img :src="'/brand/govista-logo.png'" alt="GoVista — Travel Beyond Limits"></div>
                <p class="eyebrow">Բարի վերադարձ</p>
                <h2>Մուտք ադմին համակարգ</h2>
                <p class="muted">Մուտքագրեք ձեր տվյալները՝ կառավարումը շարունակելու համար։</p>
                <div v-if="loginError" class="alert alert-error"><AlertCircle :size="18" /> {{ loginError }}</div>
                <label>
                    <span>Էլ․ փոստ</span>
                    <input v-model="credentials.email" type="email" autocomplete="email" required>
                </label>
                <label>
                    <span>Գաղտնաբառ</span>
                    <input v-model="credentials.password" type="password" autocomplete="current-password" required placeholder="••••••••••">
                </label>
                <button class="button button-primary login-button" :disabled="loginLoading">
                    <Loader2 v-if="loginLoading" class="spin" :size="18" />
                    <span>{{ loginLoading ? 'Մուտք...' : 'Մուտք գործել' }}</span>
                    <ChevronRight v-if="!loginLoading" :size="18" />
                </button>
            </form>
        </div>
    </main>

    <div v-else class="admin-shell">
        <aside class="sidebar" :class="{ open: mobileMenu }">
            <div class="sidebar-brand">
                <img class="sidebar-logo" :src="'/brand/govista-logo-light.png'" alt="GoVista — Travel Beyond Limits">
                <button class="sidebar-close" @click="mobileMenu = false"><X :size="21" /></button>
            </div>
            <nav>
                <p class="nav-section">Հիմնական</p>
                <button :class="{ active: activeView === 'dashboard' }" @click="selectView('dashboard')">
                    <LayoutDashboard :size="19" /><span>Վահանակ</span>
                </button>
                <p class="nav-section">Բովանդակություն</p>
                <button v-for="(resource, key) in resources" :key="key" :class="{ active: activeView === key }" @click="selectView(key)">
                    <component :is="resource.icon" :size="19" /><span>{{ resource.label }}</span>
                    <span v-if="key === 'bookings' && dashboard?.stats?.[2]?.value" class="nav-count">{{ dashboard.stats[2].value }}</span>
                </button>
            </nav>
            <div class="sidebar-footer">
                <div class="user-avatar">{{ user?.name?.slice(0, 1) }}</div>
                <div><strong>{{ user?.name }}</strong><small>{{ user?.email }}</small></div>
                <button title="Դուրս գալ" @click="logout()"><LogOut :size="18" /></button>
            </div>
        </aside>

        <div v-if="mobileMenu" class="sidebar-scrim" @click="mobileMenu = false"></div>

        <section class="admin-main">
            <header class="topbar">
                <button class="menu-button" @click="mobileMenu = true"><Menu :size="22" /></button>
                <div>
                    <p class="breadcrumb">GoVista / {{ pageTitle }}</p>
                    <h1>{{ pageTitle }}</h1>
                </div>
                <a class="site-link" :href="publicSiteUrl" target="_blank" rel="noopener noreferrer">Դիտել կայքը <ChevronRight :size="16" /></a>
            </header>

            <div class="content-area">
                <template v-if="activeView === 'dashboard'">
                    <div class="welcome-card">
                        <div>
                            <p class="eyebrow">Այսօրվա ամփոփում</p>
                            <h2>Բարի աշխատանք, {{ user?.name?.split(' ')?.[0] }} 👋</h2>
                            <p>Կայքի ամբողջ բովանդակությունն ու հաճախորդների հայտերը այստեղ են։</p>
                        </div>
                        <div class="welcome-art"><Sparkles :size="56" /></div>
                    </div>

                    <div v-if="dashboard" class="stats-grid">
                        <article v-for="(stat, index) in dashboard.stats" :key="stat.key" class="stat-card">
                            <div class="stat-icon" :class="`tone-${index + 1}`">
                                <component :is="[Compass, MapPinned, CalendarCheck2, Mail][index]" :size="22" />
                            </div>
                            <div><span>{{ stat.label }}</span><strong>{{ stat.value }}</strong></div>
                        </article>
                    </div>

                    <div class="dashboard-grid">
                        <article class="panel">
                            <div class="panel-heading"><div><p class="eyebrow">Վերջին հայտերը</p><h3>Ամրագրումներ</h3></div><button @click="selectView('bookings')">Բոլորը</button></div>
                            <div v-if="dashboard?.latest_bookings?.length" class="activity-list">
                                <div v-for="booking in dashboard.latest_bookings" :key="booking.id" class="activity-row">
                                    <div class="activity-icon"><CalendarCheck2 :size="18" /></div>
                                    <div><strong>{{ booking.name }}</strong><small>{{ booking.item_title || booking.type }}</small></div>
                                    <span class="status-chip" :class="`status-${booking.status}`">{{ displayValue(booking, 'status') }}</span>
                                </div>
                            </div>
                            <div v-else class="empty-state compact"><CalendarCheck2 :size="30" /><span>Դեռ ամրագրումներ չկան</span></div>
                        </article>
                        <article class="panel">
                            <div class="panel-heading"><div><p class="eyebrow">Արագ կառավարում</p><h3>Բովանդակություն</h3></div></div>
                            <div class="quick-grid">
                                <button v-for="key in ['tours', 'destinations', 'posts', 'services']" :key="key" @click="selectView(key)">
                                    <component :is="resources[key].icon" :size="20" />
                                    <span>{{ resources[key].label }}</span>
                                    <Plus :size="17" />
                                </button>
                            </div>
                        </article>
                    </div>
                </template>

                <template v-else>
                    <div class="resource-toolbar">
                        <div class="search-box"><Search :size="18" /><input v-model="search" placeholder="Որոնել..." @input="scheduleSearch"></div>
                        <button v-if="!currentResource.readonly" class="button button-primary" @click="openCreate"><Plus :size="18" /> Ավելացնել {{ currentResource.singular }}</button>
                    </div>

                    <div class="table-card">
                        <div v-if="loading" class="table-loading"><Loader2 class="spin" :size="28" /> Բեռնվում է...</div>
                        <div v-else-if="!data.length" class="empty-state"><component :is="currentResource.icon" :size="42" /><h3>Դեռ գրառումներ չկան</h3><p>Ստեղծեք առաջին {{ currentResource.singular }}ը։</p></div>
                        <div v-else class="table-wrap">
                            <table>
                                <thead><tr><th v-for="column in currentResource.columns" :key="column">{{ currentResource.fields.find(field => field[0] === column)?.[1] || column }}</th><th></th></tr></thead>
                                <tbody>
                                    <tr v-for="record in data" :key="record.id">
                                        <td v-for="(column, columnIndex) in currentResource.columns" :key="column">
                                            <div v-if="columnIndex === 0" class="primary-cell">
                                                <img v-if="record.image" :src="record.image" alt="">
                                                <div v-else class="row-icon"><component :is="currentResource.icon" :size="17" /></div>
                                                <strong>{{ displayValue(record, column) }}</strong>
                                            </div>
                                            <span v-else-if="['active', 'featured'].includes(column)" class="boolean-chip" :class="{ enabled: record[column] }">{{ record[column] ? 'Այո' : 'Ոչ' }}</span>
                                            <span v-else-if="column === 'status'" class="status-chip" :class="`status-${record.status}`">{{ displayValue(record, column) }}</span>
                                            <span v-else>{{ displayValue(record, column) }}</span>
                                        </td>
                                        <td class="actions-cell">
                                            <button title="Խմբագրել" @click="openEdit(record)"><Pencil :size="17" /></button>
                                            <button v-if="!currentResource.readonly" class="danger" title="Ջնջել" @click="remove(record)"><Trash2 :size="17" /></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="pagination.last_page > 1" class="pagination">
                            <span>{{ pagination.total }} գրառում</span>
                            <div>
                                <button :disabled="pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)"><ChevronLeft :size="17" /></button>
                                <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                                <button :disabled="pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)"><ChevronRight :size="17" /></button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </div>

    <div v-if="modalOpen" class="modal-layer" @mousedown.self="modalOpen = false">
        <div class="editor-modal">
            <header>
                <div>
                    <p class="eyebrow">{{ editingId ? 'Խմբագրում' : 'Նոր գրառում' }}</p>
                    <h2>{{ editingId ? `${currentResource.singular} #${editingId}` : `Ավելացնել ${currentResource.singular}` }}</h2>
                </div>
                <button @click="modalOpen = false"><X :size="22" /></button>
            </header>

            <div class="editor-body">
                <div v-if="currentResource.fields.some(field => field[2].startsWith('localized'))" class="language-tabs">
                    <button v-for="locale in locales" :key="locale.code" :class="{ active: activeLocale === locale.code }" @click="activeLocale = locale.code">
                        <span>{{ locale.code.toUpperCase() }}</span>{{ locale.label }}
                    </button>
                </div>

                <div class="form-grid">
                    <template v-for="field in currentResource.fields" :key="field[0]">
                        <label v-if="field[2].startsWith('localized')" class="form-field" :class="{ wide: field[2] !== 'localized' }">
                            <span>{{ field[1] }} <em v-if="field[3]">*</em><small>{{ activeLocale.toUpperCase() }}</small></span>
                            <textarea v-if="field[2] !== 'localized'" v-model="form[field[0]][activeLocale]" :class="{ editor: field[2] === 'localized-editor' }" :placeholder="field[1]"></textarea>
                            <input v-else v-model="form[field[0]][activeLocale]" type="text" :placeholder="field[1]">
                            <small v-if="fieldError(field[0])" class="field-error">{{ fieldError(field[0]) }}</small>
                        </label>

                        <label v-else-if="field[2] === 'textarea'" class="form-field wide">
                            <span>{{ field[1] }} <em v-if="field[3]">*</em></span>
                            <textarea v-model="form[field[0]]" :placeholder="field[1]"></textarea>
                            <small v-if="fieldError(field[0])" class="field-error">{{ fieldError(field[0]) }}</small>
                        </label>

                        <label v-else-if="field[2] === 'json' || field[2] === 'list'" class="form-field wide">
                            <span>{{ field[1] }} <em v-if="field[3]">*</em></span>
                            <textarea v-model="form[field[0]]" class="code-input" :placeholder="field[2] === 'list' ? 'Մեկ արժեք՝ յուրաքանչյուր տողում' : 'Տեքստ կամ JSON'"></textarea>
                            <small v-if="fieldError(field[0])" class="field-error">{{ fieldError(field[0]) }}</small>
                        </label>

                        <label v-else-if="field[2] === 'image'" class="form-field wide">
                            <span>{{ field[1] }}</span>
                            <div class="image-field">
                                <div class="image-preview">
                                    <img v-if="form[field[0]]" :src="form[field[0]]" alt="">
                                    <ImageIcon v-else :size="25" />
                                </div>
                                <div class="image-controls">
                                    <input v-model="form[field[0]]" type="url" placeholder="https://...">
                                    <label class="upload-button">
                                        <Loader2 v-if="uploadField === field[0]" class="spin" :size="17" />
                                        <Upload v-else :size="17" />
                                        Բեռնել
                                        <input type="file" accept="image/*" @change="uploadImage($event, field[0])">
                                    </label>
                                </div>
                            </div>
                            <small v-if="fieldError(field[0])" class="field-error">{{ fieldError(field[0]) }}</small>
                        </label>

                        <label v-else-if="field[2] === 'select'" class="form-field">
                            <span>{{ field[1] }} <em v-if="field[3]">*</em></span>
                            <select v-model="form[field[0]]">
                                <option v-for="option in field[4]" :key="option[0]" :value="option[0]">{{ option[1] }}</option>
                            </select>
                            <small v-if="fieldError(field[0])" class="field-error">{{ fieldError(field[0]) }}</small>
                        </label>

                        <label v-else-if="field[2] === 'boolean'" class="toggle-field">
                            <div><strong>{{ field[1] }}</strong><small>{{ form[field[0]] ? 'Միացված է' : 'Անջատված է' }}</small></div>
                            <input v-model="form[field[0]]" type="checkbox">
                            <span class="toggle"></span>
                        </label>

                        <label v-else class="form-field">
                            <span>{{ field[1] }} <em v-if="field[3]">*</em></span>
                            <input v-model="form[field[0]]" :type="field[2] === 'number' ? 'number' : field[2] === 'datetime' ? 'datetime-local' : 'text'" :step="field[2] === 'number' ? 'any' : undefined" :placeholder="field[1]">
                            <small v-if="fieldError(field[0])" class="field-error">{{ fieldError(field[0]) }}</small>
                        </label>
                    </template>
                </div>
            </div>
            <footer>
                <button class="button button-ghost" @click="modalOpen = false">Չեղարկել</button>
                <button class="button button-primary" :disabled="saving" @click="save">
                    <Loader2 v-if="saving" class="spin" :size="18" />
                    <Save v-else :size="18" /> {{ saving ? 'Պահպանվում է...' : 'Պահպանել' }}
                </button>
            </footer>
        </div>
    </div>

    <transition name="toast">
        <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
            <CheckCircle2 v-if="toast.type === 'success'" :size="20" />
            <AlertCircle v-else :size="20" />
            {{ toast.message }}
        </div>
    </transition>
</template>
