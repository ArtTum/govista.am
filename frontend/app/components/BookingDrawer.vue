<script setup>
import { CalendarDays, CheckCircle2, Loader2, Minus, Plus, Send, X } from '@lucide/vue'

const { isOpen, selected, closeBooking } = useBooking()
const { locale, t } = useLocale()
const api = useGovistaApi()
const loading = ref(false)
const success = ref(false)
const reference = ref('')
const errorMessage = ref('')
const form = reactive({ name: '', email: '', phone: '', start_date: '', participants: 2, message: '' })
const bookingErrorCopy = {
  hy: 'Չհաջողվեց ուղարկել հայտը։ Խնդրում ենք փորձել կրկին կամ զանգահարել մեզ։',
  ru: 'Не удалось отправить заявку. Попробуйте ещё раз или позвоните нам.',
  en: 'We could not send your request. Please try again or call us.',
}

watch(isOpen, (value) => {
  if (value) {
    success.value = false
    errorMessage.value = ''
    document.body.classList.add('drawer-open')
  } else {
    document.body.classList.remove('drawer-open')
    Object.assign(form, { name: '', email: '', phone: '', start_date: '', participants: 2, message: '' })
    reference.value = ''
    errorMessage.value = ''
  }
})

async function submit() {
  loading.value = true
  errorMessage.value = ''
  try {
    const result = await api('/v1/bookings', {
      method: 'POST',
      body: {
        ...form,
        locale: locale.value,
        type: selected.value?.type || 'custom',
        item_id: selected.value?.id || null,
        item_title: selected.value?.title || null,
      },
    })
    reference.value = result.reference
    success.value = true
  } catch {
    errorMessage.value = bookingErrorCopy[locale.value] || bookingErrorCopy.en
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <transition name="drawer">
      <div v-if="isOpen" class="booking-layer" @mousedown.self="closeBooking">
        <aside class="booking-drawer">
          <header>
            <div>
              <p class="section-eyebrow">GoVista concierge</p>
              <h2>{{ selected?.title || t('common.book') }}</h2>
            </div>
            <button aria-label="Close" @click="closeBooking"><X :size="22" /></button>
          </header>

          <div v-if="success" class="booking-success">
            <div><CheckCircle2 :size="46" /></div>
            <h3>{{ t('forms.success') }}</h3>
            <p>{{ reference }}</p>
            <button class="button-primary" @click="closeBooking">OK</button>
          </div>

          <form v-else @submit.prevent="submit">
            <p class="booking-intro">Leave the details — your GoVista travel designer will prepare the best option for you.</p>
            <p v-if="errorMessage" class="booking-error" role="alert">{{ errorMessage }}</p>
            <label><span>{{ t('forms.name') }}</span><input v-model="form.name" required></label>
            <div class="form-row">
              <label><span>{{ t('forms.email') }}</span><input v-model="form.email" type="email" required></label>
              <label><span>{{ t('forms.phone') }}</span><input v-model="form.phone" type="tel"></label>
            </div>
            <div class="form-row">
              <label><span>{{ t('forms.date') }}</span><div class="input-icon"><CalendarDays :size="17" /><DatePicker v-model="form.start_date" /></div></label>
              <label><span>{{ t('forms.guests') }}</span><div class="guest-input"><button type="button" aria-label="Decrease guests" @click="form.participants = Math.max(1, form.participants - 1)"><Minus :size="15" /></button><strong>{{ form.participants }}</strong><button type="button" aria-label="Increase guests" @click="form.participants = Math.min(100, form.participants + 1)"><Plus :size="15" /></button></div></label>
            </div>
            <label><span>{{ t('forms.message') }}</span><textarea v-model="form.message" rows="5"></textarea></label>
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
