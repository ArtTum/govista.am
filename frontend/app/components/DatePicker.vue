<script setup>
import { CalendarDays, ChevronDown, ChevronLeft, ChevronRight } from '@lucide/vue'

const props = defineProps({
  min: { type: String, default: '' },
  label: { type: String, default: '' },
  placement: { type: String, default: 'bottom' },
})

const model = defineModel({ type: String, default: '' })
const { locale, t } = useLocale()
const root = ref(null)
const isOpen = ref(false)
const panel = ref(null)
const panelId = useId()
const { panelStyle, close, keydown } = useFloatingPanel(root, panel, isOpen, () => props.placement)

function toDateKey(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function dateFromKey(value) {
  if (!value) return null
  const [year, month, day] = value.split('-').map(Number)
  if (!year || !month || !day) return null
  return new Date(year, month - 1, day, 12)
}

const today = new Date()
const todayKey = toDateKey(today)
const minimumDate = computed(() => props.min && props.min > todayKey ? props.min : todayKey)
const view = ref({ year: today.getFullYear(), month: today.getMonth() })

const localeData = computed(() => ({
  hy: {
    months: ['Հունվար', 'Փետրվար', 'Մարտ', 'Ապրիլ', 'Մայիս', 'Հունիս', 'Հուլիս', 'Օգոստոս', 'Սեպտեմբեր', 'Հոկտեմբեր', 'Նոյեմբեր', 'Դեկտեմբեր'],
    shortMonths: ['հուն', 'փետր', 'մարտ', 'ապր', 'մայ', 'հունիս', 'հուլ', 'օգ', 'սեպտ', 'հոկտ', 'նոյ', 'դեկտ'],
    weekdays: ['Երկ', 'Երք', 'Չրք', 'Հնգ', 'Ուր', 'Շբթ', 'Կիր'],
  },
  ru: {
    months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
    shortMonths: ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'],
    weekdays: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
  },
  en: {
    months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    shortMonths: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    weekdays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
  },
}[locale.value] || {
  months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
  shortMonths: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  weekdays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
}))

const copy = computed(() => ({
  hy: { choose: 'Ընտրել ամսաթիվ', today: 'Այսօր', clear: 'Մաքրել', previous: 'Նախորդ ամիս', next: 'Հաջորդ ամիս' },
  ru: { choose: 'Выберите дату', today: 'Сегодня', clear: 'Очистить', previous: 'Предыдущий месяц', next: 'Следующий месяц' },
  en: { choose: 'Choose a date', today: 'Today', clear: 'Clear', previous: 'Previous month', next: 'Next month' },
}[locale.value] || {
  choose: 'Choose a date',
  today: 'Today',
  clear: 'Clear',
  previous: 'Previous month',
  next: 'Next month',
}))

const formattedValue = computed(() => {
  const selected = dateFromKey(model.value)
  if (!selected) return ''
  const day = selected.getDate()
  const month = localeData.value.shortMonths[selected.getMonth()]
  const year = selected.getFullYear()
  return locale.value === 'en' ? `${month} ${day}, ${year}` : `${day} ${month} ${year}`
})

const monthTitle = computed(() => `${localeData.value.months[view.value.month]} ${view.value.year}`)

const weekdays = computed(() => localeData.value.weekdays)

const calendarDays = computed(() => {
  const firstDay = new Date(view.value.year, view.value.month, 1)
  const leadingDays = (firstDay.getDay() + 6) % 7

  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(view.value.year, view.value.month, index - leadingDays + 1, 12)
    const value = toDateKey(date)
    return {
      value,
      day: date.getDate(),
      currentMonth: date.getMonth() === view.value.month,
      disabled: value < minimumDate.value,
      today: value === todayKey,
      selected: value === model.value,
      label: `${localeData.value.weekdays[(date.getDay() + 6) % 7]}, ${date.getDate()} ${localeData.value.months[date.getMonth()]} ${date.getFullYear()}`,
    }
  })
})

function syncView() {
  const selected = dateFromKey(model.value) || dateFromKey(minimumDate.value) || today
  view.value = { year: selected.getFullYear(), month: selected.getMonth() }
}

function toggleCalendar() {
  if (!isOpen.value) syncView()
  isOpen.value = !isOpen.value
}

function changeMonth(offset) {
  const next = new Date(view.value.year, view.value.month + offset, 1)
  view.value = { year: next.getFullYear(), month: next.getMonth() }
}

function selectDate(day) {
  if (day.disabled) return
  model.value = day.value
  view.value = {
    year: dateFromKey(day.value).getFullYear(),
    month: dateFromKey(day.value).getMonth(),
  }
  close()
}

function selectToday() {
  if (todayKey < minimumDate.value) return
  model.value = todayKey
  syncView()
  close()
}

function clearDate() {
  model.value = ''
  close()
}

</script>

<template>
  <div ref="root" class="date-picker" :class="{ 'is-open': isOpen, 'has-value': model }">
    <button
      type="button"
      class="date-picker-trigger"
      :aria-label="`${label || t('forms.date')}: ${formattedValue || copy.choose}`"
      :aria-expanded="isOpen"
      :aria-controls="isOpen ? panelId : undefined"
      aria-haspopup="dialog"
      @click="toggleCalendar"
    >
      <span>{{ formattedValue || copy.choose }}</span>
      <ChevronDown :size="16" />
    </button>

    <Teleport to="body">
    <Transition name="calendar-popover">
      <div
        v-if="isOpen"
        ref="panel"
        :id="panelId"
        data-overlay-popover
        :style="panelStyle"
        @keydown="keydown"
        class="date-picker-popover"
        :class="`date-picker-popover--${placement}`"
        role="dialog"
        :aria-label="label || t('forms.date')"
      >
        <div class="date-picker-heading">
          <div>
            <span class="date-picker-heading-icon"><CalendarDays :size="17" /></span>
            <strong>{{ monthTitle }}</strong>
          </div>
          <div class="date-picker-navigation">
            <button type="button" :aria-label="copy.previous" @click="changeMonth(-1)"><ChevronLeft :size="17" /></button>
            <button type="button" :aria-label="copy.next" @click="changeMonth(1)"><ChevronRight :size="17" /></button>
          </div>
        </div>

        <div class="date-picker-weekdays" aria-hidden="true">
          <span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span>
        </div>

        <div class="date-picker-grid">
          <button
            v-for="day in calendarDays"
            :key="day.value"
            type="button"
            :class="{
              'is-outside': !day.currentMonth,
              'is-disabled': day.disabled,
              'is-today': day.today,
              'is-selected': day.selected,
            }"
            :disabled="day.disabled"
            :aria-label="day.label"
            :aria-pressed="day.selected"
            @click="selectDate(day)"
          >
            {{ day.day }}
          </button>
        </div>

        <div class="date-picker-actions">
          <button v-if="model" type="button" @click="clearDate">{{ copy.clear }}</button>
          <span v-else></span>
          <button type="button" class="date-picker-today" :disabled="todayKey < minimumDate" @click="selectToday">{{ copy.today }}</button>
        </div>
      </div>
    </Transition>
    </Teleport>
  </div>
</template>
