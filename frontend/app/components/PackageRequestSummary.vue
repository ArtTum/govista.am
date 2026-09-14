<script setup>
defineProps({ snapshot: {type:Object, required:true} })
const copy = usePackageText(), {locale} = useLocale()
const label = computed(() => ({hy:'Ընտրված փաթեթի պահպանված տվյալները',ru:'Сохранённые данные выбранного пакета',en:'Saved details of your selected package'}[locale.value]))
</script>
<template>
  <details class="package-saved-summary"><summary>{{label}}</summary><p>{{snapshot.source}} · {{snapshot.hotel_name || snapshot.title}}</p><p>{{snapshot.nights}} {{copy.nights}} · {{copy.meals[snapshot.meal_plan] || snapshot.meal_plan}}</p><p v-if="snapshot.price != null"><strong>{{snapshot.price}} {{snapshot.currency}}</strong> · {{snapshot.price_basis==='per_person' ? copy.perPerson : snapshot.price_basis==='indicative_total' ? copy.indicative : copy.total}}</p><p class="package-snapshot-note">{{copy.livePricing}}</p><ul><li v-for="key in snapshot.inclusions" :key="key">✓ {{copy.inclusions[key]}}</li></ul><p v-if="snapshot.exclusions" class="travel-preserve">{{copy.excludes}}: {{snapshot.exclusions}}</p><p v-if="snapshot.terms" class="travel-preserve">{{copy.terms}}: {{snapshot.terms}}</p></details>
</template>
