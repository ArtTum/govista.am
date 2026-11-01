<script setup>
const props = defineProps({ item: { type: Object, required: true } })
const { locale } = useLocale()
const { openBooking } = useBooking()
const isTransfer = computed(() => props.item.type === 'transfer')
const copy = computed(() => ({
  hy: { title: 'Տարբերակներ և գներ', option: isTransfer.value ? 'Մեքենայի դասը' : 'Վարձակալության տևողությունը', price: isTransfer.value ? 'Ամբողջ մեքենայի համար' : 'Օրավարձ', capacity: 'Ուղևոր / ուղեբեռ', note: 'Գները մեկնարկային են։ Ընտրված ամսաթվերի գինն ու հասանելիությունը կհաստատենք առանձին։', request: 'Ընտրել', deposit: 'Երաշխիքային ավանդ' },
  ru: { title: 'Варианты и цены', option: isTransfer.value ? 'Класс автомобиля' : 'Срок аренды', price: isTransfer.value ? 'За автомобиль' : 'В сутки', capacity: 'Пассажиры / багаж', note: 'Цены ориентировочные. Стоимость и доступность на выбранные даты подтвердим отдельно.', request: 'Выбрать', deposit: 'Залог' },
  en: { title: 'Options and rates', option: isTransfer.value ? 'Vehicle class' : 'Rental duration', price: isTransfer.value ? 'Per vehicle' : 'Per day', capacity: 'Passengers / bags', note: 'Starting rates. We will confirm the price and availability for your dates separately.', request: 'Select', deposit: 'Security deposit' },
}[locale.value]))
const amount = value => new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(Number(value))
const select = tariff => openBooking(props.item, { message: `${tariff.label[locale.value]} · ${amount(tariff.price)} ${props.item.currency} · ${props.item.unit}` })
</script>

<template>
  <section class="supplier-details">
    <h3>{{ copy.title }}</h3>
    <div class="supplier-table-wrap"><table class="supplier-rates">
      <thead><tr><th scope="col">{{ copy.option }}</th><th v-if="isTransfer" scope="col">{{ copy.capacity }}</th><th scope="col">{{ copy.price }}</th><th v-if="isTransfer" scope="col"><span class="sr-only">{{ copy.request }}</span></th></tr></thead>
      <tbody><tr v-for="tariff in item.rental_terms.tariffs" :key="tariff.key"><th scope="row">{{ tariff.label[locale] }}</th><td v-if="isTransfer">{{ tariff.passengers }} / {{ tariff.luggage }}</td><td>{{ amount(tariff.price) }} {{ item.currency }}</td><td v-if="isTransfer"><button type="button" class="supplier-select" @click="select(tariff)">{{ copy.request }}</button></td></tr></tbody>
    </table></div>
    <p v-if="!isTransfer && item.rental_terms.deposit">{{ copy.deposit }}: {{ amount(item.rental_terms.deposit) }} {{ item.currency }}</p>
    <p>{{ copy.note }}</p>
  </section>
</template>
