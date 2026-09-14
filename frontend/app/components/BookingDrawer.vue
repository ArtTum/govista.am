<script setup>
import { CalendarDays, CheckCircle2, Loader2, Minus, Plus, Send, X } from '@lucide/vue'

const { isOpen, selected, preferences, closeBooking } = useBooking()
const { locale, t, localePath } = useLocale()
const travel = useTravelText()
const packageCopy = usePackageText()
const { customer, restore, request: api } = useCustomer()
onMounted(restore)
const submissionKey = ref('')
const loading = ref(false)
const success = ref(false)
const reference = ref('')
const errorMessage = ref('')
const form = reactive({ name: '', email: '', phone: '', start_date: '', end_date: '', participants: 2, message: '', request_details: {} })
watch(customer, value => {
  if (isOpen.value && value) {
    form.email = value.email
    if (!form.name) form.name = value.name
  }
})
const drawer = ref(null)
const fieldErrors = ref({})
const dialogTitle = useId()
const minimumGuests = computed(() => (form.request_details.children?.length || 0) + 1)
watch(() => form.participants, value => { if (form.request_details.adults) form.request_details.adults = Math.max(1, value - (form.request_details.children?.length || 0)) })
const safeClose = () => { if (!loading.value) closeBooking() }
useModalFocus(isOpen, drawer, safeClose)
const isAccommodation = computed(() => selected.value?.type === 'accommodation')
const hasEndDate = computed(() => isAccommodation.value || ['transport', 'flight', 'package'].includes(selected.value?.type))
const selectedPackage = computed(() => Boolean(form.request_details.package_offer_id || form.request_details.search_token))
const endDateLabel = computed(() => isAccommodation.value ? t('forms.checkOut') : travel.value.returnDate)
const checkoutMin = computed(() => {
  if (!form.start_date) return undefined
  const date = new Date(`${form.start_date}T12:00:00`)
  if (isAccommodation.value) date.setDate(date.getDate() + 1)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
})
watch(() => form.start_date, () => {
  if (form.end_date && (!form.start_date || form.end_date < checkoutMin.value)) form.end_date = ''
})
const bookingIntro = computed(() => ({
  hy: 'Թողեք տվյալները, իսկ GoVista-ի մասնագետը կպատրաստի լավագույն տարբերակը ձեզ համար։',
  ru: 'Оставьте данные — специалист GoVista подготовит для вас лучший вариант.',
  en: 'Leave your details and a GoVista travel designer will prepare the best option for you.',
}[locale.value]))
const bookingErrorCopy = {
  hy: 'Չհաջողվեց ուղարկել հայտը։ Խնդրում ենք փորձել կրկին կամ զանգահարել մեզ։',
  ru: 'Не удалось отправить заявку. Попробуйте ещё раз или позвоните нам.',
  en: 'We could not send your request. Please try again or call us.',
}

watch(isOpen, (value) => {
  if (value) {
    success.value = false
    errorMessage.value = ''
    fieldErrors.value = {}
    submissionKey.value = crypto.randomUUID()
    Object.assign(form, { name: customer.value?.name || form.name, email: customer.value?.email || form.email, start_date: '', end_date: '', participants: 2, request_details: {} }, preferences.value)
  } else {
    Object.assign(form, { name: '', email: '', phone: '', start_date: '', end_date: '', participants: 2, message: '', request_details: {} })
    reference.value = ''
    errorMessage.value = ''
  }
})

async function submit() {
  if (loading.value) return
  loading.value = true
  errorMessage.value = ''
  fieldErrors.value = {}
  try {
    const result = await api('/v1/bookings', {
      method: 'POST',
      body: {
        ...form,
        submission_key: submissionKey.value,
        locale: locale.value,
        type: selected.value?.type || 'custom',
        item_id: selected.value?.id || null,
        item_title: selected.value?.title || null,
      },
    })
    reference.value = result.reference
    success.value = true
  } catch (error) {
    fieldErrors.value = error.data?.errors || {}
    errorMessage.value = error.statusCode === 429 ? t('ui.rateLimit')
      : fieldErrors.value.item_id ? t('ui.unavailable')
        : fieldErrors.value.start_date || fieldErrors.value.end_date ? t('ui.dateError')
          : bookingErrorCopy[locale.value] || bookingErrorCopy.en
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <transition name="drawer">
      <div v-if="isOpen" class="booking-layer" @mousedown.self="safeClose">
        <aside ref="drawer" class="booking-drawer" role="dialog" aria-modal="true" :aria-labelledby="dialogTitle" tabindex="-1" :aria-busy="loading">
          <header>
            <div>
              <p class="section-eyebrow">GoVista concierge</p>
              <h2 :id="dialogTitle">{{ selected?.title || t('common.book') }}</h2>
            </div>
            <button :aria-label="t('ui.close')" :disabled="loading" @click="safeClose"><X :size="22" /></button>
          </header>

          <div v-if="success" class="booking-success" role="status" aria-live="polite">
            <div><CheckCircle2 :size="46" /></div>
            <h3>{{ t('forms.success') }}</h3>
            <p>{{ t('ui.reference') }}<br><strong>{{ reference }}</strong></p>
            <NuxtLink v-if="customer" :to="localePath('/account')" @click="closeBooking">{{ travel.account }}</NuxtLink>
            <button class="button-primary" @click="closeBooking">OK</button>
          </div>

          <form v-else @submit.prevent="submit">
            <p class="booking-intro">{{ bookingIntro }}</p>
            <p v-if="!customer" class="booking-account-note"><NuxtLink :to="localePath('/account')" @click="closeBooking">{{ travel.tracking }}</NuxtLink></p>
            <p v-if="form.request_details.destination" class="booking-intro">{{ form.request_details.origin }} → {{ form.request_details.destination }}<template v-if="form.request_details.children?.length"> · {{ travel.children }}: {{ form.request_details.children.join(', ') }} ({{ travel.age }})</template></p>
            <p v-if="errorMessage" class="booking-error" role="alert">{{ errorMessage }}</p>
            <label><span>{{ t('forms.name') }}</span><input v-model.trim="form.name" name="name" autocomplete="name" maxlength="120" :aria-invalid="Boolean(fieldErrors.name)" required></label>
            <div class="form-row">
              <label><span>{{ t('forms.email') }}</span><input v-model.trim="form.email" name="email" autocomplete="email" :readonly="Boolean(customer)" maxlength="190" :aria-invalid="Boolean(fieldErrors.email)" type="email" required></label>
              <label><span>{{ t('forms.phone') }}</span><input v-model.trim="form.phone" name="phone" autocomplete="tel" maxlength="40" type="tel"></label>
            </div>
            <div v-if="selectedPackage" class="package-selected-note"><strong>{{form.start_date}}<template v-if="form.end_date"> — {{form.end_date}}</template> · {{form.participants}} {{t('forms.guests')}}</strong>{{packageCopy.selectedNote}}</div>
            <div v-else class="form-row">
              <label><span>{{ isAccommodation ? t('forms.checkIn') : t('forms.date') }}</span><div class="input-icon"><CalendarDays :size="17" /><DatePicker v-model="form.start_date" /></div></label>
              <label v-if="hasEndDate"><span>{{ endDateLabel }}</span><div class="input-icon"><CalendarDays :size="17" /><DatePicker v-model="form.end_date" :min="checkoutMin" :label="endDateLabel" /></div></label>
              <label v-else><span>{{ selected?.type === 'transport' ? t('forms.people') : t('forms.guests') }}</span><div class="guest-input"><button type="button" :aria-label="t('ui.decrease')" :disabled="form.participants <= minimumGuests" @click="form.participants = Math.max(minimumGuests, form.participants - 1)"><Minus :size="15" /></button><strong aria-live="polite">{{ form.participants }}</strong><button type="button" :aria-label="t('ui.increase')" :disabled="form.participants >= 100" @click="form.participants = Math.min(100, form.participants + 1)"><Plus :size="15" /></button></div></label>
            </div>
            <label v-if="hasEndDate && !selectedPackage"><span>{{ t('forms.guests') }}</span><div class="guest-input"><button type="button" :aria-label="t('ui.decrease')" :disabled="form.participants <= minimumGuests" @click="form.participants = Math.max(minimumGuests, form.participants - 1)"><Minus :size="15" /></button><strong aria-live="polite">{{ form.participants }}</strong><button type="button" :aria-label="t('ui.increase')" :disabled="form.participants >= 100" @click="form.participants = Math.min(100, form.participants + 1)"><Plus :size="15" /></button></div></label>
            <label><span>{{ t('forms.message') }}</span><textarea v-model="form.message" name="message" maxlength="3000" rows="4"></textarea></label>
            <p class="booking-request-note">{{ t('ui.requestNote') }}</p>
            <button class="drawer-submit" :disabled="loading">
              <Loader2 v-if="loading" class="spin" :size="19" />
              <Send v-else :size="19" />
              {{ t('forms.send') }}
            </button>
          </form>
        </aside>
      </div>
    </transition>
  </Teleport>
</template>
