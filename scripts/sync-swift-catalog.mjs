import { writeFile, mkdir } from 'node:fs/promises'

const fleetOrigin = 'https://swift.rent'
const transferOrigin = 'https://transfer.swift.rent'
const locales = ['en', 'ru']
const text = value => String(value || '').replace(/<\/(?:p|div|li)>|<br\s*\/?\s*>/gi, '\n').replace(/<[^>]*>/g, '').replace(/&nbsp;|\u00a0/g, ' ').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;|&apos;/g, "'").replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n))).replace(/[ \t]+/g, ' ').replace(/\n\s*\n+/g, '\n\n').trim()
const translated = (hy, ru, en) => ({ hy, ru, en })
const positive = value => { const n = Number(value); if (!Number.isFinite(n) || n <= 0) throw new Error(`Invalid Swift price: ${value}`); return Math.round(n * 100) / 100 }
const slug = value => { if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value)) throw new Error(`Invalid Swift slug: ${value}`); return value }
const image = (value, origin) => {
  const candidate = value?.size_urls?.maximum || value?.file_url || value?.url || value?.media?.file_url
  if (!candidate) throw new Error('Missing Swift image')
  const url = new URL(candidate, origin)
  if (![fleetOrigin, transferOrigin].includes(url.origin) || !url.pathname.startsWith('/uploads/')) throw new Error('Unexpected Swift image origin/path')
  return url.href
}
const catalogImage = (source, publicSlug) => {
  const extension = new URL(source).pathname.match(/\.(webp|png|jpe?g)$/i)?.[1]?.toLowerCase()
  if (!extension) throw new Error('Unsupported catalog image format')
  return `/catalog/rentals/${publicSlug}.${extension}`
}
async function get(url) {
  const response = await fetch(url, { redirect: 'error', signal: AbortSignal.timeout(30000) })
  if (!response.ok) throw new Error(`Swift API HTTP ${response.status}: ${url}`)
  const data = await response.json()
  if (data.success !== true) throw new Error(`Swift API unsuccessful: ${url}`)
  return data
}
const [fleetEn, fleetRu, transfersEn, transfersRu] = await Promise.all([
  ...locales.map(locale => get(`${fleetOrigin}/backend/api/fleet?language_id=${locale}`)),
  ...locales.map(locale => get(`${transferOrigin}/api/transfers?language_id=${locale}`)),
])
if (!Array.isArray(fleetEn.products) || !fleetEn.products.length || !Array.isArray(transfersEn.transferTypes) || !transfersEn.transferTypes.length) throw new Error('Swift catalog is incomplete')
const capturedAt = new Date().toISOString()
const records = []
const byId = (items, id) => items.find(item => item.id === id)
for (const [index, car] of fleetEn.products.filter(car => Number(car.show_status) === 1).entries()) {
  const ru = byId(fleetRu.products, car.id)
  if (!ru?.title) throw new Error(`Missing Russian car translation: ${car.id}`)
  const baseTitle = text(car.title).replace(/\s+or similar$/i, '')
  const similar = /or similar/i.test(car.title)
  const titleHy = `${baseTitle}${similar ? ' կամ համարժեք' : ''}`
  const publicSlug = `car-${slug(car.slug)}`
  const sourceImage = image(car.main_image, fleetOrigin)
  const tariffs = ['price1', 'price2_3', 'price4_6', 'price7'].map((key, tier) => ({
    key,
    label: [translated('1 օր', '1 день', '1 day'), translated('2–3 օր', '2–3 дня', '2–3 days'), translated('4–6 օր', '4–6 дней', '4–6 days'), translated('7+ օր', '7+ дней', '7+ days')][tier],
    price: positive(car[key]),
  }))
  // The public API has already applied the current seasonal discounts.
  const features = translated(
    [`${car.passenger_count} նստատեղ`, `${car.luggage_count} ուղեբեռ`, car.transmission_id === 1 ? 'Ավտոմատ' : 'Մեխանիկական', ...(car.conditioning ? ['Օդորակիչ'] : [])],
    [`${car.passenger_count} мест`, `${car.luggage_count} багажа`, text(ru.transmission?.title), ...(car.conditioning ? ['Кондиционер'] : [])],
    [`${car.passenger_count} seats`, `${car.luggage_count} bags`, text(car.transmission?.title), ...(car.conditioning ? ['Air conditioning'] : [])],
  )
  records.push({
    slug: publicSlug, type: 'transport',
    title: translated(titleHy, text(ru.title), text(car.title)),
    description: translated(`${titleHy}՝ ${car.passenger_count} նստատեղով և ${car.luggage_count} ուղեբեռի տեղով։ Նշված մեկնարկային օրավարձը գործում է 7 և ավելի օրվա վարձակալության դեպքում՝ ընթացիկ սեզոնի համար։ Վերջնական գինն ու հասանելիությունը հաստատվում են ընտրված ամսաթվերի հիման վրա։`, `${text(ru.title)}: ${car.passenger_count} мест, ${car.luggage_count} места для багажа. Указана минимальная стоимость в сутки при аренде от 7 дней в текущем сезоне. Стоимость и доступность на выбранные даты подтвердит GoVista.`, `${text(car.title)} with ${car.passenger_count} seats and space for ${car.luggage_count} bags. The starting daily rate applies to rentals of 7+ days in the current season. GoVista will confirm pricing and availability for your dates.`),
    location: translated('Երևան, Հայաստան', 'Ереван, Армения', 'Yerevan, Armenia'),
    image: catalogImage(sourceImage, publicSlug), gallery: [], price_from: positive(car.price7), currency: 'AMD',
    unit: translated('օրական՝ 7+ օրվա դեպքում', 'в день при аренде от 7 дней', 'per day for 7+ days'),
    features, icon: 'CarFront', rating: 0, review_count: 0, featured: Boolean(car.best), active: true, sort_order: index,
    supplier: { code: 'swift', name: 'Swift Rent', reference: `car:${car.id}`, url: `${fleetOrigin}/car/${car.slug}`, source_image: sourceImage, synced_at: capturedAt, minimum_days: 7, tariffs, deposit: Number(car.deposit || 0), passengers: Number(car.passenger_count), luggage: Number(car.luggage_count) },
  })
}
const placesHy = { Zvartnots: 'Զվարթնոց օդանավակայան', Yerevan: 'Երևան', Gyumri: 'Գյումրի', Tsaghkadzor: 'Ծաղկաձոր', Dilijan: 'Դիլիջան', Etchmiadzin: 'Էջմիածին', Jermuk: 'Ջերմուկ', Ijevan: 'Իջևան', 'Sevan Lake': 'Սևանա լիճ', Tbilisi: 'Թբիլիսի', Kutaisi: 'Քութաիսի', Batumi: 'Բաթումի' }
const categories = Object.fromEntries(transfersEn.categories.map(c => [String(c.id), c]))
const categoryNames = { standard: translated('Ստանդարտ', 'Стандарт', 'Standard'), minivan: translated('Մինիվեն', 'Минивэн', 'Minivan'), '4X4-drive': translated('Ամենագնաց', 'Внедорожник', 'SUV') }
const routesRu = transfersRu.transferTypes.flatMap(group => group.transfers)
for (const [index, route] of transfersEn.transferTypes.flatMap(group => group.transfers).entries()) {
  const ru = byId(routesRu, route.id)
  if (!ru?.title || !route.pickup_location || !route.destination_location) throw new Error(`Incomplete Swift transfer: ${route.id}`)
  const tariffs = Object.entries(route.price).filter(([, rate]) => Number(rate?.price) > 0).map(([id, rate]) => {
    const category = categories[id]
    if (!category || !Number.isInteger(Number(rate.passengers)) || Number(rate.passengers) < 1 || !Number.isInteger(Number(rate.luggage)) || Number(rate.luggage) < 0) throw new Error(`Invalid transfer capacity/category: ${route.id}/${id}`)
    return { key: category.slug, label: categoryNames[category.slug] || translated(category.additional_title, category.additional_title, category.additional_title), price: positive(rate.price), passengers: Number(rate.passengers), luggage: Number(rate.luggage) }
  }).sort((a, b) => a.price - b.price)
  if (!tariffs.length) throw new Error(`No priced transfer categories: ${route.id}`)
  const from = placesHy[route.pickup_location] || text(route.pickup_location)
  const to = placesHy[route.destination_location] || text(route.destination_location)
  const publicSlug = `transfer-${slug(route.slug)}`
  const sourceImage = image(route.photo, transferOrigin)
  records.push({
    slug: publicSlug, type: 'transfer',
    title: translated(`${from} → ${to}`, text(ru.title), text(route.title)),
    description: translated(`Անհատական տրանսֆեր վարորդով՝ ${from} → ${to} երթուղով։ Գինը մեկ ուղղությամբ ամբողջ մեքենայի համար է։ Ընտրեք մեքենայի դասը՝ ուղևորների և ուղեբեռի քանակին համապատասխան։ Վերջնական պայմաններն ու հասանելիությունը կհաստատենք ձեր հայտի հիման վրա։`, `Индивидуальный трансфер с водителем: ${text(ru.title)}. Цена указана в одну сторону за автомобиль. Выберите класс по количеству пассажиров и багажа. GoVista подтвердит окончательные условия и доступность по вашему запросу.`, `Private transfer with a driver: ${text(route.title)}. Prices are one way, per vehicle. Choose a class for your passenger and luggage count. GoVista will confirm final terms and availability after your request.`),
    location: translated(`${from} → ${to}`, `${text(ru.pickup_location)} → ${text(ru.destination_location)}`, `${text(route.pickup_location)} → ${text(route.destination_location)}`),
    image: catalogImage(sourceImage, publicSlug), gallery: [], price_from: tariffs[0].price, currency: 'AMD',
    unit: translated('մեկ ուղղությամբ՝ ամբողջ մեքենայի համար', 'в одну сторону за автомобиль', 'one way, per vehicle'),
    features: translated(['Վարորդով', `${tariffs[0].passengers} ուղևոր`, `${tariffs[0].luggage} ուղեբեռ`, `${route.distance} կմ`], ['С водителем', `${tariffs[0].passengers} пассажира`, `${tariffs[0].luggage} багажа`, `${route.distance} км`], ['With driver', `${tariffs[0].passengers} passengers`, `${tariffs[0].luggage} bags`, `${route.distance} km`]),
    icon: 'Bus', rating: 0, review_count: 0, featured: Boolean(route.best), active: true, sort_order: index,
    supplier: { code: 'swift', name: 'Swift Rent', reference: `transfer:${route.id}`, url: `${transferOrigin}/destinations/${route.slug}`, source_image: sourceImage, synced_at: capturedAt, tariffs, distance_km: Number(route.distance), origin: route.pickup_location, destination: route.destination_location },
  })
}
if (new Set(records.map(r => r.slug)).size !== records.length) throw new Error('Duplicate supplier slugs')
const manifest = { supplier: 'swift', captured_at: capturedAt, sources: [`${fleetOrigin}/backend/api/fleet`, `${transferOrigin}/api/transfers`], records }
if (process.argv.includes('--write')) {
  const assets = [...records]
  await mkdir(new URL('../frontend/public/catalog/rentals/', import.meta.url), { recursive: true })
  await Promise.all(Array.from({ length: 4 }, async () => {
    while (assets.length) {
      const record = assets.shift()
      const response = await fetch(record.supplier.source_image, { redirect: 'error', signal: AbortSignal.timeout(30000) })
      if (!response.ok || !response.headers.get('content-type')?.startsWith('image/')) throw new Error(`Invalid catalog image: ${record.slug}`)
      const bytes = new Uint8Array(await response.arrayBuffer())
      if (!bytes.length || bytes.length > 10000000) throw new Error(`Unexpected image size: ${record.slug}`)
      await writeFile(new URL(`../frontend/public${record.image}`, import.meta.url), bytes)
    }
  }))
  const target = new URL('../backend/database/data/swift-catalog.json', import.meta.url)
  await mkdir(new URL('../backend/database/data/', import.meta.url), { recursive: true })
  await writeFile(target, JSON.stringify(manifest, null, 2) + '\n')
}
console.log(JSON.stringify({ mode: process.argv.includes('--write') ? 'written' : 'preview', cars: records.filter(r => r.type === 'transport').length, transfers: records.filter(r => r.type === 'transfer').length, captured_at: capturedAt, currency: 'AMD', startingCarPrice: Math.min(...records.filter(r => r.type === 'transport').map(r => r.price_from)), startingTransferPrice: Math.min(...records.filter(r => r.type === 'transfer').map(r => r.price_from)) }, null, 2))
